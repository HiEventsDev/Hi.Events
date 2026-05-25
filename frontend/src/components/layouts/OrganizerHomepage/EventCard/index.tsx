import React from 'react';
import {Link} from "react-router";
import {Event, LocationType} from "../../../../types.ts";
import classes from './EventCard.module.scss';
import {formatDateWithLocale} from "../../../../utilites/dates.ts";
import {t} from "@lingui/macro";
import {isLightColor} from "@mantine/core";
import {formatCurrency} from "../../../../utilites/currency.ts";
import {eventHomepagePath, eventHomepageUrl} from "../../../../utilites/urlHelper.ts";
import {getProductsFromEvent} from "../../../../utilites/helpers.ts";
import {ShareComponent} from "../../../common/ShareIcon";
import dayjs from "dayjs";
import {IconCalendar, IconClock, IconMapPin, IconTicket, IconWifi} from '@tabler/icons-react';
import {resolveEventLocation} from "../../../../utilites/effectiveLocation.ts";
import {formatAddress} from "../../../../utilites/addressUtilities.ts";

interface EventCardProps {
    event: Event;
    primaryColor?: string;
}

const placeholderEmojis = ['🎉', '🎪', '🎸', '🎨', '🌟'];

export const EventCard: React.FC<EventCardProps> = ({event, primaryColor = '#8b5cf6'}) => {
    const dateTextColor = isLightColor(primaryColor) ? '#000000' : '#ffffff';

    const emojiIndex = event.id ? Number(event.id) % placeholderEmojis.length : 0;
    const placeholderEmoji = placeholderEmojis[emojiIndex];

    // Format dates using the event's timezone
    const startMonth = formatDateWithLocale(event.start_date, "monthShort", event.timezone);
    const startDay = formatDateWithLocale(event.start_date, "dayOfMonth", event.timezone);
    const startTime = formatDateWithLocale(event.start_date, "timeOnly", event.timezone);
    const endTime = event.end_date ? formatDateWithLocale(event.end_date, "timeOnly", event.timezone) : null;
    const prettyTimezone = formatDateWithLocale(event.start_date, "timezone", event.timezone);

    const isSameDay = event.end_date && event.start_date.substring(0, 10) === event.end_date.substring(0, 10);
    const endMonth = event.end_date ? formatDateWithLocale(event.end_date, "monthShort", event.timezone) : null;
    const endDay = event.end_date ? formatDateWithLocale(event.end_date, "dayOfMonth", event.timezone) : null;

    const coverImage = event.images?.find(img => img.type === 'EVENT_COVER');
    const occurrences = event.occurrences ?? [];
    const resolvedList = occurrences.length > 0
        ? occurrences.map(o => resolveEventLocation(event, o))
        : [resolveEventLocation(event, null)];
    const types = new Set<string>();
    const locationIds = new Set<string>();
    for (const r of resolvedList) {
        if (r) {
            types.add(r.type);
            if (r.location_id != null) locationIds.add(String(r.location_id));
        }
    }
    const effective = resolvedList[0];
    const hasMixedModes = types.size > 1;
    const hasMultipleLocations = locationIds.size > 1;
    const isOnlineEvent = effective?.type === LocationType.Online;
    const locationLabel: string | null = (() => {
        if (hasMixedModes) return t`Online & in-person`;
        if (isOnlineEvent) return t`Online`;
        if (hasMultipleLocations) return t`Multiple locations`;
        if (effective?.type !== LocationType.InPerson) return null;
        const city = effective.location?.structured_address?.city;
        const venueName = effective.location?.name || effective.location?.structured_address?.venue_name;
        const formatted = effective.location?.structured_address ? formatAddress(effective.location.structured_address) : '';
        return venueName ?? city ?? (formatted ? formatted : null);
    })();
    const location = !isOnlineEvent ? locationLabel : null;

    // Check if event is live
    const now = dayjs();
    const startDate = dayjs(event.start_date);
    const endDate = event.end_date ? dayjs(event.end_date) : startDate.add(2, 'hour');
    const isLive = now.isAfter(startDate) && now.isBefore(endDate);

    // Get products from event categories
    const products = getProductsFromEvent(event) || [];

    // Calculate price range from products
    let lowestPrice: number | null = null;
    let highestPrice: number | null = null;

    products.forEach(product => {
        if (product.prices && product.prices.length > 0) {
            product.prices.forEach(price => {
                const priceValue = price.price || 0;
                if (lowestPrice === null || priceValue < lowestPrice) {
                    lowestPrice = priceValue;
                }
                if (highestPrice === null || priceValue > highestPrice) {
                    highestPrice = priceValue;
                }
            });
        } else {
            const priceValue = product.price || 0;
            if (lowestPrice === null || priceValue < lowestPrice) {
                lowestPrice = priceValue;
            }
            if (highestPrice === null || priceValue > highestPrice) {
                highestPrice = priceValue;
            }
        }
    });

    const eventPath = eventHomepagePath(event);

    return (
        <Link to={eventPath} className={classes.eventCardLink}>
            <article className={classes.eventCard}>
                {/* Image Section */}
                <div className={classes.eventImage}>
                    <div className={classes.imageWrapper}>
                        {coverImage ? (
                            <img
                                src={coverImage.url}
                                alt={event.title}
                                loading="lazy"
                            />
                        ) : (
                            <div className={classes.placeholderImage}
                                 style={{'--date-text-color': dateTextColor} as React.CSSProperties}>
                                <div className={classes.placeholderContent}>
                                    <span className={classes.placeholderIcon}>{placeholderEmoji}</span>
                                    <div className={classes.sparkles}>
                                        <span className={classes.sparkle}>✨</span>
                                        <span className={classes.sparkle}>✨</span>
                                        <span className={classes.sparkle}>✨</span>
                                    </div>
                                </div>
                            </div>
                        )}

                        {/* Floating elements on image */}
                        <div className={classes.imageOverlay}>
                            {isLive && (
                                <div className={classes.liveIndicator}>
                                    <span className={classes.liveDot}></span>
                                    <span className={classes.liveText}>{t`LIVE`}</span>
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

                    <div className={classes.dateBadge}>
                        <IconCalendar size={16}/>
                        <span>{startMonth} {startDay}</span>
                    </div>
                </div>

                {/* Content Section */}
                <div className={classes.eventContent}>
                    <div className={classes.eventHeader}>
                        <h3 className={classes.eventTitle}>{event.title}</h3>

                        <div className={classes.eventDateTime}>
                            <IconClock size={14}/>
                            <span>
                                {startTime}
                                {endTime && (
                                    <>
                                        {!isSameDay
                                            ? ` - ${endMonth} ${endDay}, ${endTime}`
                                            : ` - ${endTime}`
                                        }
                                    </>
                                )}
                                {prettyTimezone && (
                                    <span title={event.timezone} className={classes.timezone}> ({prettyTimezone})</span>
                                )}
                            </span>
                        </div>
                    </div>

                    {event.description_preview && (
                        <p className={classes.eventDescription}>
                            {event.description_preview}
                        </p>
                    )}

                    <div className={classes.eventFooter}>
                        <div className={classes.eventMeta}>
                            {(location || isOnlineEvent) && (
                                <div className={classes.location}>
                                    {isOnlineEvent ? (
                                        <><IconWifi size={14}/><span>{t`Online Event`}</span></>
                                    ) : (
                                        <><IconMapPin size={14}/><span>{location}</span></>
                                    )}
                                </div>
                            )}
                        </div>

                        {lowestPrice !== null && (
                            <div className={classes.priceSection}>
                                <IconTicket size={14}/>
                                <span className={lowestPrice === 0 && highestPrice === 0 ? classes.free : classes.price}>
                                    {lowestPrice === 0 && highestPrice === 0 ? (
                                        t`Free`
                                    ) : highestPrice !== null && highestPrice !== lowestPrice ? (
                                        `${formatCurrency(lowestPrice, event.currency)} - ${formatCurrency(highestPrice, event.currency)}`
                                    ) : (
                                        formatCurrency(lowestPrice, event.currency)
                                    )}
                                </span>
                            </div>
                        )}
                    </div>
                </div>
            </article>
        </Link>
    );
};
