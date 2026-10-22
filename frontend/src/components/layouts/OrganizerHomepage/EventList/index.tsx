import {useId} from "react";
import {Link} from "react-router";
import {plural, t} from "@lingui/macro";
import {IconArrowRight, IconCalendarRepeat, IconMapPin, IconWorld} from "@tabler/icons-react";
import {Event, Image, LocationType} from "../../../../types.ts";
import {eventHomepagePath} from "../../../../utilites/urlHelper.ts";
import {formatDate, formatDateWithLocale, formatMonthHeading} from "../../../../utilites/dates.ts";
import {formatAddress} from "../../../../utilites/addressUtilities.ts";
import {summariseEventLocations} from "../../../../utilites/effectiveLocation.ts";
import {summariseEventPrice, summariseEventSchedule} from "../../../../utilites/eventListing.ts";
import {eventPriceLabel, eventScheduleLabel} from "../../../../utilites/eventListingLabels.ts";
import {decodeHtmlEntities} from "../../../../utilites/htmlEntities.ts";
import {groupConsecutive} from "../../../../utilites/groupConsecutive.ts";
import {responsiveImage} from "../../../../utilites/imageTransform.ts";
import {DateTile} from "../../HomepageFrame/DateTile.tsx";
import classes from "./EventList.module.scss";

const MONTH_KEY = 'YYYY-MM';

const latestOccurrenceStart = (event: Event): string | null => (event.occurrences ?? [])
    .map(occurrence => occurrence.start_date)
    .reduce<string | null>((latest, start) => !latest || start > latest ? start : latest, null);

const anchorDate = (event: Event, past: boolean, schedule = summariseEventSchedule(event)): string | null => past
    ? latestOccurrenceStart(event) ?? event.end_date ?? schedule.start
    : schedule.start;

const listingDetails = (event: Event, past: boolean) => {
    const schedule = summariseEventSchedule(event);
    const price = schedule.hasEnded ? null : summariseEventPrice(event);
    const locations = summariseEventLocations(event);
    const isOnline = locations.kind === 'single' && locations.eventLocation.type === LocationType.Online;
    const venue = (() => {
        if (locations.kind === 'none' || isOnline) {
            return null;
        }
        if (locations.kind === 'varied') {
            return locations.types.length > 1 ? t`Online & in-person` : t`Multiple locations`;
        }
        const address = locations.eventLocation.location?.structured_address;
        const venueName = locations.eventLocation.location?.name || address?.venue_name;
        return venueName ?? address?.city ?? (address ? formatAddress(address) || null : null);
    })();

    return {
        schedule,
        price,
        isOnline,
        venue,
        anchorDate: anchorDate(event, past, schedule),
        cover: event.images?.find(image => image.type === 'EVENT_COVER'),
        dateLine: eventScheduleLabel(schedule, event.timezone),
        timezone: schedule.start && !schedule.isSeriesRange ? formatDateWithLocale(schedule.start, 'timezone', event.timezone) : null,
    };
};

type ListingDetails = ReturnType<typeof listingDetails>;

const Artwork = ({image, sizes, lazy}: { image: Image, sizes: string, lazy?: boolean }) => (
    <>
        {image.lqip_base64 && <img src={image.lqip_base64} alt="" aria-hidden="true" className={classes.artworkFill}/>}
        <img {...responsiveImage(image.url, sizes)} alt="" className={classes.artwork} loading={lazy ? 'lazy' : undefined} decoding="async"/>
    </>
);

const DateLine = ({event, details, id}: { event: Event, details: ListingDetails, id: string }) => (
    <div className={classes.date} id={id} data-testid="organizer-event-card-date">
        {details.dateLine}
        {details.timezone && (
            <span title={event.timezone} className={classes.timezone} suppressHydrationWarning>{` (${details.timezone})`}</span>
        )}
    </div>
);

const Price = ({event, details}: { event: Event, details: ListingDetails }) => details.price ? (
    <span className={classes.price} data-sold-out={details.price.kind === 'soldOut' || undefined} data-testid="organizer-event-card-price">
        {eventPriceLabel(details.price, event.currency)}
    </span>
) : null;

const LiveBadge = () => (
    <span className={classes.liveBadge}>
        <span className={classes.liveDot} aria-hidden="true"/>
        {t`Happening now`}
    </span>
);

