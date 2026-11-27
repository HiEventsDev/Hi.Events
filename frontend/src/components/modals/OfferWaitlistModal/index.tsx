import {EventSettings, GenericModalProps, IdParam, WaitlistProductStats, WaitlistStats} from "../../../types.ts";
import {Modal} from "../../common/Modal";
import {useOfferWaitlistEntry} from "../../../mutations/useOfferWaitlistEntry.ts";
import {showError, showSuccess} from "../../../utilites/notifications.tsx";
import {t} from "@lingui/macro";
import {Anchor, NumberInput, Text, VisuallyHidden} from "@mantine/core";
import {IconSend} from "@tabler/icons-react";
import {NavLink} from "react-router";
import {Button} from "../../common/Button";
import {useState} from "react";
import {BouncingEmoji} from "../../common/BouncingEmoji";
import {TanStackTable, TanStackTableColumn} from "../../common/TanStackTable";
import {CellText} from "../../common/TanStackTable/cells.tsx";
import {StatusBadge} from "../../common/StatusBadge";

interface OfferWaitlistModalProps extends GenericModalProps {
    eventId: IdParam;
    eventSettings?: EventSettings;
    stats?: WaitlistStats;
    eventOccurrenceId?: IdParam | null;
    isRecurring?: boolean;
}

const getDefaultQuantity = (product: WaitlistProductStats): number => {
    if (product.available === 0) return 0;
    if (product.available === null) return product.waiting;
    return Math.min(product.waiting, product.available);
};

const getMaxQuantity = (product: WaitlistProductStats): number => {
    if (product.available === null) return product.waiting;
    return Math.min(product.waiting, product.available);
};

export const OfferWaitlistModal = ({onClose, eventId, eventSettings, stats, eventOccurrenceId, isRecurring}: OfferWaitlistModalProps) => {
    const mutation = useOfferWaitlistEntry();
    const [loadingProductId, setLoadingProductId] = useState<number | null>(null);

    const productsWithWaiting = stats?.products?.filter(p => p.waiting > 0) ?? [];
    const isAutoProcess = !!eventSettings?.waitlist_auto_process;
    const showAllDatesNote = !!isRecurring && !eventOccurrenceId;

    const [quantities, setQuantities] = useState<Record<number, number>>(() => {
        const initial: Record<number, number> = {};
        productsWithWaiting.forEach(p => {
            initial[p.product_price_id] = getDefaultQuantity(p);
        });
        return initial;
    });

    const timeoutHours = eventSettings?.waitlist_offer_timeout_minutes
        ? Math.round(eventSettings.waitlist_offer_timeout_minutes / 60 * 10) / 10
        : null;

    const handleOffer = (product: WaitlistProductStats) => {
        const qty = quantities[product.product_price_id] ?? 1;
        setLoadingProductId(product.product_price_id);

        mutation.mutate({
            eventId,
            productPriceId: product.product_price_id,
            quantity: qty,
            eventOccurrenceId,
        }, {
            onSuccess: (response) => {
                const count = response?.data?.length ?? qty;
                showSuccess(
                    count === 1
                        ? t`Successfully offered tickets to 1 person`
                        : t`Successfully offered tickets to ${count} people`
                );
                setLoadingProductId(null);
            },
            onError: (error: any) => {
                const message = error?.response?.data?.message || t`Failed to offer tickets`;
                showError(message);
                setLoadingProductId(null);
            },
        });
    };

    const isBusy = loadingProductId !== null;

    if (isAutoProcess) {
        return (
            <Modal
                opened
                onClose={onClose}
                heading={t`Offer Tickets`}
            >
                <div style={{textAlign: 'center', padding: '20px 0 10px'}}>
                    <BouncingEmoji emoji="⚡"/>
                    <Text size="lg" fw={600} mb="xs">
                        {t`Auto-offer is enabled`}
                    </Text>
                    <Text size="sm" c="dimmed" mb="md" maw={380} mx="auto">
                        {t`Tickets are automatically offered to waitlisted customers when capacity becomes available.`}
                        {!!timeoutHours && ' ' + t`Offers expire after ${timeoutHours} hours.`}
                    </Text>
                    <Anchor
                        component={NavLink}
                        to={`/manage/event/${eventId}/settings/waitlist`}
                        size="xs"
                    >
                        {t`Change waitlist settings`}
                    </Anchor>
                </div>

                {productsWithWaiting.length > 0 && (
                    <>
                        <Text size="sm" fw={500} mb="xs">{t`Manual offer`}</Text>
                        <Text size="xs" c="dimmed" mb="sm">
                            {t`You can still manually offer tickets if needed.`}
                            {showAllDatesNote && ' ' + t`Counts include all upcoming dates. Each person is offered a spot for the date they joined for.`}
                        </Text>
                        <ProductOfferTable
                            products={productsWithWaiting}
                            quantities={quantities}
                            setQuantities={setQuantities}
                            onOffer={handleOffer}
                            loadingProductId={loadingProductId}
                            isBusy={isBusy}
                        />
                    </>
                )}
            </Modal>
        );
    }

    return (
        <Modal
            opened
            onClose={onClose}
            heading={t`Offer Tickets`}
        >
            <Text size="sm" c="dimmed" mb="xs">
                {t`Each person will receive an email with a reserved spot to complete their purchase.`}
                {!!timeoutHours && ' ' + t`Offers expire after ${timeoutHours} hours.`}
                {showAllDatesNote && ' ' + t`Counts include all upcoming dates. Each person is offered a spot for the date they joined for.`}
            </Text>

            {productsWithWaiting.length === 0 ? (
                <Text c="dimmed" ta="center" py="xl">{t`No products have waiting entries`}</Text>
            ) : (
                <ProductOfferTable
                    products={productsWithWaiting}
                    quantities={quantities}
                    setQuantities={setQuantities}
                    onOffer={handleOffer}
                    loadingProductId={loadingProductId}
                    isBusy={isBusy}
                />
            )}
        </Modal>
    );
};

