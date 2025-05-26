import React, {useEffect, useState} from 'react';
import {NavLink, useParams} from "react-router";
import {Badge, Button, Grid, Group, Paper, Select, Skeleton, Stack, Text, Title, Tooltip} from '@mantine/core';
import {
    IconBuildingStore,
    IconCalendarEvent,
    IconCash,
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
import {formatDate, relativeDate} from "../../../../utilites/dates.ts";
import {formatNumber} from "../../../../utilites/helpers.ts";
import {Event, EventStatus, Order, QueryFilterOperator} from '../../../../types';
import {useGetOrganizerOrders} from "../../../../queries/useGetOrganizerOrders.ts";
import classes from './OrganizerDashboard.module.scss';
import {StatBox} from "../../../common/StatBoxes";
import {useGetOrganizers} from "../../../../queries/useGetOrganizers.ts";

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

            {/* Skeleton for Stats Area */}
            <div className={classes.statisticsSkeletonContainer}>
                {[...Array(4)].map((_, index) => (
                    <Skeleton key={index} height={105} radius="md"/> // Simplified StatBox skeleton
                ))}
            </div>

            {/* Skeleton for Recent Orders/Events Lists */}
            <Grid gutter="xl" mt="xl">
                {[...Array(2)].map((_, colIndex) => (
                    <Grid.Col span={{base: 12, lg: 6}} key={colIndex}>
                        <Skeleton height={30} radius="sm" mb="lg" width="40%"/>
                        <Stack gap="md">
                            {[...Array(3)].map((_, itemIndex) => (
                                <Skeleton key={itemIndex} height={100} radius="md"/>
                            ))}
                        </Stack>
                    </Grid.Col>
                ))}
            </Grid>
        </PageBody>
    );
};


