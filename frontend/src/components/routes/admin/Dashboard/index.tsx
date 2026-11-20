import {ReactNode, useMemo, useState} from "react";
import {Container, SegmentedControl, Skeleton, Stack, Text, Title, Tooltip} from "@mantine/core";
import {t} from "@lingui/macro";
import {Link} from "react-router";
import {IconExternalLink} from "@tabler/icons-react";
import dayjs from "dayjs";
import utc from "dayjs/plugin/utc";
import timezone from "dayjs/plugin/timezone";
import relativeTime from "dayjs/plugin/relativeTime";
import {useGetAdminStats} from "../../../../queries/useGetAdminStats";
import {useGetAdminDashboardData} from "../../../../queries/useGetAdminDashboardData";
import {
    AdminCommissionByCurrency,
    AdminDashboardAttention,
    AdminDashboardPeriod,
    AdminMostViewedEvent,
    AdminSalesByCurrency,
    AdminTopCommissionAccount,
    AdminTopSellingEvent,
    AdminUpcomingEvent,
    RecentAccount,
} from "../../../../api/admin.client";
import {eventHomepageUrl} from "../../../../utilites/urlHelper";
import {formatCurrency} from "../../../../utilites/currency";
import {Event} from "../../../../types";
import {TanStackTable, TanStackTableColumn} from "../../../common/TanStackTable";
import {CellText, EmptyCell} from "../../../common/TanStackTable/cells.tsx";
import {StatusBadge} from "../../../common/StatusBadge";
import classes from "./Dashboard.module.scss";

dayjs.extend(utc);
dayjs.extend(timezone);
dayjs.extend(relativeTime);

const PERIODS: AdminDashboardPeriod[] = ['24h', '7d', '30d'];

const periodLabel = (period: AdminDashboardPeriod) => ({
    '24h': t`Last 24 hours`,
    '7d': t`Last 7 days`,
    '30d': t`Last 30 days`,
})[period];

const formatNumber = (value: number) => new Intl.NumberFormat().format(value);

type DashboardEvent = Pick<AdminTopSellingEvent, 'id' | 'slug' | 'title' | 'account_id' | 'account_name'>;

const eventColumns = <TEvent extends DashboardEvent>(): TanStackTableColumn<TEvent>[] => [
    {
        id: 'event',
        header: t`Event`,
        meta: {sticky: 'left'},
        cell: ({row: {original: event}}) => (
            <a
                className={classes.eventLink}
                href={eventHomepageUrl({id: event.id, slug: event.slug} as Event)}
                target="_blank"
                rel="noopener noreferrer"
            >
                <CellText strong title={event.title}>{event.title}</CellText>
                <IconExternalLink size={12} className={classes.eventLinkIcon}/>
            </a>
        ),
    },
    {
        id: 'account',
        header: t`Account`,
        cell: ({row: {original: event}}) => (
            <Link className={classes.subLink} to={`/admin/accounts/${event.account_id}`}>
                {event.account_name || '-'}
            </Link>
        ),
    },
];

const accountColumns = <TAccount extends Pick<RecentAccount, 'id' | 'name' | 'email'>>(): TanStackTableColumn<TAccount>[] => [
    {
        id: 'account',
        header: t`Account`,
        meta: {sticky: 'left'},
        cell: ({row: {original: account}}) => (
            <Link className={classes.eventLink} to={`/admin/accounts/${account.id}`}>
                <CellText strong title={account.name}>{account.name}</CellText>
            </Link>
        ),
    },
    {
        id: 'email',
        header: t`Email`,
        cell: ({row: {original: account}}) => (
            <CellText muted maxWidth={260} title={account.email}>{account.email}</CellText>
        ),
    },
];

const Commission = ({net, gross, currency}: { net: number, gross: number, currency: string }) => (
    <span>
        <CellText strong>{formatCurrency(net, currency)}</CellText>
        {' '}
        <CellText muted>{t`Gross`} {formatCurrency(gross, currency)}</CellText>
    </span>
);

const formatStart = (startDate: string, eventTimezone: string) => {
    const start = dayjs.utc(startDate);
    return `${start.fromNow()} · ${start.tz(eventTimezone).format('ddd MMM D, h:mma')}`;
};

const DataTable = <TData,>({data, columns, isLoading, emptyMessage}: {
    data: TData[],
    columns: TanStackTableColumn<TData>[],
    isLoading: boolean,
    emptyMessage: string,
}) => {
    if (isLoading) {
        return <Skeleton height={240} radius="md"/>;
    }

    if (data.length === 0) {
        return <div className={classes.empty}>{emptyMessage}</div>;
    }

    return <TanStackTable data={data} columns={columns}/>;
};