const ProductOfferTable = ({products, quantities, setQuantities, onOffer, loadingProductId, isBusy}: {
    products: WaitlistProductStats[];
    quantities: Record<number, number>;
    setQuantities: (fn: (prev: Record<number, number>) => Record<number, number>) => void;
    onOffer: (product: WaitlistProductStats) => void;
    loadingProductId: number | null;
    isBusy: boolean;
}) => {
    const columns: TanStackTableColumn<WaitlistProductStats>[] = [
        {
            id: 'product',
            header: t`Product`,
            meta: {sticky: 'left'},
            cell: ({row: {original: product}}) => <CellText strong title={product.product_title}>{product.product_title}</CellText>,
        },
        {
            id: 'waiting',
            header: t`Waiting`,
            meta: {align: 'right'},
            cell: ({row: {original: product}}) => product.waiting,
        },
        {
            id: 'available',
            header: t`Available`,
            meta: {align: 'right'},
            cell: ({row: {original: product}}) => {
                if (product.available === 0) {
                    return <StatusBadge tone="negative">{t`No capacity`}</StatusBadge>;
                }
                if (product.available === null) {
                    return <StatusBadge tone="positive">{t`Unlimited`}</StatusBadge>;
                }
                return product.available;
            },
        },
        {
            id: 'quantity',
            header: t`Qty`,
            meta: {align: 'right'},
            cell: ({row: {original: product}}) => product.available !== 0 && (
                <NumberInput
                    size="xs"
                    min={1}
                    max={getMaxQuantity(product)}
                    mb={0}
                    value={quantities[product.product_price_id] ?? 1}
                    onChange={(val) => setQuantities(prev => ({
                        ...prev,
                        [product.product_price_id]: Number(val) || 1,
                    }))}
                    disabled={isBusy}
                    style={{width: 70, marginLeft: 'auto'}}
                />
            ),
        },
        {
            id: 'actions',
            header: () => <VisuallyHidden>{t`Actions`}</VisuallyHidden>,
            meta: {sticky: 'right'},
            cell: ({row: {original: product}}) => {
                const isRowLoading = loadingProductId === product.product_price_id;

                return product.available !== 0 && (
                    <Button
                        size="xs"
                        variant="light"
                        leftSection={<IconSend size={14}/>}
                        onClick={() => onOffer(product)}
                        disabled={isBusy && !isRowLoading}
                        loading={isRowLoading}
                    >
                        {t`Offer`}
                    </Button>
                );
            },
        },
    ];

    return (
        <TanStackTable
            data={products}
            columns={columns}
            rowProps={product => ({style: product.available === 0 ? {opacity: 0.5} : undefined})}
        />
    );
};
