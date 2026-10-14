import {ReactNode, useState} from "react";
import {useParams} from "react-router";
import {Alert, Anchor, Avatar, Badge, Button, Pagination, SegmentedControl, Skeleton, Text, TextInput, Tooltip} from "@mantine/core";
import {useDebouncedValue} from "@mantine/hooks";
import {IconAlertCircle, IconDownload, IconPackage, IconSearch, IconTicket} from "@tabler/icons-react";
import {t} from "@lingui/macro";
import {
    EventType,
    GenericModalProps,
    IdParam,
    ProductPurchase,
    ProductPurchaseExportFilters,
    ProductPurchaseStatus,
    ProductType,
    QueryFilterOperator,
    QueryFilters,
} from "../../../types.ts";
import {useGetEvent} from "../../../queries/useGetEvent.ts";
import {useGetEventOccurrences} from "../../../queries/useGetEventOccurrences.ts";
import {useGetProductPurchases} from "../../../queries/useGetProductPurchases.ts";
import {useGetProductPurchaseSummary} from "../../../queries/useGetProductPurchaseSummary.ts";
import {getProductFromEvent} from "../../../utilites/helpers.ts";
import {prettyDate, relativeDate} from "../../../utilites/dates.ts";
import {productClient} from "../../../api/product.client.ts";
import {downloadBinary} from "../../../utilites/download.ts";
import {showError} from "../../../utilites/notifications.tsx";
import {Currency} from "../../common/Currency";
import {OccurrenceSelect} from "../../common/OccurrenceSelect";
import {SideDrawer, SideDrawerHeading, SideDrawerSection, SideDrawerStats} from "../../common/SideDrawer";
import {ManageOrderModal} from "../ManageOrderModal";
import classes from "./ProductPurchasesModal.module.scss";

interface ProductPurchasesModalProps {
    productId: IdParam;
}

type PurchaseView = 'active' | 'awaiting' | 'refunded' | 'cancelled' | 'all';

const PER_PAGE = 25;

const REFUNDED_STATUSES = ['REFUNDED', 'PARTIALLY_REFUNDED'];

const getViewFilters = (view: PurchaseView): Pick<ProductPurchaseExportFilters, 'statuses' | 'refund_statuses'> => {
    switch (view) {
        case 'active':
            return {statuses: [ProductPurchaseStatus.Sold, ProductPurchaseStatus.AwaitingPayment]};
        case 'awaiting':
            return {statuses: [ProductPurchaseStatus.AwaitingPayment]};
        case 'refunded':
            return {refund_statuses: REFUNDED_STATUSES};
        case 'cancelled':
            return {statuses: [ProductPurchaseStatus.Cancelled]};
        default:
            return {};
    }
};

const getRefundLabel = (refundStatus: ProductPurchase['refund_status']) => {
    switch (refundStatus) {
        case 'REFUNDED':
            return t`Refunded`;
        case 'PARTIALLY_REFUNDED':
            return t`Partially refunded`;
        case 'REFUND_PENDING':
            return t`Refund pending`;
        case 'REFUND_FAILED':
            return t`Refund failed`;
        default:
            return null;
    }
};

const PurchaseStatusBadge = ({purchase}: { purchase: ProductPurchase }) => {
    if (purchase.status === ProductPurchaseStatus.Cancelled) {
        return <Badge color="red" variant="light" size="sm">{t`Cancelled`}</Badge>;
    }

    if (purchase.status === ProductPurchaseStatus.AwaitingPayment) {
        return <Badge color="orange" variant="light" size="sm">{t`Awaiting payment`}</Badge>;
    }

    return <Badge color="teal" variant="light" size="sm">{t`Sold`}</Badge>;
};

const PurchaseQuantity = ({purchase}: { purchase: ProductPurchase }) => {
    const isCancelled = purchase.status === ProductPurchaseStatus.Cancelled;
    const quantity = isCancelled
        ? purchase.cancelled_quantity
        : purchase.sold_quantity + purchase.awaiting_payment_quantity;

    return (
        <>
            <span className={isCancelled ? classes.cancelledQuantity : classes.quantity}>
                <span className={classes.quantityLabel}>{t`Qty`}</span> {quantity}
            </span>
            {!isCancelled && purchase.cancelled_quantity > 0 && (
                <span className={classes.quantityNote}>{t`${purchase.cancelled_quantity} cancelled`}</span>
            )}
        </>
    );
};

