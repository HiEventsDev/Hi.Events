import {t} from "@lingui/macro";
import {useAccess} from "../../../hooks/useAccess.ts";
import {ActionIcon, Button, Group, Menu, Popover, Tooltip, VisuallyHidden} from '@mantine/core';
import {Event, EventType, IdParam, Invoice, Order} from "../../../types.ts";
import {IconCash, IconCashRegister, IconCopy, IconCreditCard, IconDotsVertical, IconHelp, IconSend} from "@tabler/icons-react";
import {formatDateWithLocale, relativeDate} from "../../../utilites/dates.ts";
import {ManageOrderModal} from "../../modals/ManageOrderModal";
import {useClipboard, useDisclosure} from "@mantine/hooks";
import {useMemo, useState} from "react";
import {NoResultsSplash} from "../NoResultsSplash";
import classes from "./OrdersTable.module.scss";
import {formatNumber} from "../../../utilites/helpers.ts";
import {useUrlHash} from "../../../hooks/useUrlHash.ts";
import {useOrderActions} from "../../../hooks/useOrderActions.tsx";
import {EntityActionMenuItems} from "../EntityActions";
import {showSuccess} from "../../../utilites/notifications.tsx";
import {TanStackTable, TanStackTableColumn} from "../TanStackTable";
import {CellCopy, CellLink, CellText, EmptyCell, IdentityCell} from "../TanStackTable/cells.tsx";
import {sortableHeader, TableSort} from "../TanStackTable/sorting.tsx";
import {StatusBadge, StatusTone} from "../StatusBadge";
import {CellContext} from "@tanstack/react-table";
import {formatCurrency} from "../../../utilites/currency.ts";
import {getBoxOfficeTenderLabel} from "../../../ee/box-office/utilites/boxOfficeTender.ts";
import {WriteAccess} from "../WriteAccess";

interface OrdersTableProps {
    event: Event,
    orders: Order[];
    compact?: boolean;
    embedded?: boolean;
    isRefreshing?: boolean;
    sort?: TableSort;
    columnToggleSlot?: HTMLElement | null;
}

const DEFAULT_COLUMN_VISIBILITY = {invoice: false};

const ORDER_STATUS_TONES: Record<string, StatusTone> = {
    COMPLETED: 'positive',
    RESERVED: 'active',
    AWAITING_OFFLINE_PAYMENT: 'waiting',
    CANCELLED: 'negative',
    ABANDONED: 'neutral',
};

const OrderStatus = ({order}: { order: Order }) => {
    const isPaymentReported = order.status === 'AWAITING_OFFLINE_PAYMENT' && !!order.offline_payment_reported_at;
    const labels: Record<string, string> = {
        COMPLETED: t`Completed`,
        RESERVED: t`Reserved`,
        AWAITING_OFFLINE_PAYMENT: isPaymentReported ? t`Payment reported` : t`Awaiting Payment`,
        CANCELLED: t`Cancelled`,
        ABANDONED: t`Abandoned`,
    };
    const reservedUntil = order.status === 'RESERVED' && order.reserved_until
        ? new Date(order.reserved_until).toLocaleTimeString([], {hour: 'numeric', minute: '2-digit'})
        : null;

    return (
        <Tooltip label={`${t`Reserved until`} ${reservedUntil}`} disabled={!reservedUntil} withArrow>
            <span>
                <StatusBadge tone={isPaymentReported ? 'active' : ORDER_STATUS_TONES[order.status] ?? 'neutral'}>
                    {labels[order.status] ?? order.status}
                </StatusBadge>
            </span>
        </Tooltip>
    );
};

