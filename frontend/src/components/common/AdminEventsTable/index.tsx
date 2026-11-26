import {useMemo} from "react";
import {ActionIcon, Menu, Text, VisuallyHidden} from "@mantine/core";
import {t} from "@lingui/macro";
import {IconDotsVertical, IconEye, IconUserCheck} from "@tabler/icons-react";
import {AdminEvent} from "../../../api/admin.client";
import {IdParam} from "../../../types";
import {TanStackTable, TanStackTableColumn} from "../TanStackTable";
import {CellText, EmptyCell} from "../TanStackTable/cells.tsx";
import {sortableHeader, TableSort} from "../TanStackTable/sorting.tsx";
import {StatusBadge, StatusTone} from "../StatusBadge";
import tableStyles from "../../../styles/admin-table.module.scss";

interface AdminEventsTableProps {
    events: AdminEvent[];
    sort?: TableSort;
    onViewEvent?: (event: AdminEvent) => void;
    onImpersonate?: (userId: IdParam, accountId: IdParam) => void;
    isImpersonating?: boolean;
}

const STATUS_TONES: Record<string, StatusTone> = {
    LIVE: 'positive',
    DRAFT: 'waiting',
    ARCHIVED: 'neutral',
    PENDING_MANUAL_REVIEW: 'waiting',
};

const formatDate = (dateString?: string | null) => dateString
    ? new Date(dateString).toLocaleDateString()
    : <EmptyCell/>;

const formatSales = (amount: number) => new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
}).format(amount);

const formatNumber = (value: number) => new Intl.NumberFormat('en-US').format(value);

const AdminEventsTable = ({events, sort, onViewEvent, onImpersonate, isImpersonating}: AdminEventsTableProps) => {
    const columns = useMemo<TanStackTableColumn<AdminEvent>[]>(() => [
        {
            id: 'title',
            ...sortableHeader(t`Event Title`, 'title', sort),
            meta: {sticky: 'left'},
            cell: ({row: {original: event}}) => <CellText strong title={event.title}>{event.title}</CellText>,
        },
        {
            id: 'organizer',
            header: t`Organizer`,
            cell: ({row: {original: event}}) => (
                <CellText maxWidth={220} title={event.organizer_name}>{event.organizer_name}</CellText>
            ),
        },
        {
            id: 'start_date',
            ...sortableHeader(t`Start Date`, 'start_date', sort),
            cell: ({row: {original: event}}) => formatDate(event.start_date),
        },
        {
            id: 'end_date',
            ...sortableHeader(t`End Date`, 'end_date', sort),
            cell: ({row: {original: event}}) => formatDate(event.end_date),
        },
        {
            id: 'sales',
            header: t`Sales`,
            meta: {align: 'right'},
            cell: ({row: {original: event}}) => event.statistics
                ? <CellText strong>{formatSales(event.statistics.total_gross_sales)}</CellText>
                : <EmptyCell/>,
        },
        {
            id: 'attendees',
            header: t`Attendees`,
            meta: {align: 'right'},
            cell: ({row: {original: event}}) => event.statistics
                ? formatNumber(event.statistics.attendees_registered)
                : <EmptyCell/>,
        },
        {
            id: 'orders',
            header: t`Orders`,
            meta: {align: 'right'},
            cell: ({row: {original: event}}) => event.statistics
                ? formatNumber(event.statistics.orders_created)
                : <EmptyCell/>,
        },
        {
            id: 'status',
            header: t`Status`,
            cell: ({row: {original: event}}) => (
                <StatusBadge tone={STATUS_TONES[event.status.toUpperCase()] ?? 'active'}>{event.status}</StatusBadge>
            ),
        },
        {
            id: 'actions',
            header: () => <VisuallyHidden>{t`Actions`}</VisuallyHidden>,
            meta: {sticky: 'right'},
            cell: ({row: {original: event}}) => (
                <Menu shadow="md" width={200} position="bottom-end">
                    <Menu.Target>
                        <ActionIcon variant="subtle" color="gray" aria-label={t`Actions`}>
                            <IconDotsVertical size={18}/>
                        </ActionIcon>
                    </Menu.Target>
                    <Menu.Dropdown>
                        <Menu.Item leftSection={<IconEye size={14}/>} onClick={() => onViewEvent?.(event)}>
                            {t`View Event`}
                        </Menu.Item>
                        <Menu.Item
                            leftSection={<IconUserCheck size={14}/>}
                            onClick={() => onImpersonate?.(event.user_id, event.account_id)}
                            disabled={isImpersonating}
                        >
                            {t`Impersonate User`}
                        </Menu.Item>
                    </Menu.Dropdown>
                </Menu>
            ),
        },
    ], [sort?.sortBy, sort?.sortDirection, isImpersonating]);

    if (events.length === 0) {
        return (
            <div className={tableStyles.emptyState}>
                <Text size="lg" c="dimmed">{t`No events found`}</Text>
            </div>
        );
    }

    return <TanStackTable data={events} columns={columns} noCard/>;
};

export default AdminEventsTable;
