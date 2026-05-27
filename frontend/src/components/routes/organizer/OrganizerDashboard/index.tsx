import {useEffect, useMemo, useState} from 'react';
import {NavLink, useParams} from "react-router";
import {Button, Menu, SegmentedControl, Skeleton, UnstyledButton} from '@mantine/core';
import {AreaChart} from "@mantine/charts";
import {IconArrowDownRight, IconArrowUpRight, IconChevronDown, IconChevronRight, IconReceipt} from '@tabler/icons-react';
import {t, Trans} from '@lingui/macro';

import {PageTitle} from "../../../common/PageTitle";
import {PageBody} from "../../../common/PageBody";
import {useGetOrganizerStats} from "../../../../queries/useGetOrganizerStats.ts";
import {useGetEvents} from "../../../../queries/useGetEvents.ts";
import {formatCurrency} from "../../../../utilites/currency.ts";
import {relativeDate} from "../../../../utilites/dates.ts";
import {formatNumber} from "../../../../utilites/helpers.ts";
import {Event, Order, OrganizerDailyStats, QueryFilters} from '../../../../types';
import {useGetOrganizerOrders} from "../../../../queries/useGetOrganizerOrders.ts";
import classes from './OrganizerDashboard.module.scss';
import {KpiCell, KpiGrid} from "../../../common/KpiGrid";
import {PeriodSelector, PeriodPreset} from "../../../common/PeriodSelector";
import {computeDelta, periodPresetToDateRange, previousPeriodRange} from "../../../../utilites/periodPreset.ts";
import {useGetOrganizer} from "../../../../queries/useGetOrganizer.ts";
import {currenciesMap} from "../../../../../data/currencies.ts";
import {CreateEventModal} from "../../../modals/CreateEventModal";
import {getEventQueryFilters} from "../../../../utilites/eventsPageFiltersHelper.ts";
import {EventCard} from "../../../common/EventCard";

type HeroTab = 'sales' | 'orders' | 'attendees';

export const DashboardSkeleton = () => {
    return (
        <PageBody>
            <div style={{display: 'flex', justifyContent: 'space-between', marginBottom: 16}}>
                <Skeleton height={40} radius="md" width="60%"/>
                <Skeleton height={36} radius="md" width="220px"/>
            </div>
            <Skeleton height={140} radius="md" mb={12}/>
            <Skeleton height={210} radius="md" mb={12}/>
            <div className={classes.recentLists}>
                <Skeleton height={180} radius="md"/>
                <Skeleton height={180} radius="md"/>
            </div>
        </PageBody>
    );
};

