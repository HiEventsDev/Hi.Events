import {useGetEvent} from "../../../../queries/useGetEvent.ts";
import {useLocation, useNavigate, useParams} from "react-router";
import {PageTitle} from "../../../common/PageTitle";
import {PageBody} from "../../../common/PageBody";
import {StatBoxes} from "../../../common/StatBoxes";
import {useGetMe} from "../../../../queries/useGetMe.ts";
import {t, Trans} from "@lingui/macro";
import {ProductSalesChartCard, RevenueChartCard} from "../../../common/StatsCharts";
import classes from "./EventDashboard.module.scss";
import {useGetEventStats} from "../../../../queries/useGetEventStats.ts";
import {formatDateWithLocale} from "../../../../utilites/dates.ts";
import {Skeleton} from "@mantine/core";
import {useMediaQuery} from "@mantine/hooks";
import {useGetAccount} from "../../../../queries/useGetAccount.ts";
import {useDisclosure} from "@mantine/hooks";
import {useEffect, useMemo, useRef, useState} from 'react';
import {EventLifecycleStatus, EventStatus, EventType} from "../../../../types.ts";
import {UpcomingOccurrences} from "./UpcomingOccurrences";
import {NextOccurrenceHero} from "./NextOccurrenceHero";
import {trackEvent, AnalyticsEvents} from "../../../../utilites/analytics.ts";
import {useGetOrganizer} from "../../../../queries/useGetOrganizer.ts";
import {useGetEventProductCategories} from "../../../../queries/useGetProductCategories.ts";
import {useGetEventImages} from "../../../../queries/useGetEventImages.ts";
import {useGetEventOccurrences} from "../../../../queries/useGetEventOccurrences.ts";
import {PeriodSelector, PeriodPreset} from "../../../common/PeriodSelector";
import {periodPresetToDateRange} from "../../../../utilites/periodPreset.ts";
import {hasEventDetails, SetupChecklist} from "./SetupChecklist";
import {PublishEventModal} from "../../../modals/PublishEventModal";
import {EventLiveCelebrationModal} from "../../../modals/EventLiveCelebrationModal";
import {eventHomepageUrl} from "../../../../utilites/urlHelper.ts";

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

    const defaultDateRangeRef = useRef<PeriodPreset | null>(null);
    if (event && !defaultDateRangeRef.current) {
        defaultDateRangeRef.current = (event.lifecycle_status === EventLifecycleStatus.ENDED
            || event.status === EventStatus.ARCHIVED) ? 'event_full' : 'last_30_days';
    }
    const [dateRangeOverride, setDateRange] = useState<PeriodPreset | null>(null);
    const effectiveDateRange: PeriodPreset = dateRangeOverride ?? defaultDateRangeRef.current ?? 'last_30_days';
    const {startDate, endDate} = useMemo(
        () => periodPresetToDateRange(effectiveDateRange, event),
        [effectiveDateRange, event],
    );

    const eventStatsQuery = useGetEventStats(eventId, {
        startDate,
        endDate,
        enabled: !!event && !!defaultDateRangeRef.current,
    });
    const {data: eventStats} = eventStatsQuery;
    const isMobile = useMediaQuery('(max-width: 768px)');
    const {data: account, isFetched: accountIsFetched} = useGetAccount();
    const [publishModalOpened, {open: openPublishModal, close: closePublishModal}] = useDisclosure(false);
    const [celebrationOpened, {open: openCelebration, close: closeCelebration}] = useDisclosure(false);

    const [isChecklistDismissed, setIsChecklistDismissed] = useState(false);
    const [isMounted, setIsMounted] = useState(false);

    const organizerId = event?.organizer_id ?? event?.organizer?.id;
    const {data: organizer} = useGetOrganizer(organizerId);
    const isStripeConnected = !!organizer?.stripe_connect_setup_complete;
    const {data: productCategoriesResponse} = useGetEventProductCategories(eventId);
    const productCount = productCategoriesResponse?.data?.reduce(
        (sum, category) => sum + (category.products?.length ?? 0),
        0,
    ) ?? 0;
    const {data: eventImages} = useGetEventImages(eventId);
    const isRecurring = event?.type === EventType.RECURRING;
    const occurrencesQuery = useGetEventOccurrences(
        eventId,
        {pageNumber: 1, perPage: 1},
        isRecurring,
    );
    const hasOccurrences = (occurrencesQuery?.data?.data?.length ?? 0) > 0;
    const hasCoverImage = (eventImages?.length ?? 0) > 0;

    const isNewEvent = new URLSearchParams(location.search).get('new_event') === 'true';

    useEffect(() => {
        setIsMounted(true);
        if (typeof window === 'undefined' || !eventId) {
            return;
        }
        if (isNewEvent) {
            window.localStorage.removeItem('setupChecklistDismissed-' + eventId);
            setIsChecklistDismissed(false);
            return;
        }
        const dismissed = window.localStorage.getItem('setupChecklistDismissed-' + eventId);
        if (dismissed === 'true') {
            setIsChecklistDismissed(true);
        }
    }, [eventId, isNewEvent]);

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

    const handlePublish = () => {
        openPublishModal();
    };

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
        navigate(`/manage/event/${eventId}/products#create-product`);
    };

    const handleEditDetails = () => {
        if (!eventId) {
            return;
        }
        navigate(`/manage/event/${eventId}/settings`);
    };

    const handleSetupSchedule = () => {
        if (!eventId) {
            return;
        }
        navigate(`/manage/event/${eventId}/occurrences`);
    };

    const handleCustomizePage = () => {
        if (!eventId) {
            return;
        }
        navigate(`/manage/event/${eventId}/homepage-designer`);
    };

    const isSaasMode = !!account?.is_saas_mode_enabled;
    const allChecklistComplete = !!event
        && event.status === 'LIVE'
        && (!isSaasMode || isStripeConnected)
        && productCount > 0
        && hasEventDetails(event)
        && hasCoverImage
        && (!isRecurring || hasOccurrences)
        && (!isSaasMode || !!account?.is_account_email_confirmed);

    useEffect(() => {
        if (allChecklistComplete && isMounted && typeof window !== 'undefined' && eventId) {
            window.localStorage.removeItem('setupChecklistDismissed-' + eventId);
        }
    }, [allChecklistComplete, isMounted, eventId]);

    const dateRangeLabel = (() => {
        if (!event) return '';
        const startYear = new Date(startDate.replace(' ', 'T')).getFullYear();
        const endYear = new Date(endDate.replace(' ', 'T')).getFullYear();
        const startStr = formatDateWithLocale(startDate, 'chartDate', event.timezone);
        const endStr = formatDateWithLocale(endDate, 'chartDate', event.timezone);
        if (startYear !== endYear) {
            return `${startStr}, ${startYear} - ${endStr}, ${endYear}`;
        }
        return `${startStr} - ${endStr}`;
    })();

    const shouldShowChecklist = !!(event && accountIsFetched);

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
                {publishModalOpened && (
                    <PublishEventModal
                        opened={publishModalOpened}
                        onClose={closePublishModal}
                        event={event}
                        onSuccess={() => {
                            trackEvent(AnalyticsEvents.EVENT_PUBLISHED);
                            closePublishModal();
                            openCelebration();
                        }}
                    />
                )}

                <EventLiveCelebrationModal
                    opened={celebrationOpened}
                    onClose={closeCelebration}
                    url={eventHomepageUrl(event)}
                    eventTitle={event.title}
                    eventId={String(event.id)}
                />

                {shouldShowChecklist && (
                    <SetupChecklist
                        event={event}
                        organizer={organizer}
                        isStripeConnected={isStripeConnected}
                        productCount={productCount}
                        hasOccurrences={hasOccurrences}
                        eventImages={eventImages}
                        account={account}
                        me={me}
                        onPublish={handlePublish}
                        onConnectStripe={handleConnectStripe}
                        onAddTickets={handleAddTickets}
                        onEditDetails={handleEditDetails}
                        onSetupSchedule={handleSetupSchedule}
                        onCustomizePage={handleCustomizePage}
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
                        event={event}
                    />
                </div>

                <StatBoxes dateRange={effectiveDateRange} event={event}/>

                {event?.type === EventType.RECURRING && (
                    <UpcomingOccurrences eventId={eventId} event={event}/>
                )}

                <ProductSalesChartCard
                    dailyStats={eventStats?.daily_stats}
                    timezone={event.timezone}
                    dateRangeLabel={dateRangeLabel}
                    syncId="events"
                />

                <RevenueChartCard
                    dailyStats={eventStats?.daily_stats}
                    timezone={event.timezone}
                    currency={event.currency}
                    dateRangeLabel={dateRangeLabel}
                    syncId="events"
                />
            </>)}
        </PageBody>
    )
};

export default EventDashboard;
