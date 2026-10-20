import {useParams} from "react-router";
import '../../../styles/widget/default.scss';
import {useState} from "react";
import {Loader, SegmentedControl} from "@mantine/core";
import {t} from "@lingui/macro";
import {IconCalendar, IconMapPin} from "@tabler/icons-react";
import {Event, LocationType, Organizer} from "../../../types.ts";
import {useQuery} from "@tanstack/react-query";
import {AxiosError} from "axios";
import {getOrganizerPublicQuery} from "../../../queries/useGetOrganizerPublic.ts";
import {WIDGET_EVENT_STALE_TIME} from "../../../queries/useGetEventPublic.ts";
import {OrganizerWidgetEventsStatus, useGetOrganizerWidgetEvents} from "../../../queries/useGetOrganizerWidgetEvents.ts";
import {summariseEventPrice, summariseEventSchedule} from "../../../utilites/eventListing.ts";
import {eventPriceLabel, eventScheduleLabel} from "../../../utilites/eventListingLabels.ts";
import {summariseEventLocations} from "../../../utilites/effectiveLocation.ts";
import {eventCoverImage, eventHomepageUrl} from "../../../utilites/urlHelper.ts";
import {formatDateWithLocale} from "../../../utilites/dates.ts";
import {getEmbedParentUrl, getParentOrigin} from "../../../utilites/iframeResize.ts";
import {PREVIEW_OPEN_EVENT_MESSAGE, useWidgetAppearance, useWidgetRuntime} from "../../widget/useWidgetRuntime.ts";
import {WidgetFrame} from "../../widget/WidgetFrame";
import {WidgetMessage} from "../../widget/WidgetMessage";
import {forwardedHostParams, WidgetEventsLayout, WidgetRuntimeConfig} from "../../../utilites/widgetConfig.ts";
import classes from "./OrganizerEventsWidget.module.scss";

const locationLabel = (event: Event): string | null => {
    const summary = summariseEventLocations(event);
    if (summary.kind === 'none') return null;
    if (summary.kind === 'varied') {
        return summary.types.length > 1 ? t`Online & in-person` : t`Multiple locations`;
    }
    const eventLocation = summary.eventLocation;
    if (eventLocation.type === LocationType.Online) return t`Online`;
    const address = eventLocation.location?.structured_address;
    return eventLocation.location?.name || address?.venue_name || address?.city || null;
};

const openEvent = (event: Event, openIn: WidgetRuntimeConfig['open_in'], isPreview: boolean, search: URLSearchParams) => {
    if (openIn !== 'page' && isPreview) {
        window.parent.postMessage({type: PREVIEW_OPEN_EVENT_MESSAGE, eventId: event.id}, window.location.origin);
        return;
    }
    if (openIn !== 'page' && getEmbedParentUrl()) {
        window.parent.postMessage({type: 'hievents:open-widget', eventId: event.id}, getParentOrigin() || '*');
        return;
    }
    const hostParams = new URLSearchParams(forwardedHostParams(search)).toString();
    window.open(eventHomepageUrl(event) + (hostParams ? `?${hostParams}` : ''), '_blank', 'noopener');
};

