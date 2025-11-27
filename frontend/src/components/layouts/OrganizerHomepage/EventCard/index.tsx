import React from 'react';
import {Link} from "react-router";
import {Event} from "../../../../types.ts";
import classes from './EventCard.module.scss';
import {formatDate} from "../../../../utilites/dates.ts";
import {t} from "@lingui/macro";
import {isLightColor} from "@mantine/core";
import {formatCurrency} from "../../../../utilites/currency.ts";
import {eventHomepagePath, eventHomepageUrl} from "../../../../utilites/urlHelper.ts";
import {getProductsFromEvent} from "../../../../utilites/helpers.ts";
import {ShareComponent} from "../../../common/ShareIcon";
import dayjs from "dayjs";
import {IconCalendar, IconClock, IconMapPin, IconTicket, IconWifi} from '@tabler/icons-react';

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
    const startMonth = formatDate(event.start_date, "MMM", event.timezone);
    const startDay = formatDate(event.start_date, "D", event.timezone);
    const startTime = formatDate(event.start_date, "h:mm A", event.timezone);
    const endTime = event.end_date ? formatDate(event.end_date, "h:mm A", event.timezone) : null;
    const prettyTimezone = formatDate(event.start_date, "z", event.timezone);

    const isSameDay = event.end_date && event.start_date.substring(0, 10) === event.end_date.substring(0, 10);
    const endMonth = event.end_date ? formatDate(event.end_date, "MMM", event.timezone) : null;
    const endDay = event.end_date ? formatDate(event.end_date, "D", event.timezone) : null;

    const coverImage = event.images?.find(img => img.type === 'EVENT_COVER');
    const location = event?.settings?.location_details?.city || event?.settings?.location_details?.venue_name;
    const isOnlineEvent = event.settings?.is_online_event;

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