const RefundStatus = ({order}: { order: Order }) => {
    if (!order.refund_status) {
        return null;
    }
    const hasAmount = order.total_refunded != null;
    const labels: Record<string, string> = {
        REFUNDED: hasAmount ? t`Refunded: ${formatCurrency(order.total_refunded, order.currency)}` : t`Refunded`,
        PARTIALLY_REFUNDED: hasAmount ? t`Partially refunded: ${formatCurrency(order.total_refunded, order.currency)}` : t`Partially refunded`,
        REFUND_PENDING: t`Refund pending`,
        REFUND_FAILED: t`Refund failed`,
    };

    return (
        <StatusBadge tone={order.refund_status === 'REFUND_FAILED' ? 'negative' : 'waiting'}>
            {labels[order.refund_status] ?? order.refund_status}
        </StatusBadge>
    );
};

export const OrdersTable = ({orders, event, compact, embedded, isRefreshing, sort, columnToggleSlot}: OrdersTableProps) => {
    const [isViewModalOpen, viewModal] = useDisclosure(false);
    const [orderId, setOrderId] = useState<IdParam>();
    const [emailPopoverId, setEmailPopoverId] = useState<IdParam | null>(null);
    const clipboard = useClipboard({timeout: 2000});
    const {getOrderActions, orderActionModals, openMessageModal, downloadInvoice} = useOrderActions({
        eventId: event.id,
        onManage: (order: Order) => handleModalClick(order.id, viewModal),
    });

    useUrlHash(/^#order-(\d+)$/, (matches => {
        const orderId = matches![1];
        setOrderId(orderId);
        viewModal.open();
    }));

    const handleModalClick = (orderId: IdParam, modal: { open: () => void }) => {
        setOrderId(orderId);
        modal.open();
    }

    const handleCopyEmail = (email: string | null) => {
        if (!email) {
            return;
        }
        clipboard.copy(email);
        showSuccess(t`Email address copied to clipboard`);
        setEmailPopoverId(null);
    };

    const handleMessageFromEmail = (order: Order) => {
        setEmailPopoverId(null);
        openMessageModal(order);
    };

    const ActionMenu = ({order}: { order: Order }) => (
        <Menu shadow="md" width={200} position="bottom-end">
            <Menu.Target>
                <ActionIcon variant="subtle" color="gray" aria-label={t`Actions`} data-testid="order-actions-trigger">
                    <IconDotsVertical size={18}/>
                </ActionIcon>
            </Menu.Target>

            <Menu.Dropdown>
                <EntityActionMenuItems actions={getOrderActions(order)}/>
            </Menu.Dropdown>
        </Menu>
    );

    const canViewFinancials = useAccess().can('VIEW_FINANCIALS');
    const openOrder = (order: Order) => handleModalClick(order.id, viewModal);
    const hasOccurrences = event.type === EventType.RECURRING && orders.some(order => order.order_items?.[0]?.event_occurrence);
    const hasBoxOfficeOrders = orders.some(order => order.box_office_id);

    const columns = useMemo<TanStackTableColumn<Order>[]>(
        () => {
            const renderEmail = (order: Order) => {
                if (!order.email) {
                    return <CellText muted>{t`No email provided`}</CellText>;
                }

                return (
                    <Popover
                        opened={emailPopoverId === order.id}
                        onChange={(opened) => {
                            if (!opened) setEmailPopoverId(null);
                        }}
                        width={200}
                        position="bottom-start"
                        shadow="md"
                    >
                        <Popover.Target>
                            <span className={classes.emailTarget}>
                                <CellLink onClick={() => setEmailPopoverId(order.id)} title={order.email}>
                                    {order.email}
                                </CellLink>
                            </span>
                        </Popover.Target>
                        <Popover.Dropdown>
                            <Group gap="xs" style={{flexDirection: 'column', width: '100%'}}>
                                <WriteAccess>
                                    <Button
                                        fullWidth
                                        variant="light"
                                        leftSection={<IconSend size={16}/>}
                                        onClick={() => handleMessageFromEmail(order)}
                                    >
                                        {t`Message`}
                                    </Button>
                                </WriteAccess>
                                <Button
                                    fullWidth
                                    variant="light"
                                    color="gray"
                                    leftSection={<IconCopy size={16}/>}
                                    onClick={() => handleCopyEmail(order.email)}
                                >
                                    {t`Copy Email`}
                                </Button>
                            </Group>
                        </Popover.Dropdown>
                    </Popover>
                );
            };

            const allColumns: (TanStackTableColumn<Order> | false)[] = [
                {
                    id: 'customer',
                    enableHiding: false,
                    ...sortableHeader(t`Customer`, 'first_name', sort, 'asc'),
                    meta: {label: t`Customer`, sticky: 'left', interactive: true},
                    cell: ({row: {original: order}}: CellContext<Order, unknown>) => {
                        const name = [order.first_name, order.last_name].filter(Boolean).join(' ');

                        return (
                            <IdentityCell
                                stacked
                                primary={(
                                    <>
                                        <CellLink strong onClick={() => openOrder(order)} title={name || undefined}>
                                            {name || order.public_id}
                                        </CellLink>
                                        {order.company_name && <span className={classes.company}>{order.company_name}</span>}
                                    </>
                                )}
                                secondary={renderEmail(order)}
                            />
                        );
                    },
                },
                {
                    id: 'status',
                    header: t`Status`,
                    cell: ({row: {original: order}}: CellContext<Order, unknown>) => (
                        <span className={classes.badges}>
                            <OrderStatus order={order}/>
                            <RefundStatus order={order}/>
                        </span>
                    ),
                },
                canViewFinancials && {
                    id: 'amount',
                    ...sortableHeader(t`Amount`, 'total_gross', sort),
                    meta: {label: t`Amount`, align: 'right'},
                    cell: ({row: {original: order}}: CellContext<Order, unknown>) => (
                        <Tooltip
                            label={`${t`Tax`}: ${formatCurrency(order.total_tax, order.currency)} · ${t`Fees`}: ${formatCurrency(order.total_fee, order.currency)}`}
                            withArrow
                            openDelay={300}
                        >
                            <span className={classes.amount}>{formatCurrency(order.total_gross, order.currency)}</span>
                        </Tooltip>
                    ),
                },
                {
                    id: 'items',
                    header: t`Items`,
                    cell: ({row: {original: order}}: CellContext<Order, unknown>) => {
                        const items = order.order_items ?? [];
                        if (items.length === 0) {
                            return <EmptyCell/>;
                        }
                        const [first, ...rest] = items;

                        return (
                            <Tooltip
                                label={items.map(item => `${item.quantity} × ${item.item_name}`).join('\n')}
                                multiline
                                withArrow
                                openDelay={300}
                                style={{whiteSpace: 'pre-line'}}
                            >
                                <span className={classes.items}>
                                    <CellText maxWidth={220}>{formatNumber(first.quantity)} × {first.item_name}</CellText>
                                    {rest.length > 0 && <span className={classes.more}>+{rest.length}</span>}
                                </span>
                            </Tooltip>
                        );
                    },
                },
                hasOccurrences && {
                    id: 'occurrence',
                    header: t`Date`,
                    cell: ({row: {original: order}}: CellContext<Order, unknown>) => {
                        const occurrence = order.order_items?.[0]?.event_occurrence;
                        if (!occurrence || !event.timezone) {
                            return <EmptyCell/>;
                        }

                        return (
                            <CellText maxWidth={240}>
                                {formatDateWithLocale(occurrence.start_date, 'shortDate', event.timezone)}
                                {' '}
                                {formatDateWithLocale(occurrence.start_date, 'timeOnly', event.timezone)}
                                {occurrence.label && ` · ${occurrence.label}`}
                            </CellText>
                        );
                    },
                },
                {
                    id: 'order',
                    ...sortableHeader(t`Order`, 'public_id', sort, 'asc'),
                    meta: {label: t`Order`},
                    cell: ({row: {original: order}}: CellContext<Order, unknown>) => (
                        <CellCopy value={order.public_id}>
                            <CellLink mono onClick={() => openOrder(order)}>{order.public_id}</CellLink>
                        </CellCopy>
                    ),
                },
                {
                    id: 'payment',
                    header: t`Payment`,
                    cell: ({row: {original: order}}: CellContext<Order, unknown>) => {
                        const tenderLabel = getBoxOfficeTenderLabel(order.box_office_tender);
                        const isCard = tenderLabel ? order.box_office_tender === 'CARD' : order.payment_provider === 'STRIPE';
                        const isCash = !!tenderLabel || order.payment_provider === 'OFFLINE';
                        const Icon = isCard ? IconCreditCard : isCash ? IconCash : IconHelp;
                        const label = tenderLabel
                            ?? (order.payment_provider === 'STRIPE' ? t`Stripe` : order.payment_provider === 'OFFLINE' ? t`Offline` : t`Other`);

                        return <span className={classes.payment}><Icon size={15} stroke={1.6}/>{label}</span>;
                    },
                },
                hasBoxOfficeOrders && {
                    id: 'boxOffice',
                    header: t`Box office`,
                    cell: ({row: {original: order}}: CellContext<Order, unknown>) => {
                        const operatorName = order.box_office_operator_name;
                        if (!order.box_office_id) {
                            return <EmptyCell/>;
                        }

                        return (
                            <span className={classes.payment}>
                                <IconCashRegister size={15} stroke={1.6}/>
                                {operatorName ? t`Sold by ${operatorName}` : t`Box office`}
                            </span>
                        );
                    },
                },
                {
                    id: 'invoice',
                    header: t`Invoice`,
                    meta: {interactive: true},
                    cell: ({row: {original: order}}: CellContext<Order, unknown>) => order.latest_invoice ? (
                        <CellLink mono onClick={() => downloadInvoice(order.latest_invoice as Invoice)}>
                            #{order.latest_invoice.invoice_number}
                        </CellLink>
                    ) : <EmptyCell/>,
                },
                {
                    id: 'created',
                    ...sortableHeader(t`Created`, 'created_at', sort),
                    meta: {label: t`Created`},
                    cell: ({row: {original: order}}: CellContext<Order, unknown>) => (
                        <Tooltip
                            label={event.timezone ? formatDateWithLocale(order.created_at, 'fullDateTime', event.timezone) : undefined}
                            disabled={!event.timezone}
                            withArrow
                            openDelay={300}
                        >
                            <span>{relativeDate(order.created_at)}</span>
                        </Tooltip>
                    ),
                },
                {
                    id: 'actions',
                    header: () => <VisuallyHidden>{t`Actions`}</VisuallyHidden>,
                    enableHiding: false,
                    meta: {sticky: 'right'},
                    cell: ({row: {original: order}}: CellContext<Order, unknown>) => <ActionMenu order={order}/>,
                },
            ];

            return allColumns.filter(Boolean) as TanStackTableColumn<Order>[];
        },
        [event.id, event.timezone, emailPopoverId, canViewFinancials, hasOccurrences, hasBoxOfficeOrders, sort?.sortBy, sort?.sortDirection]
    );

    if (orders.length === 0) {
        return <NoResultsSplash
            imageHref={'/blank-slate/orders.svg'}
            heading={t`No orders to show`}
            subHeading={(
                <p>
                    {t`Your orders will appear here once they start rolling in.`}
                </p>
            )}
        />
    }

    return (
        <>
            <TanStackTable
                data={orders}
                columns={columns}
                storageKey="orders-list"
                enableColumnVisibility={!compact}
                defaultColumnVisibility={DEFAULT_COLUMN_VISIBILITY}
                columnToggleSlot={columnToggleSlot}
                hideHeader={compact}
                noCard={compact || embedded}
                isRefreshing={isRefreshing}
                rowLines={2}
                onRowClick={openOrder}
            />
            {orderId && isViewModalOpen && <ManageOrderModal onClose={viewModal.close} orderId={orderId}/>}
            {orderActionModals}
        </>
    )
};
