import {useEffect, useMemo, useState} from "react";
import {Alert, Button, Drawer, Group, Loader} from "@mantine/core";
import {useMediaQuery} from "@mantine/hooks";
import {t} from "@lingui/macro";
import {AxiosError} from "axios";
import {BoxOfficePublic, BoxOfficeSession, Order} from "../../../../../../types.ts";
import {useGetBoxOfficeProductsPublic} from "../../../../queries/useGetBoxOfficeProductsPublic.ts";
import {useGetBoxOfficeOccupiedSeats, useGetBoxOfficeSeatMap} from "../../../../queries/useGetBoxOfficeSeating.ts";
import {CartLine, lineCap, openSeatCount, orderItemsFor, useBoxOfficeCart} from "../../../../hooks/useBoxOfficeCart.ts";
import {useCreateBoxOfficeOrder} from "../../../../mutations/useCreateBoxOfficeOrder.ts";
import {useTenderBoxOfficeOrder} from "../../../../mutations/useTenderBoxOfficeOrder.ts";
import {useAbandonBoxOfficeOrder} from "../../../../mutations/useAbandonBoxOfficeOrder.ts";
import {publicBoxOfficeClient} from "../../../../api/box-office-public.client.ts";
import {showError} from "../../../../../../utilites/notifications.tsx";
import {firstApiError} from "../../../../../../utilites/apiErrors.ts";
import {getActiveSaleShortId, rememberActiveSale} from "../../../../utilites/boxOfficeSession.ts";
import {seatedTypes, seatsToChoose} from "../../../../utilites/boxOfficeSeating.ts";
import {buildAttendeeSlots, QuestionAnswer, slotKey, unansweredRequiredQuestions} from "../../../../utilites/boxOfficeQuestions.ts";
import {AttendeeQuestionsStage} from "../AttendeeQuestionsStage.tsx";
import {useHaptics} from "../../../../../../hooks/useHaptics.ts";
import {ProductTile} from "../ProductTile.tsx";
import {CartPanel} from "../CartPanel.tsx";
import {CartBar} from "../CartBar.tsx";
import {DiscountSheet, PriceOverrideSheet} from "../AmountSheet.tsx";
import {BuyerSheet} from "../BuyerSheet.tsx";
import {TenderStage} from "../TenderStage.tsx";
import {CardTender} from "../CardTender.tsx";
import {ReaderSwitchSheet} from "../ReaderSwitchSheet.tsx";
import {SaleComplete} from "../SaleComplete.tsx";
import {DESKTOP_QUERY} from "../Sheet.tsx";
import {bandOf, indexLayout, seatLabel} from "../../../../../seating/components/lib/layoutIndex.ts";
import {BoxOfficeSeatMap} from "../BoxOfficeSeatMap.tsx";
import {SeatDetails} from "../CartPanel.tsx";
import classes from "./SellTab.module.scss";

interface SellTabProps {
    boxOffice: BoxOfficePublic;
    boxOfficeShortId: string;
    session: BoxOfficeSession;
    onSaleStateChange?: (inProgress: boolean) => void;
    onSessionUpdated: (session: BoxOfficeSession) => void;
}

type Stage = 'cart' | 'questions' | 'tender' | 'card' | 'cash-after-card' | 'complete';

type ChargeOverrides = { answers?: QuestionAnswer[]; attendeeAnswers?: Record<string, QuestionAnswer[]> };

