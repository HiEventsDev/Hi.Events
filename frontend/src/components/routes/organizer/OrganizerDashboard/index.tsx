import React, {useEffect, useState} from 'react';
import {NavLink, useParams} from "react-router";
import {Badge, Button, Group, Menu, Skeleton, Tooltip, UnstyledButton} from '@mantine/core';
import {
    IconBuildingStore,
    IconCash,
    IconChevronDown,
    IconChevronRight,
    IconReceiptTax,
    IconReportMoney,
    IconTicket,
    IconUserCircle,
    IconUsers
} from '@tabler/icons-react';
import {t, Trans} from '@lingui/macro';

import {PageTitle} from "../../../common/PageTitle";
import {PageBody} from "../../../common/PageBody";
import {useGetOrganizerStats} from "../../../../queries/useGetOrganizerStats.ts";
import {useGetEvents} from "../../../../queries/useGetEvents.ts";
import {formatCurrency} from "../../../../utilites/currency.ts";
import {relativeDate} from "../../../../utilites/dates.ts";
import {formatNumber} from "../../../../utilites/helpers.ts";
import {Event, Order, QueryFilters} from '../../../../types';
import {useGetOrganizerOrders} from "../../../../queries/useGetOrganizerOrders.ts";
import classes from './OrganizerDashboard.module.scss';
import {StatBox} from "../../../common/StatBoxes";
import {useGetOrganizer} from "../../../../queries/useGetOrganizer.ts";
import {EventCard} from "../../../common/EventCard";
import {currenciesMap} from "../../../../../data/currencies.ts";
import {Card} from "../../../common/Card";
import {CreateEventModal} from "../../../modals/CreateEventModal";
import {getEventQueryFilters} from "../../../../utilites/eventsPageFiltersHelper.ts";

interface OrganizerStatDisplayItem {
    value: string | number;
    description: string;
    icon: React.ReactNode;
    backgroundColor: string;
}

export const DashboardSkeleton = () => {
    return (
        <PageBody>
            <Group justify="space-between" mb="xl">
                <Skeleton height={40} radius="md" width="60%"/>
                <Skeleton height={36} radius="md" width="150px"/>
            </Group>

            <div className={classes.statisticsSkeletonContainer}>
                {[...Array(6)].map((_, index) => (
                    <Skeleton key={index} height={105} radius="md"/>
                ))}
            </div>
            <div className={classes.recentItemsGrid}>
                {[...Array(2)].map((_, colIndex) => (
                    <div key={colIndex} className={classes.recentSection}>
                        <Skeleton height={30} radius="sm" mb="lg" width="40%"/>
                        <div className={classes.skeletonStack}>
                            {[...Array(3)].map((_, itemIndex) => (
                                <Skeleton key={itemIndex} height={100} radius="md"/>
                            ))}
                        </div>
                    </div>
                ))}
            </div>
        </PageBody>
    );
};