const EventItem = ({event, layout, onOpen}: { event: Event; layout: WidgetEventsLayout; onOpen: (event: Event) => void }) => {
    const schedule = summariseEventSchedule(event);
    const price = schedule.hasEnded ? null : summariseEventPrice(event);
    const cover = eventCoverImage(event);
    const location = locationLabel(event);
    const actionLabel = schedule.hasEnded
        ? t`View event`
        : price?.kind === 'soldOut' ? t`Sold out` : t`Get tickets`;

    return (
        <li className={classes.item} data-layout={layout} data-ended={schedule.hasEnded ? '' : undefined}>
            <button type="button" className={classes.itemButton} onClick={() => onOpen(event)} data-testid="widget-event-item">
                {layout === 'compact' ? (
                    <span className={classes.dateBadge} aria-hidden>
                        {schedule.start ? (
                            <>
                                <span className={classes.dateBadgeMonth}>{formatDateWithLocale(schedule.start, 'monthShort', event.timezone)}</span>
                                <span className={classes.dateBadgeDay}>{formatDateWithLocale(schedule.start, 'dayOfMonth', event.timezone)}</span>
                            </>
                        ) : <IconCalendar size={18}/>}
                    </span>
                ) : (
                    <span className={classes.cover}>
                        {cover?.url
                            ? <img src={cover.url} alt="" loading="lazy" decoding="async"/>
                            : (
                                <span className={classes.coverPlaceholder} aria-hidden>
                                    {schedule.start ? (
                                        <>
                                            <span className={classes.dateBadgeMonth}>{formatDateWithLocale(schedule.start, 'monthShort', event.timezone)}</span>
                                            <span className={classes.placeholderDay}>{formatDateWithLocale(schedule.start, 'dayOfMonth', event.timezone)}</span>
                                        </>
                                    ) : <IconCalendar size={24}/>}
                                </span>
                            )}
                    </span>
                )}
                <span className={classes.body}>
                    <span className={classes.date}>{eventScheduleLabel(schedule, event.timezone)}</span>
                    <span className={classes.title}>{event.title}</span>
                    {location && (
                        <span className={classes.location}>
                            <IconMapPin size={13} aria-hidden/>
                            {location}
                        </span>
                    )}
                </span>
                <span className={classes.aside}>
                    {price && <span className={classes.price} data-sold-out={price.kind === 'soldOut' ? '' : undefined}>{eventPriceLabel(price, event.currency)}</span>}
                    <span className={classes.cta}>{actionLabel}</span>
                </span>
            </button>
        </li>
    );
};

const OrganizerEventsWidget = () => {
    const {organizerId} = useParams();
    const {config, search, isPreview, notFound} = useWidgetRuntime();
    const [status, setStatus] = useState<OrganizerWidgetEventsStatus>('upcoming');
    const organizerQuery = useQuery<Organizer, AxiosError>({
        ...getOrganizerPublicQuery(String(organizerId)),
        staleTime: WIDGET_EVENT_STALE_TIME,
        enabled: !!organizerId && !notFound,
    });
    const eventsQuery = useGetOrganizerWidgetEvents(String(organizerId), config, status, !!organizerId && !notFound);
    const appearance = useWidgetAppearance(config, organizerQuery.data?.settings?.homepage_theme_settings);
    const layout: WidgetEventsLayout = config.layout ?? 'list';
    const events = eventsQuery.data?.pages.flatMap(page => page.data) ?? [];

    if (notFound || eventsQuery.isError) {
        return (
            <WidgetFrame style={appearance.style} mode={appearance.mode}>
                <WidgetMessage title={t`These events are not available`}/>
            </WidgetFrame>
        );
    }

    return (
        <WidgetFrame style={appearance.style} mode={appearance.mode} testId="events-widget">
            {config.show_past && (
                <SegmentedControl
                    className={classes.tabs}
                    size="md"
                    value={status}
                    onChange={(value) => setStatus(value as OrganizerWidgetEventsStatus)}
                    data={[
                        {value: 'upcoming', label: t`Upcoming`},
                        {value: 'ended', label: t`Past`},
                    ]}
                />
            )}
            {eventsQuery.isLoading ? (
                <div className={'hi-widget-loading'}>
                    <Loader color={appearance.colors.primaryText} size="md" type="dots"/>
                </div>
            ) : events.length === 0 ? (
                <WidgetMessage
                    title={status === 'upcoming' ? t`No upcoming events` : t`No past events`}
                    description={status === 'upcoming' ? t`Check back soon for new dates.` : undefined}
                />
            ) : (
                <ul className={classes.list} data-layout={layout}>
                    {events.map(event => (
                        <EventItem key={event.id} event={event} layout={layout} onOpen={(item) => openEvent(item, config.open_in, isPreview, search)}/>
                    ))}
                </ul>
            )}
            {eventsQuery.hasNextPage && (
                <button
                    type="button"
                    className={classes.loadMore}
                    onClick={() => eventsQuery.fetchNextPage()}
                    disabled={eventsQuery.isFetchingNextPage}
                >
                    {eventsQuery.isFetchingNextPage ? t`Loading…` : t`Show more events`}
                </button>
            )}
        </WidgetFrame>
    );
};

export default OrganizerEventsWidget;