export const SellTab = ({boxOffice, boxOfficeShortId, session, onSaleStateChange, onSessionUpdated}: SellTabProps) => {
    const isDesktop = useMediaQuery(DESKTOP_QUERY);
    const haptic = useHaptics();
    const currency = boxOffice.event.currency;
    const occurrenceId = session.event_occurrence?.id ? Number(session.event_occurrence.id) : null;
    const catalogueQuery = useGetBoxOfficeProductsPublic(boxOfficeShortId, occurrenceId);
    const products = catalogueQuery.data?.products;
    const questions = catalogueQuery.data?.questions ?? [];
    const seatMapQuery = useGetBoxOfficeSeatMap(boxOfficeShortId);
    const seatMap = seatMapQuery.data;
    const seatIndex = useMemo(() => seatMap ? indexLayout(seatMap.layout) : null, [seatMap]);
    const bandOfSeat = useMemo(() => seatIndex ? (uid: string) => bandOf(seatIndex, uid) : undefined, [seatIndex]);
    const cart = useBoxOfficeCart(products, bandOfSeat);
    const types = useMemo(() => seatMap ? seatedTypes(products ?? [], seatMap.band_products) : [], [seatMap, products]);
    const typesByPrice = useMemo(() => new Map(types.map(type => [type.price.id, type])), [types]);
    const hasSeatsInSale = cart.lines.some(line => typesByPrice.has(line.priceId));
    const occupiedSeatsQuery = useGetBoxOfficeOccupiedSeats(boxOfficeShortId, occurrenceId, seatMap?.version, hasSeatsInSale);
    const toChoose = seatsToChoose(cart.lines, typesByPrice);
    const seatDetails = (uid: string): SeatDetails => {
        const seat = seatIndex?.seats.get(uid)?.seat;
        return {label: seatIndex ? seatLabel(seatIndex, uid) : uid, note: seat?.note ?? null, accessible: seat?.acc ?? false, companion: seat?.comp ?? false};
    };
    const createOrder = useCreateBoxOfficeOrder();
    const tenderOrder = useTenderBoxOfficeOrder();
    const abandonOrder = useAbandonBoxOfficeOrder();

    const [stage, setStage] = useState<Stage>('cart');
    const [order, setOrder] = useState<Order | null>(null);
    const [cartSheetOpen, setCartSheetOpen] = useState(false);
    const [buyerOpen, setBuyerOpen] = useState(false);
    const [buyerRequired, setBuyerRequired] = useState(false);
    const [buyerErrors, setBuyerErrors] = useState<Record<string, string> | undefined>();
    const [attendeeErrors, setAttendeeErrors] = useState<Record<string, string> | undefined>();
    const [attendeeQuestionsDone, setAttendeeQuestionsDone] = useState(false);
    const [discountOpen, setDiscountOpen] = useState(false);
    const [overrideLine, setOverrideLine] = useState<CartLine | null>(null);
    const [readerSheetOpen, setReaderSheetOpen] = useState(false);
    const [pendingCharge, setPendingCharge] = useState<ChargeOverrides | null>(null);
    const [saleToResume] = useState(() => getActiveSaleShortId(boxOfficeShortId));
    const activeSaleShortId = order?.status === 'RESERVED' && (stage === 'tender' || stage === 'card' || stage === 'cash-after-card')
        ? order.short_id
        : null;

    const saleInProgress = stage !== 'cart' || cart.totals.itemCount > 0;
    useEffect(() => {
        onSaleStateChange?.(saleInProgress);
        return () => onSaleStateChange?.(false);
    }, [saleInProgress]);

    const orderQuestions = boxOffice.collect_order_questions ? questions.filter(q => q.belongs_to === 'ORDER') : [];
    const productQuestions = boxOffice.collect_order_questions ? questions.filter(q => q.belongs_to === 'PRODUCT') : [];
    const attendeeSlots = useMemo(
        () => buildAttendeeSlots(cart.lines, cart.priceLookup, productQuestions),
        [cart.lines, cart.priceLookup, productQuestions],
    );

    useEffect(() => {
        setAttendeeQuestionsDone(false);
    }, [cart.state.lines]);

    const charge = (overrides?: ChargeOverrides) => {
        const payloadLines = cart.lines;
        const orderItems = orderItemsFor(payloadLines, bandOfSeat);
        const answers = overrides?.answers ?? cart.state.questionAnswers;
        if (!overrides?.answers && unansweredRequiredQuestions(orderQuestions, answers).length > 0) {
            setBuyerRequired(true);
            setBuyerOpen(true);
            return;
        }
        const attendeeAnswers = overrides?.attendeeAnswers ?? cart.state.attendeeAnswers;
        if (attendeeSlots.length > 0 && !overrides?.attendeeAnswers && !attendeeQuestionsDone) {
            setCartSheetOpen(false);
            setStage('questions');
            return;
        }
        const attendees = payloadLines.flatMap(line => Array.from({length: line.quantity}, (_, unit) => ({
            product_id: line.productId,
            product_price_id: line.priceId,
            questions: attendeeAnswers[slotKey(line.priceId, unit)] ?? [],
        })));
        const buyer = {
            first_name: cart.state.buyer.first_name || undefined,
            last_name: cart.state.buyer.last_name || undefined,
            email: cart.state.buyer.email || undefined,
        };
        cart.clearServerErrors();
        createOrder.mutate({
            boxOfficeShortId,
            payload: {
                idempotency_key: cart.state.idempotencyKey,
                items: orderItems.map(({item}) => item),
                discount: cart.state.discount,
                buyer,
                questions: answers,
                attendees,
            },
        }, {
            onSuccess: ({data}) => {
                setOrder(data);
                setCartSheetOpen(false);
                setStage('tender');
            },
            onError: (error) => {
                if (!(error instanceof AxiosError)) {
                    cart.applyServerErrors(t`Unable to start the sale. Please try again.`, {});
                    haptic('error');
                    if (!isDesktop) setCartSheetOpen(true);
                    return;
                }
                const response = error.response?.data;
                const questionErrors = Object.fromEntries(
                    Object.entries(response?.errors ?? {})
                        .filter(([key]) => key.startsWith('order.questions'))
                        .map(([key, messages]) => [key, String(Array.isArray(messages) ? messages[0] : messages)]),
                );
                if (Object.keys(questionErrors).length > 0) {
                    setBuyerErrors(questionErrors);
                    setBuyerRequired(true);
                    setBuyerOpen(true);
                    return;
                }
                const attendeeQuestionErrors = Object.fromEntries(
                    Object.entries(response?.errors ?? {})
                        .filter(([key]) => key.startsWith('products.'))
                        .map(([key, messages]) => [key, String(Array.isArray(messages) ? messages[0] : messages)]),
                );
                if (Object.keys(attendeeQuestionErrors).length > 0) {
                    setAttendeeErrors(attendeeQuestionErrors);
                    setStage('questions');
                    return;
                }
                const lostSeatUids: string[] | undefined = response?.errors?.unavailable_seat_uids;
                if (lostSeatUids && lostSeatUids.length > 0) {
                    const lostLabels = [...new Set(lostSeatUids)].map(uid => seatDetails(uid).label).join(', ');
                    cart.dropSeats(lostSeatUids);
                    occupiedSeatsQuery.refetch();
                    haptic('error');
                    showError(t`Just taken by someone else: ${lostLabels}. Choose replacement seats.`);
                    if (!isDesktop) setCartSheetOpen(false);
                    return;
                }
                const lineErrors: Record<number, string> = {};
                Object.entries(response?.errors ?? {}).forEach(([key, messages]) => {
                    const match = key.match(/^items\.(\d+)\./);
                    if (match) {
                        const line = orderItems[Number(match[1])]?.line;
                        if (line) lineErrors[line.priceId] = String(Array.isArray(messages) ? messages[0] : messages);
                    }
                });
                const fallback = error.response
                    ? t`Unable to start the sale. Please try again.`
                    : t`Unable to start the sale. Check the connection and try again.`;
                const cartError = Object.keys(lineErrors).length ? undefined : (response?.message ?? fallback);
                cart.applyServerErrors(cartError, lineErrors);
                occupiedSeatsQuery.refetch();
                haptic('error');
                if (!isDesktop) setCartSheetOpen(!!cartError);
            },
        });
    };

    useEffect(() => {
        if (!pendingCharge) return;
        setPendingCharge(null);
        charge(pendingCharge);
    }, [pendingCharge]);

    useEffect(() => {
        rememberActiveSale(boxOfficeShortId, activeSaleShortId);
    }, [boxOfficeShortId, activeSaleShortId]);

    useEffect(() => {
        if (!saleToResume) return;
        publicBoxOfficeClient.getOrder(boxOfficeShortId, saleToResume)
            .then(({data}) => showOrder(data))
            .catch(() => undefined);
    }, []);

    const leaveSale = () => {
        cart.renewKey();
        setOrder(null);
        setStage('cart');
    };

    const backToCart = () => {
        if (!order) {
            leaveSale();
            return;
        }
        abandonOrder.mutate({boxOfficeShortId, orderShortId: order.short_id}, {
            onSuccess: leaveSale,
            onError: (error) => {
                showError(firstApiError(error, t`Unable to cancel this sale. Check the connection and try again.`));
                if (error instanceof AxiosError && error.response?.status === 409) {
                    recoverOrder();
                }
            },
        });
    };

    const showOrder = (data: Order) => {
        if (data.status === 'COMPLETED') {
            setOrder(data);
            setStage('complete');
            haptic('success');
        } else if (data.status === 'RESERVED') {
            setOrder(data);
            setStage(session.reader && data.box_office_tender === 'CARD' ? 'card' : 'tender');
        } else {
            setOrder(null);
            setStage('cart');
        }
    };

    const recoverOrder = () => {
        if (!order) return;
        publicBoxOfficeClient.getOrder(boxOfficeShortId, order.short_id).then(({data}) => showOrder(data)).catch(() => {
            showError(t`This sale could not be checked. Start it again.`);
            setOrder(null);
            setStage('cart');
        });
    };

    const tender = (tenderType: 'CASH' | 'COMP' | 'OTHER', extra?: { amount_tendered?: number; reference?: string }) => {
        if (!order) return;
        tenderOrder.mutate({
            boxOfficeShortId,
            orderShortId: order.short_id,
            payload: {tender: tenderType, ...extra},
        }, {
            onSuccess: ({data}) => {
                setOrder(data);
                setStage('complete');
                haptic('success');
            },
            onError: (error) => {
                haptic('error');
                showError(error instanceof AxiosError ? (error.response?.data?.message ?? t`Unable to complete sale`) : t`Unable to complete sale`);
                if (error instanceof AxiosError && error.response?.status === 409) {
                    recoverOrder();
                }
            },
        });
    };

    const newSale = () => {
        cart.reset();
        setOrder(null);
        setStage('cart');
        catalogueQuery.refetch();
    };

    if (stage === 'questions' && attendeeSlots.length > 0) {
        return (
            <div className={classes.stage}>
                <AttendeeQuestionsStage
                    slots={attendeeSlots}
                    initialAnswers={cart.state.attendeeAnswers}
                    serverErrors={attendeeErrors}
                    onBack={() => {
                        setAttendeeErrors(undefined);
                        setStage('cart');
                    }}
                    onDone={(answers) => {
                        cart.setAttendeeAnswers(answers);
                        setAttendeeErrors(undefined);
                        setAttendeeQuestionsDone(true);
                        setStage('cart');
                        setPendingCharge({attendeeAnswers: answers});
                    }}
                />
            </div>
        );
    }

    if ((stage === 'tender' || stage === 'cash-after-card' || (stage === 'card' && !session.reader)) && order) {
        return (
            <div className={classes.stage}>
                <TenderStage
                    order={order}
                    isSubmitting={tenderOrder.isPending}
                    isAbandoning={abandonOrder.isPending}
                    cardAvailable={boxOffice.card_payments_enabled}
                    compAvailable={boxOffice.allow_discounts}
                    initialMode={stage === 'cash-after-card' ? 'cash' : 'choose'}
                    onBack={backToCart}
                    onTender={tender}
                    onCard={() => session.reader ? setStage('card') : setReaderSheetOpen(true)}
                />
                {readerSheetOpen && (
                    <ReaderSwitchSheet
                        opened
                        onClose={() => setReaderSheetOpen(false)}
                        boxOfficeShortId={boxOfficeShortId}
                        session={session}
                        onUpdated={(updated) => {
                            onSessionUpdated(updated);
                            if (updated.reader) setStage('card');
                        }}
                    />
                )}
            </div>
        );
    }

    if (stage === 'card' && order && session.reader) {
        return (
            <div className={classes.stage}>
                <CardTender
                    boxOfficeShortId={boxOfficeShortId}
                    order={order}
                    session={session}
                    readerLabel={session.reader.label}
                    onSessionUpdated={onSessionUpdated}
                    onCompleted={(completed) => {
                        setOrder(completed);
                        setStage('complete');
                        haptic('success');
                    }}
                    onSwitchToCash={() => setStage('cash-after-card')}
                    isAbandoning={abandonOrder.isPending}
                    onBack={backToCart}
                />
            </div>
        );
    }

    if (stage === 'complete' && order) {
        return (
            <div className={classes.stage}>
                <SaleComplete
                    order={order}
                    boxOfficeShortId={boxOfficeShortId}
                    eventId={boxOffice.event.id}
                    session={session}
                    onNewSale={newSale}
                />
            </div>
        );
    }

    const overrideEntry = overrideLine ? cart.priceLookup.get(overrideLine.priceId) : undefined;

    const tiles = products?.flatMap(product => product.prices.map(price => {
        if (!seatMapQuery.isSuccess && price.band_prices) {
            return null;
        }
        const isSeated = typesByPrice.has(price.id);
        const line = cart.state.lines[price.id];
        const open = line && isSeated ? openSeatCount(line) : 0;
        return (
            <ProductTile
                key={price.id}
                product={product}
                price={price}
                quantity={line?.quantity ?? 0}
                cap={lineCap(product, price, isSeated)}
                currency={currency}
                error={cart.state.serverErrors.lines[price.id]}
                seatHint={open > 0 ? (open === 1 ? t`1 to seat` : t`${open} to seat`) : undefined}
                onIncrement={() => {
                    cart.increment(product, price, isSeated);
                    haptic('tap');
                }}
                onDecrement={() => {
                    cart.decrement(price.id);
                    haptic('tap');
                }}
            />
        );
    }));

    const panel = (
        <CartPanel
            cart={cart}
            seatsToChoose={toChoose}
            seatDetails={seatIndex ? seatDetails : undefined}
            onRemoveSeat={cart.removeSeat}
            currency={currency}
            allowDiscounts={boxOffice.allow_discounts}
            allowPriceOverride={boxOffice.allow_price_override}
            isCharging={createOrder.isPending}
            onCharge={() => charge()}
            questionsPending={unansweredRequiredQuestions(orderQuestions, cart.state.questionAnswers).length > 0}
            onOpenBuyer={() => setBuyerOpen(true)}
            onOpenDiscount={() => setDiscountOpen(true)}
            onOpenOverride={setOverrideLine}
        />
    );

    return (
        <div className={classes.layout}>
            <div className={classes.products} data-seated={!!seatMap}>
                {catalogueQuery.isLoading && <Loader size="sm" style={{margin: '40px auto'}}/>}
                {catalogueQuery.isError && !products && (
                    <Alert color="red" variant="light" data-testid="box-office-catalogue-error">
                        <Group justify="space-between" gap="sm">
                            {(catalogueQuery.error instanceof AxiosError && catalogueQuery.error.response?.data?.message)
                                || t`We couldn't load the products. Please try again.`}
                            <Button size="xs" variant="light" color="red" loading={catalogueQuery.isFetching}
                                    onClick={() => catalogueQuery.refetch()} data-testid="box-office-catalogue-retry-button">
                                {t`Try again`}
                            </Button>
                        </Group>
                    </Alert>
                )}
                {seatMapQuery.isError && (
                    <Alert color="red" variant="light" className={classes.seatMapError} data-testid="box-office-seat-map-error">
                        <Group justify="space-between" gap="sm">
                            {t`We couldn't load the seating map. Please try again.`}
                            <Button size="xs" variant="light" color="red" loading={seatMapQuery.isFetching}
                                    onClick={() => seatMapQuery.refetch()} data-testid="box-office-seat-map-retry-button">
                                {t`Try again`}
                            </Button>
                        </Group>
                    </Alert>
                )}
                {products && products.length === 0 && (
                    <div className={classes.empty}>{t`Nothing to sell here yet. Add products to this box office.`}</div>
                )}
                {seatMap ? (
                    <div className={classes.seatedProducts}>
                        <div className={classes.seatedTiles}>{tiles}</div>
                        {types.length > 0 && (
                            <BoxOfficeSeatMap
                                boxOfficeShortId={boxOfficeShortId}
                                seatMap={seatMap}
                                occupiedSeatsQuery={occupiedSeatsQuery}
                                cart={cart}
                                types={types}
                                typesByPrice={typesByPrice}
                                currency={currency}
                            />
                        )}
                    </div>
                ) : tiles}
            </div>

            <aside className={classes.cartColumn}>{panel}</aside>

            {!isDesktop && (
                <>
                    <CartBar
                        itemCount={cart.totals.itemCount}
                        total={cart.totals.total}
                        currency={currency}
                        isCharging={createOrder.isPending}
                        seatsToChoose={toChoose}
                        onOpen={() => setCartSheetOpen(true)}
                        onCharge={() => charge()}
                    />
                    <Drawer
                        opened={cartSheetOpen}
                        onClose={() => setCartSheetOpen(false)}
                        position="bottom"
                        size="auto"
                        title={t`Sale`}
                        withCloseButton
                        styles={{content: {borderRadius: '20px 20px 0 0', maxHeight: '92dvh'}, title: {fontWeight: 700}}}
                    >
                        {panel}
                    </Drawer>
                </>
            )}

            <BuyerSheet
                opened={buyerOpen}
                onClose={() => {
                    setBuyerOpen(false);
                    setBuyerRequired(false);
                    setBuyerErrors(undefined);
                }}
                buyer={cart.state.buyer}
                questions={orderQuestions}
                questionAnswers={cart.state.questionAnswers}
                requireAnswers={buyerRequired}
                serverErrors={buyerErrors}
                onSave={(buyer, answers) => {
                    cart.setBuyer(buyer);
                    cart.setQuestionAnswers(answers);
                    setBuyerOpen(false);
                    setBuyerErrors(undefined);
                    if (buyerRequired) {
                        setBuyerRequired(false);
                        setPendingCharge({answers});
                    }
                }}
            />

            {discountOpen && (
                <DiscountSheet
                    opened
                    onClose={() => setDiscountOpen(false)}
                    totalWithDiscount={cart.totalWithDiscount}
                    currency={currency}
                    current={cart.state.discount}
                    onApply={(discount) => {
                        cart.setDiscount(discount);
                        setDiscountOpen(false);
                    }}
                />
            )}

            {overrideLine && overrideEntry && (
                <PriceOverrideSheet
                    opened
                    onClose={() => setOverrideLine(null)}
                    title={overrideEntry.product.title}
                    listPrice={overrideEntry.price.price}
                    currentPrice={overrideLine.overridePrice}
                    currency={currency}
                    onApply={(price) => {
                        cart.setOverride(overrideLine.priceId, price);
                        setOverrideLine(null);
                    }}
                />
            )}
        </div>
    );
};