const SalesValue = ({sales}: { sales: AdminSalesByCurrency[] }) => {
    if (sales.length === 0) {
        return <div className={classes.kpiValue}>-</div>;
    }

    const [first, ...rest] = sales;

    return (
        <>
            <div className={classes.kpiValue}>{formatCurrency(first.total, first.currency)}</div>
            {rest.slice(0, 2).map((row) => (
                <div key={row.currency} className={classes.kpiSubValue}>{formatCurrency(row.total, row.currency)}</div>
            ))}
            {rest.length > 2 && (
                <Tooltip
                    multiline
                    label={rest.slice(2).map((row) => formatCurrency(row.total, row.currency)).join(' · ')}
                >
                    <Text size="xs" c="dimmed">{t`+${rest.length - 2} more currencies`}</Text>
                </Tooltip>
            )}
        </>
    );
};

const AttentionRow = ({attention}: { attention: AdminDashboardAttention }) => {
    const items = [
        {count: attention.flagged_events, label: t`Flagged events`, to: '/admin/spam-events'},
        {count: attention.messages_pending_review, label: t`Messages to approve`, to: '/admin/messages'},
        {count: attention.failed_jobs, label: t`Failed jobs`, to: '/admin/failed-jobs'},
        {count: attention.pending_deletion_requests, label: t`Pending deletions`, to: '/admin/deletion-requests'},
    ];

    return (
        <div className={classes.attentionRow}>
            {items.map((item) => (
                <Link
                    key={item.to}
                    to={item.to}
                    className={`${classes.attentionItem} ${item.count > 0 ? classes.attentionActive : ''}`}
                    data-testid="admin-dashboard-attention-item"
                >
                    <span className={classes.attentionCount}>{formatNumber(item.count)}</span>
                    <span>{item.label}</span>
                </Link>
            ))}
        </div>
    );
};

const Kpi = ({label, children}: { label: string, children: ReactNode }) => (
    <div className={classes.kpi}>
        <div className={classes.kpiLabel}>{label}</div>
        {children}
    </div>
);