export const ProductPurchasesModal = ({onClose, productId}: GenericModalProps & ProductPurchasesModalProps) => {
    const {eventId} = useParams();
    const {data: event} = useGetEvent(eventId);
    const isRecurring = event?.type === EventType.RECURRING;
    const {data: occurrencesData} = useGetEventOccurrences(eventId, {pageNumber: 1, perPage: 100} as QueryFilters, isRecurring);
    const occurrences = occurrencesData?.data || [];
    const product = getProductFromEvent(Number(productId), event);

    const [view, setView] = useState<PurchaseView>('active');
    const [occurrenceId, setOccurrenceId] = useState<string | null>(null);
    const [search, setSearch] = useState('');
    const [debouncedSearch] = useDebouncedValue(search.trim(), 300);
    const [page, setPage] = useState(1);
    const [isExporting, setIsExporting] = useState(false);
    const [selectedOrderId, setSelectedOrderId] = useState<IdParam>();

    const viewFilters = getViewFilters(view);
    const filterFields: QueryFilters['filterFields'] = {};
    if (viewFilters.statuses) {
        filterFields.status = {operator: QueryFilterOperator.In, value: viewFilters.statuses.join(',')};
    }
    if (viewFilters.refund_statuses) {
        filterFields.refund_status = {operator: QueryFilterOperator.In, value: viewFilters.refund_statuses.join(',')};
    }
    if (occurrenceId) {
        filterFields.event_occurrence_id = {operator: QueryFilterOperator.Equals, value: occurrenceId};
    }

    const purchasesQuery = useGetProductPurchases(eventId, productId, {
        pageNumber: page,
        perPage: PER_PAGE,
        query: debouncedSearch,
        filterFields,
    });
    const summaryQuery = useGetProductPurchaseSummary(eventId, productId, occurrenceId ?? undefined);
    const summary = summaryQuery.data;

    const purchases = purchasesQuery.data?.data;
    const pagination = purchasesQuery.data?.meta;

    if (!event || !product) {
        return <SideDrawer opened={true} onClose={onClose} loading/>;
    }

    const changeView = (value: string) => {
        setView(value as PurchaseView);
        setPage(1);
    };

    const handleOrderClose = () => {
        setSelectedOrderId(undefined);
        purchasesQuery.refetch();
        summaryQuery.refetch();
    };

    const handleExport = async () => {
        setIsExporting(true);
        try {
            const blob = await productClient.exportPurchases(eventId, {
                product_id: productId,
                event_occurrence_id: occurrenceId ?? undefined,
                query: debouncedSearch || undefined,
                ...viewFilters,
            });
            downloadBinary(blob, 'product-purchases.csv');
        } catch {
            showError(t`Failed to export purchases. Please try again.`);
        } finally {
            setIsExporting(false);
        }
    };

    const isTicket = product.product_type === ProductType.Ticket;
    const hasNoPurchases = !!summary && !occurrenceId
        && summary.sold_quantity + summary.awaiting_payment_quantity + summary.cancelled_quantity === 0;
    const statValue = (value: ReactNode) => summary ? value : <Skeleton height={18} width={40}/>;

    const header = (
        <SideDrawerHeading
            media={(
                <Avatar size={42} radius="xl" variant="light" color="primary">
                    {isTicket ? <IconTicket size={20}/> : <IconPackage size={20}/>}
                </Avatar>
            )}
            title={<span>{product.title}</span>}
            subtitle={summary
                ? <span>{t`Purchases`} · {t`Unique buyers: ${summary.buyer_count}`}</span>
                : <span>{t`Purchases`}</span>}
        />
    );

    const actions = (
        <Button
            variant="default"
            size="xs"
            leftSection={<IconDownload size={14}/>}
            loading={isExporting}
            onClick={handleExport}
            data-testid="product-purchases-export-button"
        >
            {t`Export`}
        </Button>
    );

    const stats = [
        {label: t`Sold`, value: statValue(summary?.sold_quantity)},
        {label: t`Awaiting payment`, value: statValue(summary?.awaiting_payment_quantity)},
        {label: t`Cancelled`, value: statValue(summary?.cancelled_quantity)},
        {label: t`Gross sales`, value: statValue(<Currency currency={event.currency} price={summary?.gross_sales}/>)},
    ];

    return (
        <SideDrawer
            opened={true}
            onClose={onClose}
            header={header}
            actions={actions}
        >
            <SideDrawerStats stats={stats}/>

            {!!summary?.refunded_order_count && (
                <Alert
                    className={classes.notice}
                    color="orange"
                    variant="light"
                    icon={<IconAlertCircle size={18}/>}
                >
                    {t`${summary.refunded_order_count} refunded order(s) include this product but haven't been cancelled, so they still count as sold. Cancel them if the items shouldn't count.`}
                    {' '}
                    {view !== 'refunded' && (
                        <Anchor component="button" size="sm" onClick={() => changeView('refunded')}>
                            {t`Show refunded orders`}
                        </Anchor>
                    )}
                </Alert>
            )}

            <div className={classes.filters}>
                <SegmentedControl
                    size="xs"
                    value={view}
                    onChange={changeView}
                    data={[
                        {value: 'active', label: t`Active`},
                        {value: 'awaiting', label: t`Awaiting payment`},
                        {value: 'refunded', label: t`Refunded`},
                        {value: 'cancelled', label: t`Cancelled`},
                        {value: 'all', label: t`All`},
                    ]}
                    data-testid="product-purchases-view"
                />
                <div className={classes.filterRow}>
                    <TextInput
                        className={classes.search}
                        size="xs"
                        leftSection={<IconSearch size={14}/>}
                        placeholder={t`Search by name, email, or order #...`}
                        aria-label={t`Search purchases`}
                        value={search}
                        onChange={(event) => {
                            setSearch(event.currentTarget.value);
                            setPage(1);
                        }}
                    />
                    {isRecurring && occurrences.length > 0 && (
                        <OccurrenceSelect
                            occurrences={occurrences}
                            timezone={event.timezone}
                            value={occurrenceId}
                            onChange={(value) => {
                                setOccurrenceId(value);
                                setPage(1);
                            }}
                            placeholder={t`All Dates`}
                            filterCancelled={false}
                            clearable
                            size="xs"
                        />
                    )}
                </div>
            </div>

            <SideDrawerSection title={t`Purchases`} count={pagination?.total}>
                {!purchases && (
                    <div className={classes.placeholder}>
                        {[0, 1, 2].map(index => <Skeleton key={index} height={48} radius="sm"/>)}
                    </div>
                )}

                {purchases && purchases.length === 0 && (
                    <Text size="sm" c="dimmed" className={classes.empty}>
                        {hasNoPurchases ? t`No one has bought this yet.` : t`No purchases match these filters.`}
                    </Text>
                )}

                {purchases && purchases.length > 0 && (
                    <div className={classes.list} data-fetching={purchasesQuery.isFetching || undefined}>
                        {purchases.map((purchase) => {
                            const buyerName = [purchase.first_name, purchase.last_name].filter(Boolean).join(' ');
                            const refundLabel = getRefundLabel(purchase.refund_status);

                            return (
                                <button
                                    type="button"
                                    key={`${purchase.order_id}-${purchase.product_price_id}-${purchase.event_occurrence_id}`}
                                    className={classes.row}
                                    onClick={() => setSelectedOrderId(purchase.order_id)}
                                    data-testid="product-purchase-row"
                                >
                                    <div className={classes.buyer}>
                                        <div className={classes.buyerName}>
                                            {buyerName || purchase.email || t`No name`}
                                        </div>
                                        <div className={classes.meta}>
                                            <span>{purchase.order_public_id}</span>
                                            <Tooltip label={prettyDate(purchase.order_created_at, event.timezone)} withArrow>
                                                <span>{relativeDate(purchase.order_created_at)}</span>
                                            </Tooltip>
                                            {buyerName && purchase.email && <span>{purchase.email}</span>}
                                        </div>
                                        {(purchase.price_label || purchase.occurrence_start_date) && (
                                            <div className={classes.meta}>
                                                {purchase.price_label && <span>{purchase.price_label}</span>}
                                                {purchase.occurrence_start_date && (
                                                    <span>{prettyDate(purchase.occurrence_start_date, event.timezone)}</span>
                                                )}
                                            </div>
                                        )}
                                    </div>
                                    <div className={classes.quantityCell}>
                                        <PurchaseQuantity purchase={purchase}/>
                                    </div>
                                    <div className={classes.total}>
                                        {purchase.line_total !== null
                                            ? <Currency currency={purchase.currency} price={purchase.line_total}/>
                                            : '—'}
                                    </div>
                                    <div className={classes.status}>
                                        <PurchaseStatusBadge purchase={purchase}/>
                                        {refundLabel && (
                                            <Badge color="gray" variant="outline" size="xs">{refundLabel}</Badge>
                                        )}
                                    </div>
                                </button>
                            );
                        })}
                    </div>
                )}

                {!!pagination && Number(pagination.last_page) > 1 && (
                    <Pagination
                        className={classes.pagination}
                        size="sm"
                        value={page}
                        onChange={setPage}
                        total={Number(pagination.last_page)}
                    />
                )}
            </SideDrawerSection>

            {selectedOrderId && (
                <ManageOrderModal orderId={selectedOrderId} onClose={handleOrderClose}/>
            )}
        </SideDrawer>
    );
};
