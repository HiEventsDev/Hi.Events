import {useGetEvent} from "../../../../queries/useGetEvent.ts";
import {useParams} from "react-router";
import {PageTitle} from "../../../common/PageTitle";
import {PageBody} from "../../../common/PageBody";
import {StatBoxes} from "../../../common/StatBoxes";
import {useGetMe} from "../../../../queries/useGetMe.ts";
import {t, Trans} from "@lingui/macro";
import {AreaChart} from "@mantine/charts";
import {Card} from "../../../common/Card";
import classes from "./EventDashboard.module.scss";
import {useGetEventStats} from "../../../../queries/useGetEventStats.ts";
import {formatCurrency} from "../../../../utilites/currency.ts";
import {formatDate} from "../../../../utilites/dates.ts";
import {Button, Skeleton} from "@mantine/core";
import {useMediaQuery} from "@mantine/hooks";
import {IconX} from "@tabler/icons-react";
import {useGetAccount} from "../../../../queries/useGetAccount.ts";
import {useUpdateEventStatus} from "../../../../mutations/useUpdateEventStatus.ts";
import {confirmationDialog} from "../../../../utilites/confirmationDialog.tsx";
import {showError, showSuccess} from "../../../../utilites/notifications.tsx";
import {useEffect, useState} from 'react';

export const DashBoardSkeleton = () => {
    return (
        <>
            <Skeleton height={120} radius="l" mb="20px"/>
            <Skeleton height={350} radius="l" mb="20px"/>
            <Skeleton height={350} radius="l"/>
        </>
    );
}

