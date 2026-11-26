import {useMemo} from "react";
import {Text} from "@mantine/core";
import {t} from "@lingui/macro";
import {AdminOrder} from "../../../api/admin.client";
import {formatCurrency} from "../../../utilites/currency";
import {prettyDate} from "../../../utilites/dates";
import {TanStackTable, TanStackTableColumn} from "../TanStackTable";
import {CellText, IdentityCell} from "../TanStackTable/cells.tsx";
import {sortableHeader, TableSort} from "../TanStackTable/sorting.tsx";
import {StatusBadge, StatusTone} from "../StatusBadge";
import tableStyles from "../../../styles/admin-table.module.scss";

interface AdminOrdersTableProps {
    orders: AdminOrder[];
    sort?: TableSort;
}

const STATUS_TONES: Record<string, StatusTone> = {
    COMPLETED: 'positive',
    PENDING: 'waiting',
    RESERVED: 'waiting',
    CANCELLED: 'negative',
    REFUNDED: 'active',
    PARTIALLY_REFUNDED: 'active',
};

const AdminOrdersTable = ({orders, sort}: AdminOrdersTableProps) => {
    const columns = useMemo<TanStackTableColumn<AdminOrder>[]>(() => [
        {
            id: 'short_id',
            ...sortableHeader(t`Order ID`, 'short_id', sort),
            meta: {sticky: 'left'},
            cell: ({row: {original: order}}) => <CellText mono strong>#{order.short_id}</CellText>,
        },
        {
            id: 'account',
            header: t`Account`,
            cell: ({row: {original: order}}) => (
                <CellText maxWidth={200} title={order.account_name}>{order.account_name}</CellText>
            ),
        },
        {
            id: 'customer',
            header: t`Customer`,
            cell: ({row: {original: order}}) => (
                <IdentityCell
                    stacked
                    primary={<CellText strong maxWidth={240}>{order.first_name} {order.last_name}</CellText>}
                    secondary={order.email ?? t`No email provided`}
                />
            ),
        },
        {
            id: 'event',
            header: t`Event`,
            cell: ({row: {original: order}}) => (
                <CellText maxWidth={240} title={order.event_title}>{order.event_title}</CellText>
            ),
        },
        {
            id: 'total_gross',
            ...sortableHeader(t`Total`, 'total_gross', sort),
            meta: {align: 'right'},
            cell: ({row: {original: order}}) => (
                <CellText strong>{formatCurrency(order.total_gross, order.currency)}</CellText>
            ),
        },
        {
            id: 'total_tax',
            header: t`Tax`,
            meta: {align: 'right'},
            cell: ({row: {original: order}}) => formatCurrency(order.total_tax, order.currency),
        },
        {
            id: 'status',
            header: t`Status`,
            cell: ({row: {original: order}}) => (
                <StatusBadge tone={STATUS_TONES[order.status.toUpperCase()] ?? 'neutral'}>{order.status}</StatusBadge>
            ),
        },
        {
            id: 'created_at',
            ...sortableHeader(t`Date`, 'created_at', sort),
            cell: ({row: {original: order}}) => prettyDate(order.created_at, 'UTC'),
        },
    ], [sort?.sortBy, sort?.sortDirection]);

    if (orders.length === 0) {
        return (
            <div className={tableStyles.emptyState}>
                <Text size="lg" c="dimmed">{t`No orders found`}</Text>
            </div>
        );
    }

    return <TanStackTable rowLines={2} data={orders} columns={columns} noCard/>;
};

export default AdminOrdersTable;