export const OrganizerDashboard = () => {
    const {organizerId} = useParams<{ organizerId: string }>();
    const {data: organizer} = useGetOrganizer(organizerId);
    const [showCreateEventModal, setShowCreateEventModal] = useState(false);
    const [heroTab, setHeroTab] = useState<HeroTab>('sales');
    const [periodPreset, setPeriodPreset] = useState<PeriodPreset>('last_30_days');

    const [selectedCurrency, setSelectedCurrency] = useState<string>(
        organizer?.currency || 'USD'
    );

    useEffect(() => {
        if (organizer?.currency && selectedCurrency !== organizer.currency) {
            setSelectedCurrency(organizer.currency);
        }
    }, [organizer?.currency]);

    const currentRange = useMemo(() => periodPresetToDateRange(periodPreset), [periodPreset]);
    const previousRange = useMemo(() => previousPeriodRange(currentRange), [currentRange]);

    const organizerStatsQuery = useGetOrganizerStats(organizerId, selectedCurrency, {
        startDate: currentRange.startDate,
        endDate: currentRange.endDate,
    });
    const previousStatsQuery = useGetOrganizerStats(organizerId, selectedCurrency, {
        startDate: previousRange.startDate,
        endDate: previousRange.endDate,
    });

    const stats = organizerStatsQuery.data;
    const previousStats = previousStatsQuery.data;
    const allOrganizersCurrencies = stats?.all_organizers_currencies;
    const currencies = currenciesMap.filter((currency) =>
        allOrganizersCurrencies?.includes(currency.value),
    );

    const ordersQuery = useGetOrganizerOrders(organizerId, {
        perPage: 10,
        sortBy: 'created_at',
        sortDirection: 'desc',
    });
    const recentOrders = ordersQuery.data?.data;

    const {data: eventsResponse} = useGetEvents(getEventQueryFilters({}) as QueryFilters);
    const recentEvents = eventsResponse?.data?.slice(0, 10);

    const isStatsLoading = organizerStatsQuery.isLoading;
    const isPreviousLoading = previousStatsQuery.isLoading;

    if (!organizer) {
        return <DashboardSkeleton/>;
    }

    const dailyStats: OrganizerDailyStats[] = stats?.daily_stats ?? [];

    const heroValueRaw = (() => {
        if (!stats) return 0;
        if (heroTab === 'sales') return stats.total_gross_sales;
        if (heroTab === 'orders') return stats.total_orders;
        return stats.total_attendees_registered;
    })();

    const heroPreviousRaw = (() => {
        if (!previousStats) return null;
        if (heroTab === 'sales') return previousStats.total_gross_sales;
        if (heroTab === 'orders') return previousStats.total_orders;
        return previousStats.total_attendees_registered;
    })();

    const heroLabel = heroTab === 'sales'
        ? t`Gross sales`
        : heroTab === 'orders'
            ? t`Orders`
            : t`Attendees`;

    const heroFormattedValue = heroTab === 'sales'
        ? formatCurrency(heroValueRaw, selectedCurrency)
        : formatNumber(heroValueRaw);

    const heroDelta = computeDelta(
        stats ? heroValueRaw : null,
        previousStats ? heroPreviousRaw : null,
    );

    const heroChartData = dailyStats.map((d) => ({
        date: d.date,
        value: heroTab === 'sales'
            ? d.total_sales_gross
            : heroTab === 'orders'
                ? d.orders_created
                : d.attendees_registered,
    }));

    const sparkline = (key: keyof OrganizerDailyStats): number[] =>
        dailyStats.map((d) => Number(d[key] ?? 0));

    const tabOptions = [
        {value: 'sales', label: t`Sales`},
        {value: 'orders', label: t`Orders`},
        {value: 'attendees', label: t`Attendees`},
    ];

    const heroValueFormatter = (value: number): string =>
        String(heroTab === 'sales' ? formatCurrency(value, selectedCurrency) : formatNumber(value));

    return (
        <PageBody>
            <div className={classes.headerSection}>
                <PageTitle className={classes.pageTitle}>
                    {organizer ? `${organizer.name} - ${t`Dashboard`}` : t`Organizer Dashboard`}
                </PageTitle>
                <div className={classes.headerControls}>
                    <PeriodSelector
                        value={periodPreset}
                        onChange={setPeriodPreset}
                        storageKey={`organizerDashboard.dateRange.${organizerId}`}
                    />
                    {currencies?.length > 1 && (
                        <Menu
                            shadow="md"
                            width={240}
                            position="bottom-end"
                            disabled={isStatsLoading}
                            withinPortal
                        >
                            <Menu.Target>
                                <UnstyledButton
                                    className={classes.currencySelector}
                                    disabled={isStatsLoading}
                                >
                                    <span className={classes.currencyText}>{selectedCurrency}</span>
                                    <IconChevronDown size={14} className={classes.currencyIcon}/>
                                </UnstyledButton>
                            </Menu.Target>
                            <Menu.Dropdown className={classes.currencyDropdown}>
                                <div className={classes.currencyScrollArea}>
                                    {currencies.map((currency) => (
                                        <Menu.Item
                                            key={currency.value}
                                            onClick={() => setSelectedCurrency(currency.value)}
                                            className={selectedCurrency === currency.value ? classes.selectedCurrency : ''}
                                        >
                                            <span className={classes.currencyOption}>
                                                <span className={classes.currencyCode}>{currency.value}</span>
                                                <span className={classes.currencyLabel}>{currency.label}</span>
                                            </span>
                                        </Menu.Item>
                                    ))}
                                </div>
                            </Menu.Dropdown>
                        </Menu>
                    )}
                </div>
            </div>

            <div className={classes.heroChart}>
                <div className={classes.heroChartHeader}>
                    <div>
                        <div className={classes.heroChartLabel}>{heroLabel}</div>
                        <div className={classes.heroChartValue}>
                            {isStatsLoading ? <Skeleton height={24} width={140}/> : heroFormattedValue}
                        </div>
                        {!isStatsLoading && !isPreviousLoading && heroDelta != null && (
                            <div>
                                {heroDelta.trend === 'flat' ? (
                                    <span className={`${classes.heroChartDelta} ${classes.heroChartDeltaNone}`}>
                                        {t`No change`}
                                    </span>
                                ) : heroDelta.trend === 'up' ? (
                                    <span className={`${classes.heroChartDelta} ${classes.heroChartDeltaUp}`}>
                                        <IconArrowUpRight size={12} stroke={2}/>
                                        {`+${Math.abs(heroDelta.percent).toFixed(1)}%`}
                                    </span>
                                ) : (
                                    <span className={`${classes.heroChartDelta} ${classes.heroChartDeltaDown}`}>
                                        <IconArrowDownRight size={12} stroke={2}/>
                                        {`-${Math.abs(heroDelta.percent).toFixed(1)}%`}
                                    </span>
                                )}
                            </div>
                        )}
                    </div>
                    <SegmentedControl
                        size="xs"
                        radius="md"
                        value={heroTab}
                        onChange={(v) => setHeroTab(v as HeroTab)}
                        data={tabOptions}
                        className={classes.heroChartTabs}
                        styles={{
                            root: {background: 'var(--mantine-color-gray-0)', padding: 2},
                        }}
                    />
                </div>
                <div className={classes.heroChartArea}>
                    {heroChartData.length > 0 ? (
                        <AreaChart
                            h={64}
                            data={heroChartData}
                            dataKey="date"
                            series={[{name: 'value', color: 'primary.5', label: ''}]}
                            withGradient
                            withXAxis={false}
                            withYAxis={false}
                            gridAxis="none"
                            withDots={false}
                            withLegend={false}
                            curveType="natural"
                            withTooltip
                            valueFormatter={heroValueFormatter}
                        />
                    ) : (
                        <Skeleton height={64} radius="sm"/>
                    )}
                </div>
            </div>

            <KpiGrid>
                <KpiCell
                    label={t`Gross sales`}
                    value={stats ? formatCurrency(stats.total_gross_sales, selectedCurrency) : ''}
                    sparkline={sparkline('total_sales_gross')}
                    delta={computeDelta(stats?.total_gross_sales, previousStats?.total_gross_sales)}
                    isLoading={isStatsLoading || isPreviousLoading}
                />
                <KpiCell
                    label={t`Products sold`}
                    value={stats ? formatNumber(stats.total_products_sold) : ''}
                    sparkline={sparkline('products_sold')}
                    delta={computeDelta(stats?.total_products_sold, previousStats?.total_products_sold)}
                    isLoading={isStatsLoading || isPreviousLoading}
                />
                <KpiCell
                    label={t`Attendees`}
                    value={stats ? formatNumber(stats.total_attendees_registered) : ''}
                    sparkline={sparkline('attendees_registered')}
                    delta={computeDelta(stats?.total_attendees_registered, previousStats?.total_attendees_registered)}
                    isLoading={isStatsLoading || isPreviousLoading}
                />
                <KpiCell
                    label={t`Total orders`}
                    value={stats ? formatNumber(stats.total_orders) : ''}
                    sparkline={sparkline('orders_created')}
                    delta={computeDelta(stats?.total_orders, previousStats?.total_orders)}
                    isLoading={isStatsLoading || isPreviousLoading}
                />
                <KpiCell
                    label={t`Total tax`}
                    value={stats ? formatCurrency(stats.total_tax, selectedCurrency) : ''}
                    delta={computeDelta(stats?.total_tax, previousStats?.total_tax)}
                    isLoading={isStatsLoading || isPreviousLoading}
                />
                <KpiCell
                    label={t`Total fees`}
                    value={stats ? formatCurrency(stats.total_fees, selectedCurrency) : ''}
                    delta={computeDelta(stats?.total_fees, previousStats?.total_fees)}
                    isLoading={isStatsLoading || isPreviousLoading}
                />
            </KpiGrid>

            <div className={classes.recentLists}>
                <div className={`${classes.listCard} ${classes.ordersListCard}`}>
                    <div className={classes.listCardHeader}>
                        <div className={classes.listCardTitle}>{t`Recent orders`}</div>
                    </div>
                    {recentOrders && recentOrders.length > 0 ? (
                        <div className={classes.orderCardList}>
                            {recentOrders.map((order: Order) => {
                                const customerName = [order.first_name, order.last_name].filter(Boolean).join(' ');
                                const orderStatus = formatOrderStatus(order);
                                const initials = orderInitials(order.first_name, order.last_name);
                                const gradient = orderGradient(order.id);
                                return (
                                    <NavLink
                                        key={order.id}
                                        to={`/manage/event/${order.event_id}/orders#order-${order.id}`}
                                        className={classes.orderCardLink}
                                    >
                                        <div className={classes.orderCard}>
                                            <div
                                                className={classes.orderAvatar}
                                                style={{background: gradient}}
                                            >
                                                {initials || <IconReceipt size={20}/>}
                                            </div>
                                            <div className={classes.orderContent}>
                                                <div className={classes.orderPrimary}>
                                                    <span className={classes.orderName}>
                                                        {customerName || t`Guest`}
                                                    </span>
                                                    <span className={classes.orderId}>#{order.public_id}</span>
                                                </div>
                                                <div className={classes.orderMeta}>
                                                    <span className={`${classes.statusPill} ${classes[`statusPill-${orderStatus.tone}`]}`}>
                                                        {orderStatus.label}
                                                    </span>
                                                    <span className={classes.orderDot}>·</span>
                                                    <span className={classes.orderMuted}>
                                                        {relativeDate(order.created_at)}
                                                    </span>
                                                </div>
                                            </div>
                                            <div className={classes.orderStat}>
                                                <span className={classes.orderStatValue}>
                                                    {formatCurrency(order.total_gross, order.currency)}
                                                </span>
                                                <span className={classes.orderStatLabel}>{t`Total`}</span>
                                            </div>
                                            <IconChevronRight size={16} className={classes.orderChevron}/>
                                        </div>
                                    </NavLink>
                                );
                            })}
                        </div>
                    ) : (
                        <div className={classes.eventsEmptyState}>
                            <div className={classes.eventsEmptyEmoji}>📦</div>
                            <h4 className={classes.eventsEmptyTitle}>
                                <Trans>No orders yet</Trans>
                            </h4>
                            <p className={classes.eventsEmptyCopy}>
                                <Trans>When customers purchase tickets, their orders will appear here.</Trans>
                            </p>
                        </div>
                    )}
                </div>

                <div className={`${classes.listCard} ${classes.eventsListCard}`}>
                    <div className={classes.listCardHeader}>
                        <div className={classes.listCardTitle}>{t`Upcoming events`}</div>
                        <NavLink
                            to={`/manage/organizer/${organizerId}/events`}
                            className={classes.listCardLink}
                        >
                            {t`View all →`}
                        </NavLink>
                    </div>
                    {recentEvents && recentEvents.length > 0 ? (
                        <div className={classes.eventCardList}>
                            {recentEvents.map((event: Event) => (
                                <EventCard key={event.id} event={event} compact/>
                            ))}
                        </div>
                    ) : (
                        <div className={classes.eventsEmptyState}>
                            <div className={classes.eventsEmptyEmoji}>🎉</div>
                            <h4 className={classes.eventsEmptyTitle}>
                                <Trans>No events yet</Trans>
                            </h4>
                            <p className={classes.eventsEmptyCopy}>
                                <Trans>Create your first event to start selling tickets and managing attendees.</Trans>
                            </p>
                            <Button
                                onClick={() => setShowCreateEventModal(true)}
                                variant="light"
                                size="sm"
                                mt="xs"
                            >
                                <Trans>Create event</Trans>
                            </Button>
                        </div>
                    )}
                </div>
            </div>

            {showCreateEventModal && (
                <CreateEventModal
                    onClose={() => setShowCreateEventModal(false)}
                    organizerId={organizerId}
                />
            )}
        </PageBody>
    );
};

