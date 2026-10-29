import {ReactNode, useState} from "react";
import {Container, SegmentedControl, Skeleton, Stack, Table, Text, Title, Tooltip} from "@mantine/core";
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
    AdminSalesByCurrency,
} from "../../../../api/admin.client";
import {eventHomepageUrl} from "../../../../utilites/urlHelper";
import {formatCurrency} from "../../../../utilites/currency";
import {Event, IdParam} from "../../../../types";
import tableStyles from "../../../../styles/admin-table.module.scss";
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

const EventCell = ({event}: { event: { id: IdParam, slug: string, title: string, account_id: IdParam, account_name: string | null } }) => (
    <div className={classes.eventCell}>
        <a
            className={classes.eventLink}
            href={eventHomepageUrl({id: event.id, slug: event.slug} as Event)}
            target="_blank"
            rel="noopener noreferrer"
        >
            <Text span inherit lineClamp={2}>{event.title}</Text>
            <IconExternalLink size={12} className={classes.eventLinkIcon}/>
        </a>
        <div>
            <Link className={classes.subLink} to={`/admin/accounts/${event.account_id}`}>
                {event.account_name || '-'}
            </Link>
        </div>
    </div>
);

const DataTable = ({headers, rows, isLoading, emptyMessage}: {
    headers: { label: string, numeric?: boolean }[],
    rows: ReactNode[],
    isLoading: boolean,
    emptyMessage: string,
}) => {
    if (isLoading) {
        return <Skeleton height={240} radius="md"/>;
    }

    return (
        <div className={tableStyles.tableWrapper}>
            {rows.length === 0 ? (
                <div className={classes.empty}>{emptyMessage}</div>
            ) : (
                <div className={tableStyles.tableScroll}>
                    <Table className={`${tableStyles.table} ${classes.compactTable}`}>
                        <Table.Thead>
                            <Table.Tr>
                                {headers.map((header) => (
                                    <Table.Th key={header.label} className={header.numeric ? classes.numeric : undefined}>
                                        {header.label}
                                    </Table.Th>
                                ))}
                            </Table.Tr>
                        </Table.Thead>
                        <Table.Tbody>{rows}</Table.Tbody>
                    </Table>
                </div>
            )}
        </div>
    );
};

