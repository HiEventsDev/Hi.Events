import React from 'react';
import {Link} from "react-router";
import {Event, LocationType} from "../../../../types.ts";
import classes from './EventCard.module.scss';
import {formatDateWithLocale, isSameDayInTimezone} from "../../../../utilites/dates.ts";
import {t} from "@lingui/macro";
import {formatCurrency} from "../../../../utilites/currency.ts";
import {eventHomepagePath, eventHomepageUrl} from "../../../../utilites/urlHelper.ts";
import {ShareComponent} from "../../../common/ShareIcon";
import dayjs from "dayjs";
import utc from "dayjs/plugin/utc";
import timezone from "dayjs/plugin/timezone";
import {IconCalendar, IconCalendarRepeat, IconMapPin, IconTicket, IconWifi} from '@tabler/icons-react';
import {summariseEventLocations} from "../../../../utilites/effectiveLocation.ts";
import {formatAddress} from "../../../../utilites/addressUtilities.ts";
import {EventPriceSummary, summariseEventPrice, summariseEventSchedule} from "../../../../utilites/eventListing.ts";

dayjs.extend(utc);
dayjs.extend(timezone);

interface EventCardProps {
    event: Event;
}

const formatDay = (date: string, tz: string, withYear: boolean): string =>
    formatDateWithLocale(date, withYear ? "shortDate" : "weekdayDate", tz);

const priceLabel = (price: EventPriceSummary, currency: string): string => {
    switch (price.kind) {
        case 'soldOut':
            return t`Sold out`;
        case 'free':
            return t`Free`;
        case 'donation':
            return t`Donation`;
        case 'exact':
            return formatCurrency(price.amount, currency);
        case 'from': {
            if (price.amount === 0) {
                return t`From free`;
            }
            const amount = formatCurrency(price.amount, currency);
            return t`From ${amount}`;
        }
    }
};

export const EventCard: React.FC<EventCardProps> = ({event}) => {
    const schedule = summariseEventSchedule(event);
    const price = schedule.hasEnded ? null : summariseEventPrice(event);
    const tz = event.timezone;

    const dateLine = (() => {
        const {start, end} = schedule;
        if (!start) {
            return t`Date TBA`;
        }
        if (schedule.isSeriesRange) {
            const firstDay = formatDay(start, tz, true);
            return end ? `${firstDay} – ${formatDay(end, tz, true)}` : firstDay;
        }

        const withYear = schedule.hasEnded || dayjs.utc(start).tz(tz).year() !== dayjs().tz(tz).year();
        const startLabel = `${formatDay(start, tz, withYear)} · ${formatDateWithLocale(start, "timeOnly", tz)}`;
        if (!end) {
            return startLabel;
        }
        const endTime = formatDateWithLocale(end, "timeOnly", tz);

        return isSameDayInTimezone(start, end, tz)
            ? `${startLabel} – ${endTime}`
            : `${startLabel} – ${formatDay(end, tz, withYear)}, ${endTime}`;
    })();
    const prettyTimezone = schedule.start && !schedule.isSeriesRange
        ? formatDateWithLocale(schedule.start, "timezone", tz)
        : null;
    const moreDatesCount = schedule.additionalDateCount;

    const coverImage = event.images?.find(img => img.type === 'EVENT_COVER');
    const locationSummary = summariseEventLocations(event);
    const isOnlineEvent = locationSummary.kind === 'single' && locationSummary.eventLocation.type === LocationType.Online;
    const locationLabel: string | null = (() => {
        if (locationSummary.kind === 'none') return null;
        if (locationSummary.kind === 'varied') {
            return locationSummary.types.length > 1 ? t`Online & in-person` : t`Multiple locations`;
        }
        const eventLocation = locationSummary.eventLocation;
        if (eventLocation.type === LocationType.Online) return t`Online`;
        const city = eventLocation.location?.structured_address?.city;
        const venueName = eventLocation.location?.name || eventLocation.location?.structured_address?.venue_name;
        const formatted = eventLocation.location?.structured_address ? formatAddress(eventLocation.location.structured_address) : '';
        return venueName ?? city ?? (formatted ? formatted : null);
    })();
    const location = !isOnlineEvent ? locationLabel : null;

    const eventPath = eventHomepagePath(event);

    return (
        <Link to={eventPath} className={classes.eventCardLink}>
            <article className={`${classes.eventCard}${schedule.hasEnded ? ` ${classes.eventCardEnded}` : ''}`}>
                <div className={classes.eventImage}>
                    <div className={classes.imageWrapper}>
                        {coverImage ? (
                            <img
                                src={coverImage.url}
                                alt={event.title}
                                loading="lazy"
                            />
                        ) : (
                            <div className={classes.placeholderImage} aria-hidden="true">
                                {schedule.start ? (
                                    <div className={classes.placeholderDate}>
                                        <span className={classes.placeholderMonth}>
                                            {formatDateWithLocale(schedule.start, "monthShort", tz)}
                                        </span>
                                        <span className={classes.placeholderDay}>
                                            {formatDateWithLocale(schedule.start, "dayOfMonth", tz)}
                                        </span>
                                    </div>
                                ) : (
                                    <IconCalendar size={32}/>
                                )}
                            </div>
                        )}

                        <div className={classes.imageOverlay}>
                            {schedule.isHappeningNow && (
                                <div className={classes.liveIndicator}>
                                    <span className={classes.liveDot}></span>
                                    <span className={classes.liveText}>{t`Happening now`}</span>
                                </div>
                            )}
                            <div className={classes.shareButton} onClick={(e) => e.preventDefault()}>
                                <ShareComponent
                                    title={event.title}
                                    text={event.description_preview || ''}
                                    url={eventHomepageUrl(event)}
                                    hideShareButtonText={true}
                                    className={classes.shareIcon}
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <div className={classes.eventContent}>
                    <div className={classes.eventHeader}>
                        <div className={classes.eventDate} data-testid="organizer-event-card-date">
                            {dateLine}
                            {prettyTimezone && (
                                <span title={event.timezone} className={classes.timezone}> ({prettyTimezone})</span>
                            )}
                        </div>
                        <h3 className={classes.eventTitle}>{event.title}</h3>
                    </div>

                    {event.description_preview && (
                        <p className={classes.eventDescription}>
                            {event.description_preview}
                        </p>
                    )}

                    <div className={classes.eventFooter}>
                        <div className={classes.eventMeta}>
                            {(location || isOnlineEvent) && (
                                <div className={classes.metaItem}>
                                    {isOnlineEvent ? (
                                        <><IconWifi size={14}/><span>{t`Online Event`}</span></>
                                    ) : (
                                        <><IconMapPin size={14}/><span>{location}</span></>
                                    )}
                                </div>
                            )}
                            {moreDatesCount > 0 && (
                                <div className={classes.metaItem}>
                                    <IconCalendarRepeat size={14}/>
                                    <span>
                                        {moreDatesCount === 1 ? t`+1 more date` : t`+${moreDatesCount} more dates`}
                                    </span>
                                </div>
                            )}
                        </div>

                        {price && (
                            <div
                                className={`${classes.priceSection}${price.kind === 'soldOut' ? ` ${classes.priceSoldOut}` : ''}`}
                                data-testid="organizer-event-card-price"
                            >
                                {price.kind !== 'soldOut' && <IconTicket size={14}/>}
                                <span>{priceLabel(price, event.currency)}</span>
                            </div>
                        )}
                    </div>
                </div>
            </article>
        </Link>
    );
};