const AdminDashboard = () => {
    const [period, setPeriod] = useState<AdminDashboardPeriod>('7d');
    const {data: stats} = useGetAdminStats();
    const {data, isLoading} = useGetAdminDashboardData(period);
    const [commissionCurrency, setCommissionCurrency] = useState<string | null>(null);

    const commissionAccounts = data?.top_accounts_by_commission ?? [];
    const currencyOrder = (data?.commission_by_currency ?? []).map((row) => row.currency);
    const commissionCurrencies = [...new Set(commissionAccounts.map((account) => account.currency))]
        .sort((a, b) => currencyOrder.indexOf(a) - currencyOrder.indexOf(b));
    const activeCommissionCurrency = commissionCurrency && commissionCurrencies.includes(commissionCurrency)
        ? commissionCurrency
        : commissionCurrencies[0] ?? '';
    const topCommissionAccounts = commissionAccounts.filter((account) => account.currency === activeCommissionCurrency);

    const commissionColumns = useMemo<TanStackTableColumn<AdminCommissionByCurrency>[]>(() => [
        {
            id: 'currency',
            header: t`Currency`,
            meta: {sticky: 'left'},
            cell: ({row: {original: row}}) => <CellText strong>{row.currency}</CellText>,
        },
        ...PERIODS.map((p): TanStackTableColumn<AdminCommissionByCurrency> => ({
            id: p,
            header: periodLabel(p),
            meta: {align: 'right'},
            cell: ({row: {original: row}}) => (
                <Commission net={row[p].net} gross={row[p].gross} currency={row.currency}/>
            ),
        })),
    ], []);

    const topSellingColumns = useMemo<TanStackTableColumn<AdminTopSellingEvent>[]>(() => [
        ...eventColumns<AdminTopSellingEvent>(),
        {
            id: 'tickets',
            header: t`Tickets`,
            meta: {align: 'right'},
            cell: ({row: {original: event}}) => formatNumber(event.tickets_sold),
        },
        {
            id: 'orders',
            header: t`Orders`,
            meta: {align: 'right'},
            cell: ({row: {original: event}}) => formatNumber(event.orders_count),
        },
        {
            id: 'sales',
            header: t`Sales`,
            meta: {align: 'right'},
            cell: ({row: {original: event}}) => formatCurrency(event.sales_total, event.currency),
        },
    ], []);

    const mostViewedColumns = useMemo<TanStackTableColumn<AdminMostViewedEvent>[]>(() => [
        ...eventColumns<AdminMostViewedEvent>(),
        {
            id: 'views',
            header: t`Views`,
            meta: {align: 'right'},
            cell: ({row: {original: event}}) => formatNumber(event.views),
        },
        {
            id: 'orders',
            header: t`Orders`,
            meta: {align: 'right'},
            cell: ({row: {original: event}}) => formatNumber(event.orders_count),
        },
        {
            id: 'conversion',
            header: t`Conversion`,
            meta: {align: 'right'},
            cell: ({row: {original: event}}) => `${((event.orders_count / event.views) * 100).toFixed(1)}%`,
        },
    ], []);

    const topAccountColumns = useMemo<TanStackTableColumn<AdminTopCommissionAccount>[]>(() => [
        ...accountColumns<AdminTopCommissionAccount>(),
        {
            id: 'orders',
            header: t`Orders`,
            meta: {align: 'right'},
            cell: ({row: {original: account}}) => formatNumber(account.orders_count),
        },
        {
            id: 'commission',
            header: t`Commission`,
            meta: {align: 'right'},
            cell: ({row: {original: account}}) => (
                <Commission net={account.net} gross={account.gross} currency={account.currency}/>
            ),
        },
    ], []);

    const upcomingColumns = useMemo<TanStackTableColumn<AdminUpcomingEvent>[]>(() => [
        ...eventColumns<AdminUpcomingEvent>(),
        {
            id: 'starts',
            header: t`Starts`,
            cell: ({row: {original: event}}) => formatStart(event.start_date, event.timezone),
        },
        {
            id: 'attendees',
            header: t`Attendees`,
            meta: {align: 'right'},
            cell: ({row: {original: event}}) => (
                `${formatNumber(event.attendees_count)}${event.capacity ? ` / ${formatNumber(event.capacity)}` : ''}`
            ),
        },
        {
            id: 'orders_last_24h',
            header: t`Orders (24h)`,
            meta: {align: 'right'},
            cell: ({row: {original: event}}) => formatNumber(event.orders_last_24h),
        },
    ], []);

    const recentAccountColumns = useMemo<TanStackTableColumn<RecentAccount>[]>(() => [
        ...accountColumns<RecentAccount>(),
        {
            id: 'signed_up',
            header: t`Signed up`,
            cell: ({row: {original: account}}) => dayjs.utc(account.created_at).fromNow(),
        },
        {
            id: 'verified',
            header: t`Verified`,
            cell: ({row: {original: account}}) => account.account_verified_at
                ? <StatusBadge tone="positive">{t`Yes`}</StatusBadge>
                : <StatusBadge tone="neutral">{t`No`}</StatusBadge>,
        },
        {
            id: 'stripe',
            header: t`Stripe`,
            cell: ({row: {original: account}}) => account.stripe_connect_setup_complete
                ? <StatusBadge tone="positive">{t`Connected`}</StatusBadge>
                : <EmptyCell/>,
        },
        {
            id: 'events',
            header: t`Events`,
            meta: {align: 'right'},
            cell: ({row: {original: account}}) => formatNumber(account.events_count),
        },
    ], []);

    return (
        <Container size="xl" className={classes.container}>
            <Stack gap={32}>
                <div className={classes.header}>
                    <Title order={1}>{t`Dashboard`}</Title>
                    {stats && (
                        <div className={classes.totals}>
                            <span><strong>{formatNumber(stats.total_users)}</strong> {t`users`}</span>
                            <span><strong>{formatNumber(stats.total_accounts)}</strong> {t`accounts`}</span>
                            <span><strong>{formatNumber(stats.total_live_events)}</strong> {t`live events`}</span>
                            <span><strong>{formatNumber(stats.total_tickets_sold)}</strong> {t`tickets sold all time`}</span>
                        </div>
                    )}
                </div>

                {data?.attention && <AttentionRow attention={data.attention}/>}

                <section>
                    <div className={classes.sectionHeader}>
                        <div>
                            <div className={classes.sectionTitle}>{t`Platform commission`}</div>
                            <Text size="xs" c="dimmed">
                                {t`Stripe application fees by currency, net of VAT. Refunded fees are not deducted.`}
                            </Text>
                        </div>
                    </div>
                    <DataTable
                        isLoading={isLoading && !data}
                        emptyMessage={t`No commission collected in the last 30 days`}
                        data={data?.commission_by_currency ?? []}
                        columns={commissionColumns}
                    />
                </section>

                <section>
                    <div className={classes.sectionHeader}>
                        <div className={classes.sectionTitle}>{t`Activity`}</div>
                        <SegmentedControl
                            size="xs"
                            value={period}
                            onChange={(value) => setPeriod(value as AdminDashboardPeriod)}
                            data={PERIODS.map((p) => ({value: p, label: periodLabel(p)}))}
                            data-testid="admin-dashboard-period"
                        />
                    </div>

                    {isLoading && !data ? (
                        <Skeleton height={90} radius="md"/>
                    ) : (
                        <div className={classes.kpiStrip}>
                            <Kpi label={t`Sales after refunds`}>
                                <SalesValue sales={data?.sales_by_currency ?? []}/>
                            </Kpi>
                            <Kpi label={t`Orders`}>
                                <div className={classes.kpiValue}>{formatNumber(data?.orders_count ?? 0)}</div>
                            </Kpi>
                            <Kpi label={t`Tickets sold`}>
                                <div className={classes.kpiValue}>{formatNumber(data?.tickets_sold ?? 0)}</div>
                            </Kpi>
                            <Kpi label={t`New accounts`}>
                                <div className={classes.kpiValue}>{formatNumber(data?.signups_count ?? 0)}</div>
                            </Kpi>
                            <Kpi label={t`New events`}>
                                <div className={classes.kpiValue}>{formatNumber(data?.events_created_count ?? 0)}</div>
                            </Kpi>
                        </div>
                    )}
                </section>

                <div className={classes.grid}>
                    <section>
                        <div className={classes.sectionHeader}>
                            <div className={classes.sectionTitle}>{t`Top selling events`}</div>
                            <Text size="xs" c="dimmed">{periodLabel(period)}</Text>
                        </div>
                        <DataTable
                            isLoading={isLoading && !data}
                            emptyMessage={t`No completed orders in this period`}
                            data={data?.top_selling_events ?? []}
                            columns={topSellingColumns}
                        />
                    </section>

                    <section>
                        <div className={classes.sectionHeader}>
                            <div className={classes.sectionTitle}>{t`Most viewed events`}</div>
                            {data?.views_since && (
                                <Text size="xs" c="dimmed">
                                    {t`Since ${dayjs.utc(data.views_since).format('MMM D')} (UTC)`}
                                </Text>
                            )}
                        </div>
                        <DataTable
                            isLoading={isLoading && !data}
                            emptyMessage={t`No event page views in this period`}
                            data={data?.most_viewed_events ?? []}
                            columns={mostViewedColumns}
                        />
                    </section>
                </div>

                <section>
                    <div className={classes.sectionHeader}>
                        <div>
                            <div className={classes.sectionTitle}>{t`Top accounts by commission`}</div>
                            <Text size="xs" c="dimmed">{periodLabel(period)}</Text>
                        </div>
                        {commissionCurrencies.length > 1 && (
                            <SegmentedControl
                                size="xs"
                                value={activeCommissionCurrency}
                                onChange={setCommissionCurrency}
                                data={commissionCurrencies}
                                data-testid="admin-dashboard-commission-currency"
                            />
                        )}
                    </div>
                    <DataTable
                        isLoading={isLoading && !data}
                        emptyMessage={t`No commission collected in this period`}
                        data={topCommissionAccounts}
                        columns={topAccountColumns}
                    />
                </section>

                <section>
                    <div className={classes.sectionHeader}>
                        <div>
                            <div className={classes.sectionTitle}>{t`Upcoming events by size`}</div>
                            <Text size="xs" c="dimmed">
                                {t`Live dates starting in the next 7 days, largest first. Expect check-in and page traffic around these.`}
                            </Text>
                        </div>
                    </div>
                    <DataTable
                        isLoading={isLoading && !data}
                        emptyMessage={t`No live events starting in the next 7 days`}
                        data={data?.upcoming_events ?? []}
                        columns={upcomingColumns}
                    />
                </section>

                <section>
                    <div className={classes.sectionHeader}>
                        <div className={classes.sectionTitle}>{t`Recent signups`}</div>
                    </div>
                    <DataTable
                        isLoading={isLoading && !data}
                        emptyMessage={t`No recent account signups`}
                        data={data?.recent_accounts ?? []}
                        columns={recentAccountColumns}
                    />
                </section>
            </Stack>
        </Container>
    );
};

export default AdminDashboard;
