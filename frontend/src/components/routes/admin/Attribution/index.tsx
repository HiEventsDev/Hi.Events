import {Container, Title, Text, Paper, Stack, SimpleGrid, SegmentedControl, Skeleton, Pagination, Group, Select} from "@mantine/core";
import {DatePickerInput} from "@mantine/dates";
import {IconCalendar} from "@tabler/icons-react";
import {t, Trans} from "@lingui/macro";
import dayjs from "dayjs";
import utc from "dayjs/plugin/utc";
import {useGetUtmAttributionStats} from "../../../../queries/useGetUtmAttributionStats";
import {AttributionGroupBy, UtmAttributionStats} from "../../../../api/admin.client";
import {useMemo, useState} from "react";
import {formatCurrency} from "../../../../utilites/currency";
import {TanStackTable, TanStackTableColumn} from "../../../common/TanStackTable";
import {CellText, EmptyCell} from "../../../common/TanStackTable/cells.tsx";
import {TableCard} from "../../../common/TableCard";
import tableStyles from "../../../../styles/admin-table.module.scss";

dayjs.extend(utc);

type Period = '24h' | '7d' | '30d' | '90d' | 'all' | 'custom';

const PERIOD_STARTS: Record<Exclude<Period, 'all' | 'custom'>, () => dayjs.Dayjs> = {
    '24h': () => dayjs().subtract(24, 'hour'),
    '7d': () => dayjs().subtract(7, 'day'),
    '30d': () => dayjs().subtract(30, 'day'),
    '90d': () => dayjs().subtract(90, 'day'),
};

const toUtcDateTime = (date: dayjs.Dayjs) => date.utc().format('YYYY-MM-DD HH:mm:ss');

const RevenueByCurrency = ({revenue}: { revenue: Record<string, number> }) => {
    const entries = Object.entries(revenue);

    if (entries.length === 0) {
        return <EmptyCell/>;
    }

    return (
        <CellText strong>
            {entries.map(([currency, amount]) => formatCurrency(amount, currency)).join(' · ')}
        </CellText>
    );
};

