import {useEffect, useRef, useState} from "react";
import {useQueryClient} from "@tanstack/react-query";
import {Button, Loader} from "@mantine/core";
import {t, Trans} from "@lingui/macro";
import {IconArrowLeft, IconCreditCard, IconX} from "@tabler/icons-react";
import {AxiosError} from "axios";
import {BoxOfficeSession, Order} from "../../../../../types.ts";
import {useGetBoxOfficeOrderPublic} from "../../../queries/useGetBoxOfficeOrderPublic.ts";
import {GET_BOX_OFFICE_ORDERS_PUBLIC_QUERY_KEY} from "../../../queries/useGetBoxOfficeOrdersPublic.ts";
import {GET_BOX_OFFICE_PRODUCTS_PUBLIC_QUERY_KEY} from "../../../queries/useGetBoxOfficeProductsPublic.ts";
import {GET_BOX_OFFICE_OCCUPIED_SEATS_QUERY_KEY} from "../../../queries/useGetBoxOfficeSeating.ts";
import {useStartBoxOfficeCardPayment} from "../../../mutations/useStartBoxOfficeCardPayment.ts";
import {useCancelBoxOfficeCardAction} from "../../../mutations/useCancelBoxOfficeCardAction.ts";
import {formatCurrency} from "../../../../../utilites/currency.ts";
import {showError} from "../../../../../utilites/notifications.tsx";
import {firstApiError} from "../../../../../utilites/apiErrors.ts";
import {ReaderSwitchSheet} from "./ReaderSwitchSheet.tsx";
import classes from "./tabs/SellTab.module.scss";

interface CardTenderProps {
    boxOfficeShortId: string;
    order: Order;
    session: BoxOfficeSession;
    readerLabel: string;
    onCompleted: (order: Order) => void;
    onSessionUpdated: (session: BoxOfficeSession) => void;
    onSwitchToCash: () => void;
    isAbandoning: boolean;
    onBack: () => void;
}

type CardState = 'starting' | 'waiting' | 'failed' | 'reader-unavailable' | 'expired';

const WAITING_HINT_AFTER_MS = 45000;

const errorKey = (order: Order): string | null => {
    const error = order.box_office_card_error;
    if (!error) return null;
    return `${error.code ?? ''}|${(error as { charge_id?: string | null }).charge_id ?? ''}|${error.message ?? ''}`;
};

const failureMessage = (error: NonNullable<Order['box_office_card_error']>): string => {
    switch (error.code) {
        case 'customer_canceled':
            return t`The customer cancelled on the reader`;
        case 'card_read_timed_out':
        case 'timeout':
            return t`No card was presented in time`;
        case 'reader_offline':
            return t`The card reader went offline. Check its power and Wi-Fi.`;
        case 'payment_canceled':
            return t`The card payment was cancelled`;
        case 'card_declined':
        case 'insufficient_funds':
        case 'generic_decline':
            return error.message ?? t`The card was declined`;
        default:
            return error.message ?? t`The card was declined`;
    }
};