export const OrganizerDashboard = () => {
    const {organizerId} = useParams<{ organizerId: string }>();
    const {data: organizer} = useGetOrganizer(organizerId);
    const [showCreateEventModal, setShowCreateEventModal] = useState(false);

    const [selectedCurrency, setSelectedCurrency] = useState<string>(
        organizer?.currency || 'USD'
    );

    useEffect(() => {
        if (organizer?.currency && selectedCurrency !== organizer.currency) {
            setSelectedCurrency(organizer.currency);
        }
    }, [organizer?.currency]);

    const organizerStatsQuery = useGetOrganizerStats(organizerId, selectedCurrency);
    const stats = organizerStatsQuery.data;
    const allOrganizersCurrencies = organizerStatsQuery?.data?.all_organizers_currencies;
    const currencies = currenciesMap
        .filter((currency) => allOrganizersCurrencies?.includes(currency.value))

    const ordersQuery = useGetOrganizerOrders(organizerId, {
        perPage: 10,
        sortBy: 'created_at',
        sortDirection: 'desc',
    });

    const recentOrders = ordersQuery.data?.data;
    const isLoadingOrders = ordersQuery.isLoading;

    const {
        data: eventsResponse,
        isFetching: isLoadingEvents,
    } = useGetEvents(getEventQueryFilters({}) as QueryFilters);
    const recentEvents = eventsResponse?.data;

    const showOverallSkeleton = organizerStatsQuery.isLoading || isLoadingOrders || isLoadingEvents;

    if (showOverallSkeleton || !stats || !recentOrders || !recentEvents || !organizer) {
        return <DashboardSkeleton/>;
    }

    const organizerStatItems: OrganizerStatDisplayItem[] = [];
    if (stats && selectedCurrency) {
        organizerStatItems.push(
            {
                value: formatCurrency(stats.total_gross_sales, selectedCurrency),
                description: t`Gross Sales`,
                icon: <IconCash size={18}/>,
                backgroundColor: '#7C63E6'
            },
            {
                value: formatNumber(stats.total_products_sold),
                description: t`Products Sold`,
                icon: <IconTicket size={18}/>,
                backgroundColor: '#4B7BE5'
            },
            {
                value: formatNumber(stats.total_attendees_registered),
                description: t`Attendees`,
                icon: <IconUsers size={18}/>,
                backgroundColor: '#E6677E'
            },
            {
                value: formatNumber(stats.total_orders),
                description: t`Total Orders`,
                icon: <IconBuildingStore size={18}/>,
                backgroundColor: '#E67D49'
            },
            {
                value: formatCurrency(stats.total_tax, selectedCurrency),
                description: t`Total Tax`,
                icon: <IconReceiptTax size={18}/>,
                backgroundColor: '#49A6B7'
            },
            {
                value: formatCurrency(stats.total_fees, selectedCurrency),
                description: t`Total Fees`,
                icon: <IconReportMoney size={18}/>,
                backgroundColor: '#63B3A1'
            },
        );
    }

    return (
        <PageBody>
            <div className={classes.headerSection}>
                <PageTitle className={classes.pageTitle}>
                    {organizer ? `${organizer.name} - ${t`Dashboard`}` : t`Organizer Dashboard`}
                </PageTitle>
                {currencies?.length > 1 && (
                    <Menu
                        shadow="md"
                        width={240}
                        position="bottom-end"
                        disabled={organizerStatsQuery.isLoading}
                        withinPortal
                    >
                        <Menu.Target>
                            <UnstyledButton className={classes.currencySelector}
                                            disabled={organizerStatsQuery.isLoading}>
                            <span className={classes.currencyText}>
                                {selectedCurrency}
                            </span>
                                <IconChevronDown size={14} className={classes.currencyIcon}/>
                            </UnstyledButton>
                        </Menu.Target>
                        <Menu.Dropdown className={classes.currencyDropdown}>
                            <div className={classes.currencyScrollArea}>
                                {currencies
                                    .map((currency) => (
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

            {/* Stats Section */}
            {organizerStatsQuery.isLoading && !stats && (
                <div className={classes.statisticsContainer}>
                    {[...Array(4)].map((_, index) => ( // Show 4 skeleton StatBoxes
                        <Skeleton key={index} height={105} radius="md"/>
                    ))}
                </div>
            )}
            {stats && organizerStatItems.length > 0 && (
                <div className={classes.statisticsContainer}>
                    {organizerStatItems.map((item) => (
                        <StatBox
                            key={item.description}
                            number={String(item.value)}
                            description={item.description}
                            icon={item.icon}
                            backgroundColor={item.backgroundColor}
                        />
                    ))}
                </div>
            )}
            {!stats && !organizerStatsQuery.isLoading && (
                <Card>
                    <Trans>Organizer statistics are not available for the selected currency or an error
                        occurred.</Trans>
                </Card>
            )}

            {/* Recent Orders and Events Lists */}
            <div className={classes.recentItemsGrid}>
                {/* Events Section - First on mobile, second on desktop */}
                <div className={`${classes.recentSection} ${classes.eventsSection}`}>
                    <h3 className={classes.sectionTitle}><Trans>Upcoming Events</Trans></h3>
                    {isLoadingEvents && (
                        <div className={classes.skeletonStack}>
                            {[...Array(3)].map((_, i) => <Skeleton key={i} height={100} radius="md"/>)}
                        </div>
                    )}
                    {!isLoadingEvents && recentEvents && recentEvents.length > 0 && (
                        <div className={classes.eventsList}>
                            {recentEvents.map((event: Event) => (
                                <EventCard key={event.id} event={event}/>
                            ))}
                        </div>
                    )}
                    {!isLoadingEvents && (!recentEvents || recentEvents.length === 0) && (
                        <div className={classes.emptyState}>
                            <div className={classes.emptyStateIcon}>🎉</div>
                            <h4><Trans>No events yet</Trans></h4>
                            <p><Trans>Create your first event to start selling tickets and managing attendees.</Trans>
                            </p>
                            <Button
                                onClick={() => setShowCreateEventModal(true)}
                                variant="light"
                                size="sm"
                                mt="md"
                            >
                                <Trans>Create Event</Trans>
                            </Button>
                        </div>
                    )}
                </div>

                {/* Orders Section - Second on mobile, first on desktop */}
                <div className={`${classes.recentSection} ${classes.ordersSection}`}>
                    <h3 className={classes.sectionTitle}><Trans>Recent Orders</Trans></h3>
                    {isLoadingOrders && (
                        <div className={classes.skeletonStack}>
                            {[...Array(3)].map((_, i) => <Skeleton key={i} height={100} radius="md"/>)}
                        </div>
                    )}
                    {!isLoadingOrders && recentOrders && recentOrders.length > 0 && (
                        <div className={classes.ordersList}>
                            {recentOrders.map((order: Order) => (
                                <Card key={order.id} className={classes.orderCard}>
                                    <div className={classes.orderHeader}>
                                        <div className={classes.orderInfo}>
                                            <Tooltip label={t`Order ID: ${order.public_id}`} openDelay={500}>
                                                <span className={classes.orderId}>{order.public_id}</span>
                                            </Tooltip>
                                            <span className={classes.customerName}>
                                                <IconUserCircle size={14}/>
                                                {order.first_name} {order.last_name}
                                            </span>
                                        </div>
                                        <Badge
                                            color={getOrderStatusColor(order.status, order.payment_status)}
                                            variant="light"
                                            radius="sm"
                                            size="sm"
                                        >
                                            {formatOrderStatus(order.status, order.payment_status)}
                                        </Badge>
                                    </div>
                                    <div className={classes.orderFooter}>
                                        <span className={classes.orderMeta}>
                                            {relativeDate(order.created_at)} • {formatCurrency(order.total_gross, order.currency)}
                                        </span>
                                        <Button
                                            variant="subtle"
                                            size="xs"
                                            rightSection={<IconChevronRight size={14} style={{marginLeft: '2px'}}/>}
                                            component={NavLink}
                                            to={`/manage/event/${order.event_id}/orders#order-${order.id}`}
                                            styles={{
                                                root: {
                                                    padding: '0.375rem 0.75rem',
                                                    fontSize: '0.8125rem',
                                                }
                                            }}
                                        >
                                            <Trans>View</Trans>
                                        </Button>
                                    </div>
                                </Card>
                            ))}
                        </div>
                    )}
                    {!isLoadingOrders && (!recentOrders || recentOrders.length === 0) && (
                        <div className={classes.emptyState}>
                            <div className={classes.emptyStateIcon}>📦</div>
                            <h4><Trans>No orders yet</Trans></h4>
                            <p><Trans>When customers purchase tickets, their orders will appear here.</Trans></p>
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

const getOrderStatusColor = (status: Order['status'], paymentStatus?: Order['payment_status']): string => {
    if (status === 'COMPLETED' || paymentStatus === 'PAYMENT_RECEIVED') {
        return 'teal';
    }
    if (status === 'CANCELLED' || paymentStatus === 'PAYMENT_FAILED') {
        return 'red';
    }
    if (status === 'AWAITING_OFFLINE_PAYMENT' || paymentStatus === 'AWAITING_OFFLINE_PAYMENT' || status === 'RESERVED' || paymentStatus === 'AWAITING_PAYMENT') {
        return 'orange';
    }
    return 'gray';
};

const formatOrderStatus = (status: Order['status'], paymentStatus?: Order['payment_status']): string => {
    if (status === 'COMPLETED') {
        return t`Completed`;
    }
    if (status === 'CANCELLED') {
        return t`Cancelled`;
    }
    if ((status === 'RESERVED' || !status) && paymentStatus === 'AWAITING_PAYMENT') {
        return t`Awaiting Payment`;
    }
    if (status === 'AWAITING_OFFLINE_PAYMENT' || paymentStatus === 'AWAITING_OFFLINE_PAYMENT') {
        return t`Awaiting Offline Pmt.`;
    }

    return status ? status.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()) : t`Unknown`;
};


export default OrganizerDashboard;