export const OrganizerDashboard = () => {
    const {organizerId} = useParams<{ organizerId: string }>();
    const {data: organizerData, isLoading: isLoadingOrganizer} = useGetOrganizers();
    const organizers = organizerData?.data;
    const organizer = organizers?.find(o => o.id === organizerId);
    // get an array of all currencies from the organizers
    const organizerCurrencies = organizers
        ?.map(o => o.currency).filter((value, index, self) => self.indexOf(value) === index);

    console.log(organizerCurrencies);

    const DUMMY_CURRENCIES = [
        {value: 'USD', label: 'USD'},
        {value: 'EUR', label: 'EUR'},
        {value: 'GBP', label: 'GBP'},
    ];

    const [selectedCurrency, setSelectedCurrency] = useState<string | undefined>();

    useEffect(() => {
        if (organizer?.currency) {
            setSelectedCurrency(prev => prev ?? organizer.currency);
        } else if (DUMMY_CURRENCIES.length > 0 && !selectedCurrency) {
            setSelectedCurrency(DUMMY_CURRENCIES[0].value);
        }
    }, [organizer, selectedCurrency, DUMMY_CURRENCIES]); // Added DUMMY_CURRENCIES to dependencies

    const organizerStatsQuery = useGetOrganizerStats(organizerId, selectedCurrency);
    const stats = organizerStatsQuery.data;

    const ordersQuery = useGetOrganizerOrders(organizerId, {
        perPage: 3,
        sortBy: 'created_at',
        sortDirection: 'desc',
    });
    const recentOrders = ordersQuery.data?.data;
    const isLoadingOrders = ordersQuery.isLoading;

    const {data: eventsResponse, isLoading: isLoadingEvents} = useGetEvents({
        perPage: 3,
        filterFields: organizerId ? {
            organizer_id: {
                operator: QueryFilterOperator.Equals,
                value: organizerId
            }
        } : undefined,
        sortBy: 'created_at', // Consider 'start_date' for "upcoming" if more relevant
        sortDirection: 'desc',
    });
    const recentEvents = eventsResponse?.data;

    const showOverallSkeleton = isLoadingOrganizer ||
        (organizerStatsQuery.isLoading && !stats) ||
        isLoadingOrders ||
        isLoadingEvents;

    if (showOverallSkeleton && !organizer && !stats && !recentOrders && !recentEvents) {
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
            <Group justify="space-between" align="center">
                <PageTitle style={{marginBottom: 0, flexGrow: 1}}>
                    {organizer ? `${organizer.name} - ${t`Dashboard`}` : t`Organizer Dashboard`}
                </PageTitle>
                <Select
                    data={DUMMY_CURRENCIES}
                    value={selectedCurrency}
                    onChange={(value) => setSelectedCurrency(value || undefined)}
                    placeholder={t`Select currency`}
                    className={classes.currencySwitcher}
                    checkIconPosition="right"
                    disabled={organizerStatsQuery.isLoading}
                />
            </Group>

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
                <Text c="dimmed" ta="center" my="xl">
                    <Trans>Organizer statistics are not available for the selected currency or an error
                        occurred.</Trans>
                </Text>
            )}

            {/* Recent Orders and Events Lists */}
            <Grid gutter="xl" mt="xl">
                <Grid.Col span={{base: 12, lg: 6}}>
                    <Title order={3} className={classes.sectionTitle} mb="lg"><Trans>Recent Orders</Trans></Title>
                    {isLoadingOrders && (
                        <Stack gap="md">
                            {[...Array(3)].map((_, i) => <Skeleton key={i} height={100} radius="md"/>)}
                        </Stack>
                    )}
                    {!isLoadingOrders && recentOrders && recentOrders.length > 0 && (
                        <Stack gap="md">
                            {recentOrders.map((order: Order) => (
                                <Paper key={order.id} p="md" radius="md" withBorder className={classes.listItemCard}>
                                    <Group justify="space-between" align="flex-start">
                                        <Stack gap={0}>
                                            <Tooltip label={t`Order ID: ${order.public_id}`} openDelay={500}>
                                                <Text fw={600} size="md"
                                                      className={classes.itemTitle}>{order.public_id}</Text>
                                            </Tooltip>
                                            <Text size="sm" c="dimmed" display="flex" style={{alignItems: 'center'}}>
                                                <IconUserCircle size={16} style={{marginRight: '4px'}}/>
                                                {order.first_name} {order.last_name}
                                            </Text>
                                        </Stack>
                                        <Badge color={getOrderStatusColor(order.status, order.payment_status)}
                                               variant="light" radius="sm">
                                            {formatOrderStatus(order.status, order.payment_status)}
                                        </Badge>
                                    </Group>
                                    <Group justify="space-between" mt="sm" align="center">
                                        <Text size="sm" c="dimmed">
                                            {relativeDate(order.created_at)} • {formatCurrency(order.total_gross, order.currency)}
                                        </Text>
                                        <Button
                                            variant="subtle"
                                            size="xs"
                                            rightSection={<IconChevronRight size={14}/>}
                                            component={NavLink}
                                            to={`/manage/event/${order.event_id}/orders#order-${order.id}`}
                                        >
                                            <Trans>View</Trans>
                                        </Button>
                                    </Group>
                                </Paper>
                            ))}
                        </Stack>
                    )}
                    {!isLoadingOrders && (!recentOrders || recentOrders.length === 0) && (
                        <Text c="dimmed" ta="center" py="lg"><Trans>No recent orders found.</Trans></Text>
                    )}
                </Grid.Col>

                <Grid.Col span={{base: 12, lg: 6}}>
                    <Title order={3} className={classes.sectionTitle} mb="lg"><Trans>Upcoming Events</Trans></Title>
                    {isLoadingEvents && (
                        <Stack gap="md">
                            {[...Array(3)].map((_, i) => <Skeleton key={i} height={100} radius="md"/>)}
                        </Stack>
                    )}
                    {!isLoadingEvents && recentEvents && recentEvents.length > 0 && (
                        <Stack gap="md">
                            {recentEvents.map((event: Event) => (
                                <Paper key={event.id} p="md" radius="md" withBorder className={classes.listItemCard}>
                                    <Group justify="space-between" align="flex-start">
                                        <Stack gap={0}>
                                            <Text fw={600} size="md" className={classes.itemTitle}>{event.title}</Text>
                                            <Text size="sm" c="dimmed" display="flex" style={{alignItems: 'center'}}>
                                                <IconCalendarEvent size={16} style={{marginRight: '4px'}}/>
                                                {formatDate(event.start_date, 'MMM DD, YYYY', event.timezone)}
                                            </Text>
                                        </Stack>
                                        <Badge color={getEventStatusColor(event.status)} variant="light" radius="sm">
                                            {event.status ? event.status.charAt(0).toUpperCase() + event.status.slice(1).toLowerCase() : t`Unknown`}
                                        </Badge>
                                    </Group>
                                    <Group justify="space-between" mt="sm" align="center">
                                        <Text size="sm" c="dimmed">
                                            <Trans>
                                                3 products
                                            </Trans>
                                        </Text>
                                        <Button
                                            component={NavLink}
                                            to={`/manage/event/${event.id}/dashboard`}
                                            variant="subtle"
                                            size="xs"
                                            rightSection={<IconChevronRight size={14}/>}
                                        >
                                            <Trans>Manage</Trans>
                                        </Button>
                                    </Group>
                                </Paper>
                            ))}
                        </Stack>
                    )}
                    {!isLoadingEvents && (!recentEvents || recentEvents.length === 0) && (
                        <Text c="dimmed" ta="center" py="lg"><Trans>No recent events found.</Trans></Text>
                    )}
                </Grid.Col>
            </Grid>
        </PageBody>
    );
};

const getOrderStatusColor = (status: Order['status'], paymentStatus?: Order['payment_status']): string => {
    if (status === 'COMPLETED' || paymentStatus === 'PAYMENT_RECEIVED') {
        return 'green';
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

const getEventStatusColor = (status?: EventStatus): string => {
    switch (status) {
        case EventStatus.LIVE:
            return 'green';
        case EventStatus.DRAFT:
            return 'gray';
        case EventStatus.PAUSED:
            return 'orange';
        case EventStatus.ARCHIVED:
            return 'grape';
        default:
            return 'blue';
    }
};

export default OrganizerDashboard;