const Meta = ({event, details, withPrice}: { event: Event, details: ListingDetails, withPrice?: boolean }) => {
    const moreDatesCount = details.schedule.additionalDateCount;
    const showPrice = withPrice && !!details.price;
    if (!details.isOnline && !details.venue && moreDatesCount === 0 && !showPrice && !details.schedule.isHappeningNow) {
        return null;
    }

    return (
        <div className={classes.meta}>
            {withPrice && details.schedule.isHappeningNow && <LiveBadge/>}
            {details.isOnline && <span className={classes.metaItem}><IconWorld/>{t`Online Event`}</span>}
            {details.venue && <span className={classes.metaItem}><IconMapPin/><span className={classes.metaText}>{details.venue}</span></span>}
            {moreDatesCount > 0 && (
                <span className={classes.metaItem}>
                    <IconCalendarRepeat/>
                    {plural(moreDatesCount, {one: '+# more date', other: '+# more dates'})}
                </span>
            )}
            {showPrice && <Price event={event} details={details}/>}
        </div>
    );
};

type RowTitleTag = 'h3' | 'h4';

const EventRow = ({event, past, titleAs: Title}: { event: Event, past: boolean, titleAs: RowTitleTag }) => {
    const details = listingDetails(event, past);
    const id = useId();

    return (
        <Link
            to={eventHomepagePath(event)}
            className={classes.row}
            data-ended={details.schedule.hasEnded || undefined}
            aria-labelledby={`${id}-title`}
            aria-describedby={`${id}-date`}
        >
            {details.anchorDate
                ? <DateTile date={details.anchorDate} tz={event.timezone} size="lg"/>
                : <span className={classes.tilePlaceholder} aria-hidden="true"/>}
            <div className={classes.rowBody}>
                <Title className={classes.title} id={`${id}-title`}>{event.title}</Title>
                <DateLine event={event} details={details} id={`${id}-date`}/>
                <Meta event={event} details={details} withPrice/>
            </div>
            {details.cover && (
                <span className={classes.thumb} style={{backgroundColor: details.cover.avg_colour ?? undefined}}>
                    <Artwork image={details.cover} sizes="160px" lazy/>
                </span>
            )}
        </Link>
    );
};

export const EventRows = ({events, past = false, titleAs = 'h4'}: { events: Event[], past?: boolean, titleAs?: RowTitleTag }) => (
    <div className={classes.rows}>
        {events.map(event => <EventRow key={event.id} event={event} past={past} titleAs={titleAs}/>)}
    </div>
);

export const EventList = ({events, past = false}: { events: Event[], past?: boolean }) => {
    const monthKey = (event: Event): string => {
        const anchor = anchorDate(event, past);
        return anchor ? formatDate(anchor, MONTH_KEY, event.timezone) : '';
    };

    return (
        <div className={classes.list}>
            {groupConsecutive(events, monthKey).map((group, index) => {
                const first = group.items[0];
                const anchor = anchorDate(first, past);

                return (
                    <div key={`${group.key}-${index}`}>
                        {anchor && <h3 className={classes.groupLabel}>{formatMonthHeading(anchor, first.timezone)}</h3>}
                        <EventRows events={group.items} past={past}/>
                    </div>
                );
            })}
        </div>
    );
};

export const EventSpotlight = ({event}: { event: Event }) => {
    const details = listingDetails(event, false);
    const start = details.schedule.start;
    const description = decodeHtmlEntities(event.description_preview || '');
    const id = useId();

    return (
        <Link to={eventHomepagePath(event)} className={classes.spotlight} aria-labelledby={`${id}-title`} aria-describedby={`${id}-date`}>
            <div className={classes.spotlightMedia} style={{backgroundColor: details.cover?.avg_colour ?? undefined}}>
                {details.cover
                    ? <Artwork image={details.cover} sizes="(min-width: 768px) 560px, 100vw"/>
                    : start && (
                        <div className={classes.spotlightArt} aria-hidden="true">
                            <span>{formatDateWithLocale(start, 'monthShort', event.timezone)}</span>
                            <strong>{formatDateWithLocale(start, 'dayOfMonth', event.timezone)}</strong>
                        </div>
                    )}
            </div>
            <div className={classes.spotlightBody}>
                <div className={classes.spotlightTop}>
                    <span className={classes.spotlightLabel}>{t`Next up`}</span>
                    {details.schedule.isHappeningNow && <LiveBadge/>}
                </div>
                <DateLine event={event} details={details} id={`${id}-date`}/>
                <h3 className={classes.spotlightTitle} id={`${id}-title`}>{event.title}</h3>
                {description && <p className={classes.description}>{description}</p>}
                <Meta event={event} details={details}/>
                <div className={classes.spotlightFooter}>
                    <Price event={event} details={details}/>
                    <span className={classes.cta} aria-hidden="true">
                        {event.settings?.get_tickets_button_text || t`Get Tickets`}
                        <IconArrowRight/>
                    </span>
                </div>
            </div>
        </Link>
    );
};
