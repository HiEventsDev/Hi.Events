import classes from "./EventHomepage.module.scss";
import {EventLineupSection, EventScheduleSection} from "./Lineup";
import {CustomHomepageSection} from "./Sections";
import {resolveHomepageSections} from "../../../utilites/homepageSections.ts";
import {initialSelectedOccurrence} from "../../../utilites/lineupSchedule.ts";
import SelectProducts from "../../routes/product-widget/SelectProducts";
import {defaultOccurrence} from "../../../utilites/occurrenceDefaults.ts";
import "../../../styles/widget/default.scss";
import {CSSProperties, Fragment, useCallback, useEffect, useRef, useState} from "react";
import {EventDocumentHead} from "../../common/EventDocumentHead";
import {eventCoverImage} from "../../../utilites/urlHelper.ts";
import {Event, EventLifecycleStatus, EventOccurrence, EventType, HomepageSection, HomepageSectionType, OrganizerStatus} from "../../../types.ts";
import {EventNotAvailable} from "./EventNotAvailable";
import {t} from "@lingui/macro";
import {ContactOrganizerModal} from "../../common/ContactOrganizerModal";
import {buildEventLocationDisplay, summariseEventLocations} from "../../../utilites/effectiveLocation.ts";
import {StatusToggle} from "../../common/StatusToggle";
import {validateThemeSettings} from "../../../utilites/themeUtils.ts";
import {useOrganizerTrackingPixels} from "../../../hooks/useOrganizerTrackingPixels";
import {hasActivePixels, trackPixelEvent} from "../../../utilites/trackingPixels";
import {formatDateWithLocale, isSameDayInTimezone} from "../../../utilites/dates.ts";
import {formatCurrency} from "../../../utilites/currency.ts";
import {summariseEventPrice, summariseEventSchedule} from "../../../utilites/eventListing.ts";
import {eventPriceLabel, eventScheduleLabel} from "../../../utilites/eventListingLabels.ts";
import {UserGeneratedContent} from "../../common/UserGeneratedContent";
import {HomepageFrame} from "../HomepageFrame";
import {CoverMedia} from "../HomepageFrame/CoverMedia.tsx";
import {DatePoster} from "../HomepageFrame/DatePoster.tsx";
import {MoreEventsSection} from "./MoreEventsSection.tsx";
import {EventHost, EventIntro, IntroStatus} from "./EventIntro.tsx";
import {LocationSection} from "./LocationSection.tsx";
import {OrganizerSection} from "./OrganizerSection.tsx";
import {SectionNav} from "./SectionNav.tsx";
import {CheckoutBar, CheckoutBarMode} from "./CheckoutBar.tsx";
import {useInViewport, useStickyRail} from "./hooks.ts";
import {scrollBehavior} from "../../../utilites/motion.ts";
import {resolveEventLayout} from "../../../constants/eventLayouts.ts";

interface EventHomepageProps {
    event?: Event;
    promoCodeValid?: boolean;
    promoCode?: string;
    initialOccurrenceId?: number | null;
    isPreview?: boolean;
}