const CommissionCell = ({row, period}: { row: AdminCommissionByCurrency, period: AdminDashboardPeriod }) => (
    <Table.Td className={classes.numeric}>
        <Text size="sm" fw={600}>{formatCurrency(row[period].net, row.currency)}</Text>
        <Text size="xs" c="dimmed">{t`Gross`} {formatCurrency(row[period].gross, row.currency)}</Text>
    </Table.Td>
);

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

    const formatStart = (startDate: string, eventTimezone: string) => {
        const start = dayjs.utc(startDate);
        return `${start.fromNow()} · ${start.tz(eventTimezone).format('ddd MMM D, h:mma')}`;
    };

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
                        headers={[
                            {label: t`Currency`},
                            ...PERIODS.map((p) => ({label: periodLabel(p), numeric: true})),
                        ]}
                        rows={(data?.commission_by_currency ?? []).map((row) => (
                            <Table.Tr key={row.currency}>
                                <Table.Td><Text size="sm" fw={600}>{row.currency}</Text></Table.Td>
                                {PERIODS.map((p) => <CommissionCell key={p} row={row} period={p}/>)}
                            </Table.Tr>
                        ))}
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
                            headers={[
                                {label: t`Event`},
                                {label: t`Tickets`, numeric: true},
                                {label: t`Orders`, numeric: true},
                                {label: t`Sales`, numeric: true},
                            ]}
                            rows={(data?.top_selling_events ?? []).map((event) => (
                                <Table.Tr key={event.id}>
                                    <Table.Td><EventCell event={event}/></Table.Td>
                                    <Table.Td className={classes.numeric}>{formatNumber(event.tickets_sold)}</Table.Td>
                                    <Table.Td className={classes.numeric}>{formatNumber(event.orders_count)}</Table.Td>
                                    <Table.Td className={classes.numeric}>{formatCurrency(event.sales_total, event.currency)}</Table.Td>
                                </Table.Tr>
                            ))}
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
                            headers={[
                                {label: t`Event`},
                                {label: t`Views`, numeric: true},
                                {label: t`Orders`, numeric: true},
                                {label: t`Conversion`, numeric: true},
                            ]}
                            rows={(data?.most_viewed_events ?? []).map((event) => (
                                <Table.Tr key={event.id}>
                                    <Table.Td><EventCell event={event}/></Table.Td>
                                    <Table.Td className={classes.numeric}>{formatNumber(event.views)}</Table.Td>
                                    <Table.Td className={classes.numeric}>{formatNumber(event.orders_count)}</Table.Td>
                                    <Table.Td className={classes.numeric}>
                                        {`${((event.orders_count / event.views) * 100).toFixed(1)}%`}
                                    </Table.Td>
                                </Table.Tr>
                            ))}
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
                        headers={[
                            {label: t`Account`},
                            {label: t`Orders`, numeric: true},
                            {label: t`Commission`, numeric: true},
                        ]}
                        rows={topCommissionAccounts.map((account) => (
                            <Table.Tr key={`${account.id}-${account.currency}`}>
                                <Table.Td>
                                    <Link className={classes.eventLink} to={`/admin/accounts/${account.id}`}>
                                        {account.name}
                                    </Link>
                                    <Text size="xs" c="dimmed">{account.email}</Text>
                                </Table.Td>
                                <Table.Td className={classes.numeric}>{formatNumber(account.orders_count)}</Table.Td>
                                <Table.Td className={classes.numeric}>
                                    <Text size="sm" fw={600}>{formatCurrency(account.net, account.currency)}</Text>
                                    <Text size="xs" c="dimmed">{t`Gross`} {formatCurrency(account.gross, account.currency)}</Text>
                                </Table.Td>
                            </Table.Tr>
                        ))}
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
                        headers={[
                            {label: t`Event`},
                            {label: t`Starts`},
                            {label: t`Attendees`, numeric: true},
                            {label: t`Orders (24h)`, numeric: true},
                        ]}
                        rows={(data?.upcoming_events ?? []).map((event) => (
                            <Table.Tr key={event.occurrence_id}>
                                <Table.Td><EventCell event={event}/></Table.Td>
                                <Table.Td>
                                    <Text size="sm">{formatStart(event.start_date, event.timezone)}</Text>
                                </Table.Td>
                                <Table.Td className={classes.numeric}>
                                    {formatNumber(event.attendees_count)}
                                    {event.capacity ? ` / ${formatNumber(event.capacity)}` : ''}
                                </Table.Td>
                                <Table.Td className={classes.numeric}>{formatNumber(event.orders_last_24h)}</Table.Td>
                            </Table.Tr>
                        ))}
                    />
                </section>

                <section>
                    <div className={classes.sectionHeader}>
                        <div className={classes.sectionTitle}>{t`Recent signups`}</div>
                    </div>
                    <DataTable
                        isLoading={isLoading && !data}
                        emptyMessage={t`No recent account signups`}
                        headers={[
                            {label: t`Account`},
                            {label: t`Signed up`},
                            {label: t`Verified`},
                            {label: t`Stripe`},
                            {label: t`Events`, numeric: true},
                        ]}
                        rows={(data?.recent_accounts ?? []).map((account) => (
                            <Table.Tr key={account.id}>
                                <Table.Td>
                                    <Link className={classes.eventLink} to={`/admin/accounts/${account.id}`}>
                                        {account.name}
                                    </Link>
                                    <Text size="xs" c="dimmed">{account.email}</Text>
                                </Table.Td>
                                <Table.Td><Text size="sm">{dayjs.utc(account.created_at).fromNow()}</Text></Table.Td>
                                <Table.Td><Text size="sm">{account.account_verified_at ? t`Yes` : t`No`}</Text></Table.Td>
                                <Table.Td><Text size="sm">{account.stripe_connect_setup_complete ? t`Connected` : '-'}</Text></Table.Td>
                                <Table.Td className={classes.numeric}>{formatNumber(account.events_count)}</Table.Td>
                            </Table.Tr>
                        ))}
                    />
                </section>
            </Stack>
        </Container>
    );
};

export default AdminDashboard;