type StatusTone = 'success' | 'warning' | 'danger' | 'muted';

const formatOrderStatus = (order: Order): {label: string; tone: StatusTone} => {
    if (order.status === 'CANCELLED' || order.payment_status === 'PAYMENT_FAILED') {
        return {label: t`Cancelled`, tone: 'danger'};
    }
    if (order.refund_status === 'REFUNDED') {
        return {label: t`Refunded`, tone: 'muted'};
    }
    if (order.status === 'COMPLETED' || order.payment_status === 'PAYMENT_RECEIVED') {
        return {label: t`Paid`, tone: 'success'};
    }
    if (order.status === 'AWAITING_OFFLINE_PAYMENT' || order.payment_status === 'AWAITING_OFFLINE_PAYMENT') {
        return {label: t`Awaiting payment`, tone: 'warning'};
    }
    if (order.payment_status === 'AWAITING_PAYMENT' || order.status === 'RESERVED') {
        return {label: t`Pending`, tone: 'warning'};
    }
    return {label: order.status ?? t`Unknown`, tone: 'muted'};
};

const orderAvatarGradients = [
    'linear-gradient(135deg, var(--mantine-color-violet-5) 0%, var(--mantine-color-indigo-5) 100%)',
    'linear-gradient(135deg, var(--mantine-color-pink-5) 0%, var(--mantine-color-grape-5) 100%)',
    'linear-gradient(135deg, var(--mantine-color-blue-5) 0%, var(--mantine-color-cyan-5) 100%)',
    'linear-gradient(135deg, var(--mantine-color-teal-5) 0%, var(--mantine-color-green-5) 100%)',
    'linear-gradient(135deg, var(--mantine-color-orange-5) 0%, var(--mantine-color-yellow-5) 100%)',
    'linear-gradient(135deg, var(--mantine-color-indigo-5) 0%, var(--mantine-color-blue-5) 100%)',
    'linear-gradient(135deg, var(--mantine-color-grape-5) 0%, var(--mantine-color-violet-5) 100%)',
    'linear-gradient(135deg, var(--mantine-color-cyan-5) 0%, var(--mantine-color-teal-5) 100%)',
];

const orderGradient = (orderId: Order['id']): string => {
    const idNum = typeof orderId === 'number' ? orderId : Number(orderId ?? 0);
    return orderAvatarGradients[Math.abs(idNum) % orderAvatarGradients.length];
};

const orderInitials = (firstName?: string, lastName?: string): string => {
    const f = firstName?.trim()?.[0] ?? '';
    const l = lastName?.trim()?.[0] ?? '';
    return (f + l).toUpperCase();
};

export default OrganizerDashboard;