const Attribution = () => {
    const [groupBy, setGroupBy] = useState<AttributionGroupBy>('source');
    const [period, setPeriod] = useState<Period>('30d');
    const [customRange, setCustomRange] = useState<[Date | null, Date | null]>([null, null]);
    const [page, setPage] = useState(1);
    const perPage = 20;

    const dateFilter = useMemo(() => {
        if (period === 'all') {
            return {};
        }

        if (period === 'custom') {
            const [from, to] = customRange;
            return from && to
                ? {date_from: toUtcDateTime(dayjs(from).startOf('day')), date_to: toUtcDateTime(dayjs(to).endOf('day'))}
                : {};
        }

        return {date_from: toUtcDateTime(PERIOD_STARTS[period]())};
    }, [period, customRange]);

    const {data, isLoading} = useGetUtmAttributionStats({
        group_by: groupBy,
        page,
        per_page: perPage,
        ...dateFilter,
    });

    const summary = data?.summary;
    const paginatedData = data?.data;
    const stats = paginatedData?.data || [];
    const totalPages = paginatedData?.last_page || 1;

    const periods = [
        {value: '24h', label: t`Last 24 hours`},
        {value: '7d', label: t`Last 7 days`},
        {value: '30d', label: t`Last 30 days`},
        {value: '90d', label: t`Last 90 days`},
        {value: 'all', label: t`All time`},
        {value: 'custom', label: t`Custom Range`},
    ];

    const summaryCards = summary ? [
        {label: t`Paid Accounts`, value: summary.paid_accounts},
        {label: t`Organic Accounts`, value: summary.organic_accounts},
        {label: t`Referral Accounts`, value: summary.referral_accounts},
        {label: t`Unattributed Accounts`, value: summary.unattributed_accounts},
    ] : [];

    const columns = useMemo<TanStackTableColumn<UtmAttributionStats>[]>(() => [
        {
            id: 'attribution_value',
            header: t`Attribution Value`,
            meta: {sticky: 'left'},
            cell: ({row: {original: stat}}) => (
                <CellText strong title={stat.attribution_value}>{stat.attribution_value || t`(empty)`}</CellText>
            ),
        },
        {
            id: 'accounts',
            header: t`Accounts`,
            meta: {align: 'right'},
            cell: ({row: {original: stat}}) => stat.total_accounts.toLocaleString(),
        },
        {
            id: 'events',
            header: t`Events`,
            meta: {align: 'right'},
            cell: ({row: {original: stat}}) => stat.total_events.toLocaleString(),
        },
        {
            id: 'live_events',
            header: t`Live Events`,
            meta: {align: 'right'},
            cell: ({row: {original: stat}}) => stat.live_events.toLocaleString(),
        },
        {
            id: 'stripe_connected',
            header: t`Stripe Connected`,
            meta: {align: 'right'},
            cell: ({row: {original: stat}}) => stat.stripe_connected.toLocaleString(),
        },
        {
            id: 'verified',
            header: t`Verified`,
            meta: {align: 'right'},
            cell: ({row: {original: stat}}) => stat.verified_accounts.toLocaleString(),
        },
        {
            id: 'revenue',
            header: t`Revenue`,
            meta: {align: 'right'},
            cell: ({row: {original: stat}}) => <RevenueByCurrency revenue={stat.revenue_by_currency}/>,
        },
        {
            id: 'orders',
            header: t`Orders`,
            meta: {align: 'right'},
            cell: ({row: {original: stat}}) => stat.total_orders.toLocaleString(),
        },
    ], []);

    return (
        <Container size="xl" p="xl">
            <Stack gap="xl">
                <Group justify="space-between" align="flex-start">
                    <div>
                        <Title order={1} mb="xs">
                            <Trans>Attribution Analytics</Trans>
                        </Title>
                        <Text size="lg" c="dimmed">
                            <Trans>Track account growth and performance by attribution source</Trans>
                        </Text>
                        <Text size="sm" c="dimmed" mt="xs">
                            <Trans>Statistics are based on account creation date</Trans>
                        </Text>
                    </div>
                    <Group gap="sm">
                        <Select
                            style={{minWidth: 180}}
                            data={periods}
                            value={period}
                            allowDeselect={false}
                            onChange={(value) => {
                                setPeriod(value as Period);
                                setPage(1);
                            }}
                            leftSection={<IconCalendar stroke={1.5} size={18}/>}
                        />
                        {period === 'custom' && (
                            <DatePickerInput
                                style={{minWidth: 280}}
                                type="range"
                                value={customRange}
                                onChange={(value) => {
                                    setCustomRange(value as [Date | null, Date | null]);
                                    setPage(1);
                                }}
                                maxDate={new Date()}
                                leftSection={<IconCalendar stroke={1.5} size={18}/>}
                            />
                        )}
                    </Group>
                </Group>

                {isLoading ? (
                    <SimpleGrid cols={{base: 1, sm: 2, md: 4}} spacing="md">
                        <Skeleton height={100} radius="md"/>
                        <Skeleton height={100} radius="md"/>
                        <Skeleton height={100} radius="md"/>
                        <Skeleton height={100} radius="md"/>
                    </SimpleGrid>
                ) : summary && (
                    <SimpleGrid cols={{base: 1, sm: 2, md: 4}} spacing="md">
                        {summaryCards.map((card) => (
                            <Paper key={card.label} shadow="sm" p="md" radius="md" withBorder>
                                <Text size="xs" c="dimmed" fw={500}>{card.label}</Text>
                                <Text size="xl" fw={700} mt={4}>{card.value.toLocaleString()}</Text>
                            </Paper>
                        ))}
                    </SimpleGrid>
                )}

                <div>
                    <Group justify="space-between" mb="md">
                        <Title order={2}>
                            <Trans>Attribution Breakdown</Trans>
                        </Title>
                        <SegmentedControl
                            value={groupBy}
                            onChange={(value) => {
                                setGroupBy(value as AttributionGroupBy);
                                setPage(1);
                            }}
                            data={[
                                {label: t`Source`, value: 'source'},
                                {label: t`Medium`, value: 'medium'},
                                {label: t`Campaign`, value: 'campaign'},
                                {label: t`Content`, value: 'content'},
                                {label: t`Term`, value: 'term'},
                                {label: t`CTA`, value: 'cta'},
                                {label: t`Type`, value: 'source_type'},
                            ]}
                        />
                    </Group>

                    {isLoading ? (
                        <Stack gap="md">
                            <Skeleton height={400} radius="md"/>
                        </Stack>
                    ) : stats.length === 0 ? (
                        <TableCard>
                            <div className={tableStyles.emptyState}>
                                <Text c="dimmed" size="lg">
                                    <Trans>No attribution data found</Trans>
                                </Text>
                            </div>
                        </TableCard>
                    ) : (
                        <>
                            <TanStackTable data={stats} columns={columns}/>

                            {totalPages > 1 && (
                                <Group justify="center" mt="lg">
                                    <Pagination
                                        total={totalPages}
                                        value={page}
                                        onChange={setPage}
                                    />
                                </Group>
                            )}
                        </>
                    )}
                </div>
            </Stack>
        </Container>
    );
};

export default Attribution;