const EventHomepage = ({...loaderData}: EventHomepageProps) => {
    const {event, promoCodeValid, promoCode, initialOccurrenceId, isPreview = false} = loaderData;
    const [contactModalOpen, setContactModalOpen] = useState(false);
    const [selectedOccurrence, setSelectedOccurrence] = useState<EventOccurrence | undefined>(
        () => initialOccurrenceId || !event
            ? initialSelectedOccurrence(event?.occurrences, initialOccurrenceId)
            : initialSelectedOccurrence(event.occurrences, null) ?? defaultOccurrence(event),
    );
    const occurrenceSelectionReported = useRef(false);
    const [selectedCart, setSelectedCart] = useState({quantity: 0, total: 0});
    const [continueButtonNode, setContinueButtonNode] = useState<HTMLButtonElement | null>(null);
    const [ticketsNode, setTicketsNode] = useState<HTMLDivElement | null>(null);
    const [contentNode, setContentNode] = useState<HTMLDivElement | null>(null);
    const ticketsInView = useInViewport(ticketsNode, 0);
    const continueButtonInView = useInViewport(continueButtonNode, 0.5);
    const [footerNode, setFooterNode] = useState<HTMLElement | null>(null);
    const footerInView = useInViewport(footerNode, 0);
    const layout = resolveEventLayout(event?.settings?.homepage_theme_settings?.layout);
    useStickyRail(layout.ticketRail ? ticketsNode : null);

    const handleSelectedOccurrenceChange = useCallback((occurrence?: EventOccurrence) => {
        if (!occurrence && !occurrenceSelectionReported.current) {
            return;
        }
        occurrenceSelectionReported.current = true;
        setSelectedOccurrence(occurrence);
    }, []);

    const handleCartChange = useCallback(
        (cart: {quantity: number; total: number}) => setSelectedCart(cart),
        [],
    );

    const {pixelsReady} = useOrganizerTrackingPixels(
        event?.organizer?.settings?.tracking_pixels
    );

    useEffect(() => {
        if (event && pixelsReady && hasActivePixels()) {
            trackPixelEvent({
                eventName: 'ViewContent',
                contentName: event.title,
                contentId: event.id,
            });
        }
    }, [event?.id, pixelsReady]);

    if (!event) {
        return <EventNotAvailable/>;
    }

    const themeSettings = validateThemeSettings(event.settings?.homepage_theme_settings);
    const tz = event.timezone;
    const cover = eventCoverImage(event);
    const organizer = event.organizer!;
    const organizerLogo = organizer.images?.find(image => image.type === 'ORGANIZER_LOGO')?.url;
    const organizerIsLive = organizer.status === OrganizerStatus.LIVE;

    const locationSummary = summariseEventLocations(event);
    const location = locationSummary.kind === 'single'
        ? buildEventLocationDisplay(event, locationSummary.eventLocation, locationSummary.isEventDefault)
        : null;
    const hasVenue = !!location && !location.isOnline && !!(location.venueName || location.full);
    const variedLocationLabel = locationSummary.kind !== 'varied'
        ? null
        : locationSummary.types.length > 1 ? t`Online & in-person — see schedule` : t`Multiple locations`;

    const schedule = summariseEventSchedule(event);
    const hasUpcomingDate = event.type !== EventType.RECURRING
        || !!event.next_occurrence_start_date
        || (event.occurrences ?? []).some(occurrence => !occurrence.is_past);
    const showsSingleDate = !!selectedOccurrence || (!schedule.isSeriesRange && hasUpcomingDate);
    const dateStart = selectedOccurrence?.start_date ?? (showsSingleDate ? schedule.start : null);
    const dateEnd = selectedOccurrence ? (selectedOccurrence.end_date ?? null) : schedule.end;
    const seriesLabel = !showsSingleDate && schedule.isSeriesRange && schedule.start ? eventScheduleLabel(schedule, tz) : null;
    const hasLaterDates = !!event.last_occurrence_date && !!event.next_occurrence_start_date
        && !isSameDayInTimezone(event.last_occurrence_date, event.next_occurrence_start_date, tz);
    const lastOccurrenceDate = hasLaterDates
        ? formatDateWithLocale(event.last_occurrence_date!, 'shortDate', tz)
        : null;

    const eventHasEnded = event.lifecycle_status === EventLifecycleStatus.ENDED;
    const products = event.products || event.product_categories?.flatMap(category => category.products || []) || [];
    const status: IntroStatus | null = eventHasEnded
        ? {label: t`This event has ended`, tone: 'muted'}
        : products.length > 0 && products.every(product => product.is_sold_out)
            ? {label: t`Sold Out`, tone: 'muted'}
            : event.lifecycle_status === EventLifecycleStatus.ONGOING ? {label: t`Happening now`, tone: 'live'} : null;
    const ticketRail = layout.ticketRail && !eventHasEnded;
    const sheetWidth = layout.ticketRail && !ticketRail ? 'medium' : layout.width;

    const price = eventHasEnded ? null : summariseEventPrice(event);
    const priceLabel = price ? eventPriceLabel(price, event.currency) : null;
    const shortDateLabel = dateStart
        ? `${formatDateWithLocale(dateStart, 'weekdayDate', tz)} · ${formatDateWithLocale(dateStart, 'timeOnly', tz)}`
        : null;

    const getTicketsButtonText = event.settings?.get_tickets_button_text || t`Get Tickets`;
    const continueButtonText = event.settings?.continue_button_text || t`Continue`;
    const hasCart = selectedCart.quantity > 0 && !!continueButtonNode;
    const checkoutBarMode: CheckoutBarMode | null = footerInView
        ? null
        : hasCart
            ? (continueButtonInView === false ? 'continue' : null)
            : (ticketsInView === false && !eventHasEnded && price?.kind !== 'soldOut' ? 'tickets' : null);

    const homepageSections = resolveHomepageSections(event.settings?.homepage_sections)
        .filter(section => section.type === 'TICKETS' ? !eventHasEnded : section.enabled);
    const sectionHeading = (type: HomepageSectionType, fallback: string) =>
        homepageSections.find(section => section.type === type)?.heading || fallback;
    const sectionClasses = {
        section: classes.section,
        sectionHeader: classes.sectionHeader,
        sectionTitle: classes.sectionTitle,
    };
    const lineupOccurrence = event.type === EventType.RECURRING ? selectedOccurrence : undefined;
    const mainRowCount = 1 + homepageSections.filter(section => section.type !== 'TICKETS').length;

    const renderSection = (section: HomepageSection) => {
        switch (section.type) {
            case 'TICKETS':
                return (
                    <div
                        ref={setTicketsNode}
                        id="tickets"
                        role="region"
                        aria-label={t`Tickets`}
                        tabIndex={-1}
                        className={classes.tickets}
                    >
                        <div className={classes.ticketsCard}>
                            <SelectProducts
                                colors={{
                                    background: "transparent",
                                    primary: "var(--event-primary-color)",
                                    primaryText: "var(--event-primary-text-color)",
                                    secondary: "var(--event-primary-color)",
                                    secondaryText: "var(--event-accent-contrast)",
                                    bodyBackground: "var(--event-bg-color)",
                                }}
                                continueButtonText={event.settings?.continue_button_text}
                                padding={"0px"}
                                event={event}
                                promoCodeValid={promoCodeValid}
                                promoCode={promoCode}
                                showPoweredBy={false}
                                initialOccurrenceId={initialOccurrenceId}
                                onSelectedOccurrenceChange={handleSelectedOccurrenceChange}
                                onCartChange={handleCartChange}
                                continueButtonRef={setContinueButtonNode}
                            />
                        </div>
                    </div>
                );
            case 'ABOUT':
                return event.description ? (
                    <section className={classes.section} id="about">
                        <div className={classes.sectionHeader}>
                            <h2 className={classes.sectionTitle}>{sectionHeading('ABOUT', t`About`)}</h2>
                        </div>
                        <UserGeneratedContent className={classes.description} html={event.description}/>
                    </section>
                ) : null;
            case 'LINEUP':
                return <EventLineupSection event={event} occurrence={lineupOccurrence} heading={section.heading} sectionClasses={sectionClasses}/>;
            case 'SCHEDULE':
                return <EventScheduleSection event={event} occurrence={lineupOccurrence} heading={section.heading} sectionClasses={sectionClasses}/>;
            case 'LOCATION':
                return hasVenue ? <LocationSection heading={sectionHeading('LOCATION', t`Location`)} location={location!}/> : null;
            case 'ORGANIZER':
                return organizerIsLive ? (
                    <OrganizerSection
                        heading={sectionHeading('ORGANIZER', t`Organizer`)}
                        organizer={organizer}
                        logo={organizerLogo}
                        onContact={() => setContactModalOpen(true)}
                    />
                ) : null;
            case 'MORE_EVENTS':
                return organizerIsLive
                    ? <MoreEventsSection heading={section.heading} organizer={organizer} currentEventId={event.id!}/>
                    : null;
            default:
                return <CustomHomepageSection images={event.images ?? []} section={section} sectionClasses={sectionClasses}/>;
        }
    };

    const hostProps = {
        event,
        organizer,
        organizerLogo,
        occurrence: selectedOccurrence,
        canAddToCalendar: event.type !== EventType.RECURRING || !!selectedOccurrence,
    };
    const intro = (
        <EventIntro
            {...hostProps}
            showHost={!layout.poster}
            centered={layout.centeredIntro}
            dateStart={dateStart}
            dateEnd={dateEnd}
            seriesLabel={seriesLabel}
            lastOccurrenceDate={event.type === EventType.RECURRING ? lastOccurrenceDate : null}
            status={status}
            location={location}
            locationDetailsBelow={hasVenue && homepageSections.some(section => section.type === 'LOCATION')}
            variedLocationLabel={variedLocationLabel}
        />
    );
    const sections = homepageSections.map(section => (
        <Fragment key={section.id}>{renderSection(section)}</Fragment>
    ));

    return (
        <>
            {!isPreview && event.status && event.id && (
                <StatusToggle
                    entityType="event"
                    entityId={event.id}
                    currentStatus={event.status as 'DRAFT' | 'LIVE' | 'PENDING_MANUAL_REVIEW'}
                    entityName={event.title}
                    onSuccess={() =>
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000)}
                />
            )}

            <HomepageFrame
                themeSettings={themeSettings}
                mirrorImageUrl={cover?.url}
                footerSource="app-event-footer"
                footerRef={setFooterNode}
                width={sheetWidth}
                header={layout.sectionNav && <SectionNav container={contentNode} title={event.title} subtitle={shortDateLabel} thumbnail={cover?.url}/>}
                outside={(
                    <>
                        <CheckoutBar
                            mode={checkoutBarMode}
                            compactOnWide={ticketRail}
                            priceLabel={priceLabel}
                            dateLabel={shortDateLabel}
                            getTicketsText={getTicketsButtonText}
                            continueText={selectedCart.total > 0
                                ? `${continueButtonText} (${formatCurrency(selectedCart.total, event.currency)})`
                                : continueButtonText}
                            onGetTickets={() => {
                                ticketsNode?.scrollIntoView({behavior: scrollBehavior(), block: 'start'});
                                ticketsNode?.focus({preventScroll: true});
                            }}
                            onContinue={() => continueButtonNode?.click()}
                        />
                        <ContactOrganizerModal
                            opened={contactModalOpen}
                            onClose={() => setContactModalOpen(false)}
                            organizer={organizer}
                        />
                    </>
                )}
            >
                <EventDocumentHead event={event}/>

                {!layout.poster && cover && <CoverMedia image={cover} alt={event.title}/>}

                <div
                    ref={layout.poster ? undefined : setContentNode}
                    className={classes.body}
                    data-has-cover={!layout.poster && cover ? '' : undefined}
                    data-rail={ticketRail || undefined}
                    data-poster={layout.poster || undefined}
                    style={ticketRail ? {'--main-rows': mainRowCount} as CSSProperties : undefined}
                >
                    {layout.poster ? (
                        <>
                            <div className={classes.posterColumn}>
                                {cover && <CoverMedia image={cover} alt={event.title} variant="poster"/>}
                                {!cover && (dateStart ?? schedule.start) && <DatePoster date={(dateStart ?? schedule.start)!} tz={tz}/>}
                                <EventHost {...hostProps}/>
                            </div>
                            <div ref={setContentNode} className={classes.mainColumn}>
                                {intro}
                                {sections}
                            </div>
                        </>
                    ) : (
                        <>
                            {intro}
                            {sections}
                        </>
                    )}
                </div>
            </HomepageFrame>
        </>
    );
};

export default EventHomepage;
