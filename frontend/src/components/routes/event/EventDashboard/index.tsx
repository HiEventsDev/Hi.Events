import {useGetEvent} from "../../../../queries/useGetEvent.ts";
import {useLocation, useNavigate, useParams} from "react-router";
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
import {formatDateWithLocale} from "../../../../utilites/dates.ts";
import {Skeleton} from "@mantine/core";
import {useMediaQuery} from "@mantine/hooks";
import {useGetAccount} from "../../../../queries/useGetAccount.ts";
import {useUpdateEventStatus} from "../../../../mutations/useUpdateEventStatus.ts";
import {confirmationDialog} from "../../../../utilites/confirmationDialog.tsx";
import {showError, showSuccess} from "../../../../utilites/notifications.tsx";
import {useEffect, useState} from 'react';
import {EventType} from "../../../../types.ts";
import {UpcomingOccurrences} from "./UpcomingOccurrences";
import {NextOccurrenceHero} from "./NextOccurrenceHero";
import {trackEvent, AnalyticsEvents} from "../../../../utilites/analytics.ts";
import {useGetOrganizer} from "../../../../queries/useGetOrganizer.ts";
import {useGetEventProductCategories} from "../../../../queries/useGetProductCategories.ts";
import {useGetEventSettings} from "../../../../queries/useGetEventSettings.ts";
import {PeriodSelector, PeriodPreset} from "../../../common/PeriodSelector";
import {periodPresetToDateRange} from "../../../../utilites/periodPreset.ts";
import {hasEventDetails, SetupChecklist} from "./SetupChecklist";

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
    const navigate = useNavigate();
    const location = useLocation();
    const eventQuery = useGetEvent(eventId);
    const {data: me} = useGetMe();
    const event = eventQuery?.data;

    const [effectiveDateRange, setDateRange] = useState<PeriodPreset>('last_30_days');
    const {startDate, endDate} = periodPresetToDateRange(effectiveDateRange);

    const eventStatsQuery = useGetEventStats(eventId, {
        startDate,
        endDate,
        enabled: !!event,
    });
    const {data: eventStats} = eventStatsQuery;
    const isMobile = useMediaQuery('(max-width: 768px)');
    const {data: account, isFetched: accountIsFetched} = useGetAccount();
    const statusToggleMutation = useUpdateEventStatus();

    const [isChecklistDismissed, setIsChecklistDismissed] = useState(false);
    const [isMounted, setIsMounted] = useState(false);

    const organizerId = event?.organizer_id ?? event?.organizer?.id;
    const {data: organizer} = useGetOrganizer(organizerId);
    const isStripeConnected = !!organizer?.stripe_connect_setup_complete;
    const {data: eventSettings} = useGetEventSettings(eventId);
    const {data: productCategoriesResponse} = useGetEventProductCategories(eventId);
    const productCount = productCategoriesResponse?.data?.reduce(
        (sum, category) => sum + (category.products?.length ?? 0),
        0,
    ) ?? 0;

    useEffect(() => {
        setIsMounted(true);
        if (typeof window === 'undefined' || !eventId) {
            return;
        }
        const dismissed = window.localStorage.getItem('setupChecklistDismissed-' + eventId);
        if (dismissed === 'true') {
            setIsChecklistDismissed(true);
        }
    }, [eventId]);

    const isNewEvent = new URLSearchParams(location.search).get('new_event') === 'true';

    const dismissChecklist = () => {
        setIsChecklistDismissed(true);
        if (isMounted && typeof window !== 'undefined') {
            window.localStorage.setItem('setupChecklistDismissed-' + eventId, 'true');
            const params = new URLSearchParams(location.search);
            if (params.has('new_event')) {
                params.delete('new_event');
                navigate({pathname: location.pathname, search: params.toString()}, {replace: true});
            }
        }
    };

    const handleStatusToggle = () => {
        const newStatus = event?.status === 'LIVE' ? 'DRAFT' : 'LIVE';
        const message = event?.status === 'LIVE'
            ? t`Are you sure you want to make this event draft? This will make the event invisible to the public`
            : t`Are you sure you want to make this event public? This will make the event visible to the public`;

        confirmationDialog(message, () => {
            statusToggleMutation.mutate({
                eventId,
                status: newStatus
            }, {
                onSuccess: () => {
                    if (newStatus === 'LIVE') {
                        trackEvent(AnalyticsEvents.EVENT_PUBLISHED);
                    }
                    showSuccess(t`Event status updated`);
                },
                onError: (error: any) => {
                    showError(error?.response?.data?.message || t`Event status update failed. Please try again later`);
                }
            });
        })
    }

    const handleConnectStripe = () => {
        if (!organizerId) {
            return;
        }
        navigate(`/manage/organizer/${organizerId}/settings#payouts`);
    };

    const handleAddTickets = () => {
        if (!eventId) {
            return;
        }
        navigate(`/manage/event/${eventId}/products`);
    };

    const handleEditDetails = () => {
        if (!eventId) {
            return;
        }
        navigate(`/manage/event/${eventId}/settings`);
    };

    const allChecklistComplete = !!event
        && event.status === 'LIVE'
        && isStripeConnected
        && productCount > 0
        && hasEventDetails(event, eventSettings);

    useEffect(() => {
        if (allChecklistComplete && isMounted && typeof window !== 'undefined' && eventId) {
            window.localStorage.removeItem('setupChecklistDismissed-' + eventId);
        }
    }, [allChecklistComplete, isMounted, eventId]);

    const dateRangeLabel = (event)
        ? `${formatDateWithLocale(startDate, 'chartDate', event.timezone)} - ${formatDateWithLocale(endDate, 'chartDate', event.timezone)}`
        : '';

    const shouldShowChecklist = !!(event && accountIsFetched && account?.is_saas_mode_enabled);

    return (
        <PageBody>
            {!isNewEvent && (
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
            )}

            {!event && <DashBoardSkeleton/>}

            {event && (<>
                {shouldShowChecklist && (
                    <SetupChecklist
                        event={event}
                        eventSettings={eventSettings}
                        organizer={organizer}
                        isStripeConnected={isStripeConnected}
                        productCount={productCount}
                        onPublish={handleStatusToggle}
                        onConnectStripe={handleConnectStripe}
                        onAddTickets={handleAddTickets}
                        onEditDetails={handleEditDetails}
                        onDismiss={dismissChecklist}
                        isDismissed={isChecklistDismissed}
                        showCongratsHeader={isNewEvent}
                    />
                )}

                {event?.type === EventType.RECURRING && (
                    <NextOccurrenceHero event={event} eventId={eventId}/>
                )}

                <div className={classes.sectionLabel}>
                    <span>{t`Event totals`}</span>
                    {dateRangeLabel && <span className={classes.sectionLabelRange}>· {dateRangeLabel}</span>}
                    <div className={classes.sectionLabelSpacer}/>
                    <PeriodSelector
                        value={effectiveDateRange}
                        onChange={setDateRange}
                        storageKey={`eventDashboard.dateRange.${eventId}`}
                    />
                </div>

                <StatBoxes dateRange={effectiveDateRange}/>

                {event?.type === EventType.RECURRING && (
                    <UpcomingOccurrences eventId={eventId} event={event}/>
                )}

                <Card className={classes.chartCard}>
                    <div className={classes.chartCardTitle}>
                        <h2>{t`Product Sales`}</h2>
                        <div className={classes.dateRange}>
                        <span>
                            {dateRangeLabel}
                        </span>
                        </div>
                    </div>
                    <AreaChart
                        h={300}
                        data={eventStats?.daily_stats.map(stat => ({
                            date: formatDateWithLocale(stat.date, 'chartDate', event.timezone),
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
                            {dateRangeLabel}
                        </span>
                        </div>
                    </div>

                    <AreaChart
                        h={300}
                        pl={40}
                        pr={40}
                        data={eventStats?.daily_stats.map(stat => {
                            return ({
                                date: formatDateWithLocale(stat.date, 'chartDate', event.timezone),
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
                            {name: 'total_fees', label: t`Total Fees`, color: 'primary.3'},
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