export const EventDashboard = () => {
    const {eventId} = useParams();
    const eventQuery = useGetEvent(eventId);
    const {data: me} = useGetMe();
    const event = eventQuery?.data;
    const eventStatsQuery = useGetEventStats(eventId);
    const {data: eventStats} = eventStatsQuery;
    const isMobile = useMediaQuery('(max-width: 768px)');
    const {data: account, isFetched: accountIsFetched} = useGetAccount();
    const statusToggleMutation = useUpdateEventStatus();

    const [isChecklistVisible, setIsChecklistVisible] = useState(true);
    const [isMounted, setIsMounted] = useState(false);

    useEffect(() => {
        setIsMounted(true);
        const dismissed = window.localStorage.getItem('setupChecklistDismissed-' + eventId);
        if (dismissed === 'true') {
            setIsChecklistVisible(false);
        }
    }, []);

    const dismissChecklist = () => {
        setIsChecklistVisible(false);
        if (isMounted) {
            window.localStorage.setItem('setupChecklistDismissed-' + eventId, 'true');
        }
    };

    const handleStatusToggle = () => {
        const message = event?.status === 'LIVE'
            ? t`Are you sure you want to make this event draft? This will make the event invisible to the public`
            : t`Are you sure you want to make this event public? This will make the event visible to the public`;

        confirmationDialog(message, () => {
            statusToggleMutation.mutate({
                eventId,
                status: event?.status === 'LIVE' ? 'DRAFT' : 'LIVE'
            }, {
                onSuccess: () => {
                    showSuccess(t`Event status updated`);
                },
                onError: (error: any) => {
                    showError(error?.response?.data?.message || t`Event status update failed. Please try again later`);
                }
            });
        })
    }

    const dateRange = (eventStats && event)
        ? `${formatDate(eventStats.start_date, 'MMM DD', event?.timezone)} - ${formatDate(eventStats.end_date, 'MMM DD', event?.timezone)}`
        : '';

    const shouldShowChecklist = (isChecklistVisible && event && accountIsFetched && account?.is_saas_mode_enabled) && (
        !account?.stripe_connect_setup_complete ||
        event?.status !== 'LIVE'
    );

    return (
        <PageBody>
            <PageTitle style={{marginBottom: 0}}>
                {!isMobile && (
                    <Trans>
                        Welcome back{me?.first_name && ', ' + me?.first_name} 👋
                    </Trans>
                )}

                {isMobile && (
                    <Trans>
                        Hi {me?.first_name && me?.first_name} 👋
                    </Trans>
                )}
            </PageTitle>

            {!event && <DashBoardSkeleton/>}

            {event && (<>
                <StatBoxes/>

                {shouldShowChecklist && (
                    <Card className={classes.setupCard}>
                        <div
                            className={classes.dismissButton}
                            onClick={dismissChecklist}
                            role="button"
                            aria-label="dismiss"
                        >
                            <IconX size={20}/>
                        </div>

                        <div className={classes.setupCardContent}>
                            <div className={classes.checklistContainer}>
                                <h2>🚀 {t`Get your event ready`}</h2>
                                <p className={classes.setupDescription}>
                                    {t`Complete these steps to start selling tickets for your event.`}
                                </p>

                                <div className={classes.checklistItems}>
                                    <div className={classes.checklistItem}>
                                        <h3>
                                            <div className={classes.checkboxContainer}>
                                                <div
                                                    className={classes.checkbox}
                                                    style={{backgroundColor: event?.status === 'LIVE' ? 'var(--tk-primary)' : 'transparent'}}
                                                >
                                                    {event?.status === 'LIVE' && (
                                                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none"
                                                             xmlns="http://www.w3.org/2000/svg">
                                                            <path d="M13.3333 4L6.00001 11.3333L2.66667 8"
                                                                  stroke="white" strokeWidth="2" strokeLinecap="round"
                                                                  strokeLinejoin="round"/>
                                                        </svg>
                                                    )}
                                                </div>
                                            </div>
                                            {t`Make your event live`}
                                        </h3>
                                        <p>{t`Your event must be live before you can sell tickets to attendees.`}</p>
                                        {event?.status !== 'LIVE' && (
                                            <Button
                                                onClick={handleStatusToggle}
                                                variant="light"
                                                size="sm"
                                                radius="md"
                                                fullWidth
                                            >
                                                {t`Publish Event`}
                                            </Button>
                                        )}
                                        {event?.status === 'LIVE' && (
                                            <Button
                                                onClick={handleStatusToggle}
                                                variant="light"
                                                size="sm"
                                                radius="md"
                                                fullWidth
                                            >
                                                {t`Unpublish Event`}
                                            </Button>
                                        )}
                                    </div>

                                    <div className={classes.checklistItem}>
                                        <h3>
                                            <div className={classes.checkboxContainer}>
                                                <div
                                                    className={classes.checkbox}
                                                    style={{backgroundColor: account?.stripe_connect_setup_complete ? 'var(--tk-primary)' : 'transparent'}}
                                                >
                                                    {account?.stripe_connect_setup_complete && (
                                                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none"
                                                             xmlns="http://www.w3.org/2000/svg">
                                                            <path d="M13.3333 4L6.00001 11.3333L2.66667 8"
                                                                  stroke="white" strokeWidth="2" strokeLinecap="round"
                                                                  strokeLinejoin="round"/>
                                                        </svg>
                                                    )}
                                                </div>
                                            </div>
                                            {t`Connect payment processing`}
                                        </h3>
                                        <p>{t`Link your Stripe account to receive funds from ticket sales.`}</p>
                                        {!account?.stripe_connect_setup_complete && (
                                            <Button
                                                onClick={() => {
                                                    window.location.href = '/account/payment';
                                                }}
                                                variant="light"
                                                size="sm"
                                                radius="md"
                                                fullWidth
                                            >
                                                {account?.stripe_account_id ? t`Complete Stripe Setup` : t`Connect to Stripe`}
                                            </Button>
                                        )}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </Card>
                )}

                <Card className={classes.chartCard}>
                    <div className={classes.chartCardTitle}>
                        <h2>{t`Product Sales`}</h2>
                        <div className={classes.dateRange}>
                        <span>
                            {dateRange}
                        </span>
                        </div>
                    </div>
                    <AreaChart
                        h={300}
                        data={eventStats?.daily_stats.map(stat => ({
                            date: formatDate(stat.date, 'MMM DD', event.timezone),
                            orders_created: stat.orders_created,
                            products_sold: stat.products_sold,
                            attendees_registered: stat.attendees_registered,
                        })) || []}
                        dataKey="date"
                        withLegend
                        legendProps={{verticalAlign: 'bottom', height: 50}}

                        series={[
                            {name: 'orders_created', color: 'blue.6', label: t`Completed Orders`},
                            {name: 'products_sold', color: 'blue.2', label: t`Products Sold`},
                            {name: 'attendees_registered', color: 'blue.4', label: t`Attendees Registered`},
                        ]}
                        curveType="bump"
                        tickLine="none"
                        areaChartProps={{syncId: 'events'}}
                    />
                </Card>

                <Card className={classes.chartCard}>
                    <div className={classes.chartCardTitle}>
                        <h2>{t`Revenue`}</h2>
                        <div className={classes.dateRange}>
                        <span>
                            {dateRange}
                        </span>
                        </div>
                    </div>

                    <AreaChart
                        h={300}
                        data={eventStats?.daily_stats.map(stat => {
                            return ({
                                date: formatDate(stat.date, 'MMM DD', event.timezone),
                                total_fees: stat.total_fees,
                                total_sales_gross: stat.total_sales_gross,
                                total_tax: stat.total_tax,
                                total_refunded: stat.total_refunded,
                            });
                        }) || []}
                        dataKey="date"
                        valueFormatter={(value) => formatCurrency(value, event.currency)}
                        withLegend
                        legendProps={{verticalAlign: 'bottom', height: 50}}
                        series={[
                            {name: 'total_fees', label: t`Total Fees`, color: 'purple.3'},
                            {name: 'total_sales_gross', label: t`Gross Sales`, color: 'grape.5'},
                            {name: 'total_tax', label: t`Total Tax`, color: 'grape.7'},
                            {name: 'total_refunded', label: t`Total Refunded`, color: 'red.6'},
                        ]}
                        curveType="natural"
                        tickLine="none"
                        areaChartProps={{syncId: 'events'}}
                    />
                </Card>
            </>)}
        </PageBody>
    )
};

export default EventDashboard;