export const CardTender = ({boxOfficeShortId, order, session, readerLabel, onCompleted, onSessionUpdated, onSwitchToCash, isAbandoning, onBack}: CardTenderProps) => {
    const queryClient = useQueryClient();
    const startCard = useStartBoxOfficeCardPayment();
    const cancelAction = useCancelBoxOfficeCardAction();
    const [state, setState] = useState<CardState>('starting');
    const [errorMessage, setErrorMessage] = useState<string | null>(null);
    const lastErrorRef = useRef<string | null>(errorKey(order));
    const startedRef = useRef(false);
    const [showWaitingHint, setShowWaitingHint] = useState(false);
    const [readerSheetOpen, setReaderSheetOpen] = useState(false);
    const polled = useGetBoxOfficeOrderPublic(boxOfficeShortId, order.short_id, state === 'starting' || state === 'waiting');
    const current = polled.data ?? order;

    useEffect(() => {
        if (state !== 'waiting') {
            setShowWaitingHint(false);
            return;
        }
        const timer = window.setTimeout(() => setShowWaitingHint(true), WAITING_HINT_AFTER_MS);
        return () => window.clearTimeout(timer);
    }, [state]);

    const complete = (completed: Order) => {
        [
            GET_BOX_OFFICE_ORDERS_PUBLIC_QUERY_KEY,
            GET_BOX_OFFICE_PRODUCTS_PUBLIC_QUERY_KEY,
            GET_BOX_OFFICE_OCCUPIED_SEATS_QUERY_KEY,
        ].forEach(key => queryClient.invalidateQueries({queryKey: [key, boxOfficeShortId]}));
        onCompleted(completed);
    };

    const start = () => {
        setState('starting');
        setErrorMessage(null);
        startCard.mutate({boxOfficeShortId, orderShortId: order.short_id}, {
            onSuccess: ({data}) => {
                if (data.status === 'COMPLETED') {
                    complete(data);
                    return;
                }
                lastErrorRef.current = errorKey(data);
                setState('waiting');
            },
            onError: (error) => {
                const response = error instanceof AxiosError ? error.response : null;
                const message: string | null = response?.data?.message ?? null;
                setErrorMessage(message ?? t`Unable to start the card payment`);
                if (response?.data?.error_code === 'READER_UNAVAILABLE') {
                    setState('reader-unavailable');
                    return;
                }
                setState(response?.data?.error_code === 'SALE_EXPIRED' ? 'expired' : 'failed');
            },
        });
    };

    useEffect(() => {
        if (startedRef.current) return;
        startedRef.current = true;
        start();
    }, []);

    useEffect(() => {
        if (current.status === 'COMPLETED') {
            complete(current);
            return;
        }
        if (state === 'waiting' && current.status === 'RESERVED' && current.is_expired) {
            setErrorMessage(null);
            setState('expired');
            return;
        }
        const key = errorKey(current);
        if (key && key !== lastErrorRef.current && (state === 'waiting' || state === 'starting')) {
            lastErrorRef.current = key;
            setErrorMessage(failureMessage(current.box_office_card_error!));
            setState('failed');
        }
    }, [current.status, errorKey(current), state, current.is_expired]);

    const cancel = () => {
        cancelAction.mutate({boxOfficeShortId, orderShortId: order.short_id}, {
            onSuccess: () => {
                setState('failed');
                setErrorMessage(t`Card payment cancelled`);
            },
            onError: (error) => showError(firstApiError(error, t`Unable to cancel the card payment`)),
        });
    };

    const switchToCash = () => {
        if (state !== 'waiting') {
            onSwitchToCash();
            return;
        }
        cancelAction.mutate({boxOfficeShortId, orderShortId: order.short_id}, {onSettled: onSwitchToCash});
    };

    return (
        <div className={classes.stageCard}>
            <div className={classes.stageTotal}>
                <div className={classes.stageTotalLabel}>{t`Total to pay`}</div>
                <div className={classes.stageTotalValue}>{formatCurrency(order.total_gross, order.currency)}</div>
            </div>

            <div className={classes.hero}>
                <div className={classes.heroIcon} style={{background: 'var(--hi-color-gray)', color: 'var(--hi-primary)'}}>
                    {state === 'starting' || state === 'waiting' ? <Loader size="sm"/> : <IconCreditCard size={30}/>}
                </div>
                {state === 'starting' && <p className={classes.heroSub}>{t`Sending to reader…`}</p>}
                {state === 'waiting' && (
                    <>
                        <h2 className={classes.heroTitle}>{t`Present card on reader`}</h2>
                        <p className={classes.heroSub}><Trans>Tap, insert or swipe on {readerLabel}</Trans></p>
                        {showWaitingHint && (
                            <p className={classes.heroSub}>{t`Still waiting? Check the reader screen. If it shows nothing, cancel and try again.`}</p>
                        )}
                    </>
                )}
                {(state === 'failed' || state === 'reader-unavailable') && (
                    <>
                        <h2 className={classes.heroTitle} style={{color: 'var(--mantine-color-red-9)'}}>
                            {state === 'failed' ? t`Payment not completed` : t`Reader unavailable`}
                        </h2>
                        <p className={classes.heroSub}>{errorMessage}</p>
                    </>
                )}
                {state === 'expired' && (
                    <>
                        <h2 className={classes.heroTitle} style={{color: 'var(--mantine-color-red-9)'}}>{t`Sale expired`}</h2>
                        <p className={classes.heroSub}>{errorMessage}</p>
                    </>
                )}
            </div>

            {state === 'waiting' && (
                <Button variant="light" color="red" leftSection={<IconX size={16}/>} loading={cancelAction.isPending}
                        onClick={cancel} data-testid="box-office-card-cancel-button">
                    {t`Cancel card payment`}
                </Button>
            )}

            {state === 'failed' && (
                <Button size="lg" fullWidth onClick={start} loading={startCard.isPending} data-testid="box-office-card-retry-button">
                    {t`Try again`}
                </Button>
            )}

            {state === 'reader-unavailable' && (
                <Button size="lg" fullWidth onClick={() => setReaderSheetOpen(true)} data-testid="box-office-card-choose-reader-button">
                    {t`Choose another reader`}
                </Button>
            )}

            {readerSheetOpen && (
                <ReaderSwitchSheet
                    opened
                    onClose={() => setReaderSheetOpen(false)}
                    boxOfficeShortId={boxOfficeShortId}
                    session={session}
                    onUpdated={(updated) => {
                        onSessionUpdated(updated);
                        if (updated.reader) {
                            start();
                        } else {
                            switchToCash();
                        }
                    }}
                />
            )}

            {state !== 'expired' && (
                <Button variant="light" onClick={switchToCash} loading={cancelAction.isPending && state === 'waiting'} disabled={state === 'starting'}>
                    {t`Switch to cash`}
                </Button>
            )}

            <Button variant="subtle" color="gray" leftSection={<IconArrowLeft size={16}/>} onClick={onBack} disabled={state === 'starting'}
                    loading={isAbandoning} data-testid="box-office-card-back-button">
                {t`Back to sale`}
            </Button>
        </div>
    );
};
