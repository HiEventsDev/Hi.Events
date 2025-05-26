import React, { useState, useEffect } from 'react';
import { Event } from "../../../../types.ts";
import classes from './EventCard.module.scss';

interface EventCardProps {
    event: Event;
    onClick?: () => void;
}

export const EventCard: React.FC<EventCardProps> = ({ event, onClick }) => {
    const [timeUntilEvent, setTimeUntilEvent] = useState<string>('');
    const [isEventSoon, setIsEventSoon] = useState<boolean>(false);

    const startDate = new Date(event.start_date);
    const endDate = event.end_date ? new Date(event.end_date) : null;

    const formatDate = (date: Date) => ({
        month: date.toLocaleDateString('en-US', { month: 'short' }),
        day: date.getDate(),
        dayName: date.toLocaleDateString('en-US', { weekday: 'long' }),
        time: date.toLocaleTimeString('en-US', {
            hour: 'numeric',
            minute: '2-digit',
            hour12: true
        })
    });

    const startDateFormatted = formatDate(startDate);
    const endDateFormatted = endDate ? formatDate(endDate) : null;

    useEffect(() => {
        const updateTimeUntil = () => {
            const now = new Date();
            const timeDiff = startDate.getTime() - now.getTime();

            if (timeDiff > 0) {
                const days = Math.floor(timeDiff / (1000 * 60 * 60 * 24));
                const hours = Math.floor((timeDiff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                const minutes = Math.floor((timeDiff % (1000 * 60 * 60)) / (1000 * 60));

                if (days > 0) {
                    setTimeUntilEvent(`${days}d ${hours}h`);
                    setIsEventSoon(days <= 7);
                } else if (hours > 0) {
                    setTimeUntilEvent(`${hours}h ${minutes}m`);
                    setIsEventSoon(true);
                } else if (minutes > 0) {
                    setTimeUntilEvent(`${minutes}m`);
                    setIsEventSoon(true);
                } else {
                    setTimeUntilEvent('Starting soon');
                    setIsEventSoon(true);
                }
            } else {
                const endTime = endDate ? endDate.getTime() : startDate.getTime() + (2 * 60 * 60 * 1000);
                if (now.getTime() < endTime) {
                    setTimeUntilEvent('Live now');
                } else {
                    setTimeUntilEvent('Ended');
                }
                setIsEventSoon(false);
            }
        };

        updateTimeUntil();
        const interval = setInterval(updateTimeUntil, 60000);
        return () => clearInterval(interval);
    }, [startDate, endDate]);

    const coverImage = event.images?.find(img => img.type === 'EVENT_COVER');
    const location = event.location_details?.city || event.location_details?.venue_name;
    const isOnlineEvent = event.settings?.is_online_event;
    const attendeeCount = event.statistics?.attendee_count;

    const formatAttendeeCount = (count: number) => {
        return count >= 1000 ? `${(count / 1000).toFixed(1)}k` : count.toString();
    };

    const getStatusDisplay = () => {
        if (event.status === 'LIVE') return 'published';
        if (event.status === 'DRAFT') return 'draft';
        if (event.status === 'ARCHIVED') return 'cancelled';
        return event.status?.toLowerCase() || 'draft';
    };

    const getStatusText = () => {
        if (event.status === 'LIVE') return 'Published';
        if (event.status === 'DRAFT') return 'Draft';
        if (event.status === 'ARCHIVED') return 'Archived';
        return event.status?.replace('_', ' ') || 'Draft';
    };

    const handleClick = () => onClick?.();

    return (
        <article
            className={classes.eventCard}
            onClick={handleClick}
            role={onClick ? "button" : undefined}
            tabIndex={onClick ? 0 : undefined}
            onKeyDown={onClick ? (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    handleClick();
                }
            } : undefined}
            aria-label={`Event: ${event.title}`}
        >
            <div className={classes.eventDate}>
                <div className={classes.dateInfo}>
                    <span className={classes.month}>{startDateFormatted.month} {startDateFormatted.day}</span>
                    <span className={classes.dayName}>{startDateFormatted.dayName}</span>
                </div>
            </div>

            <div className={classes.eventDetails}>
                <div className={classes.eventTime}>
                    {startDateFormatted.time}
                    {endDateFormatted && (
                        <span className={classes.endTime}>
                            {startDateFormatted.day !== endDateFormatted.day
                                ? ` - ${endDateFormatted.month} ${endDateFormatted.day}, ${endDateFormatted.time}`
                                : ` - ${endDateFormatted.time}`
                            }
                        </span>
                    )}
                </div>

                <h3 className={classes.eventTitle}>{event.title}</h3>

                {event.description_preview && (
                    <p className={classes.eventDescription}>
                        {event.description_preview}
                    </p>
                )}

                <div className={classes.eventMeta}>
                    {(location || isOnlineEvent) && (
                        <span className={classes.location}>
                            {isOnlineEvent ? '🌐 Online Event' : `📍 ${location}`}
                        </span>
                    )}

                    {event.organizer?.name && (
                        <span className={classes.organizer}>
                            by {event.organizer.name}
                        </span>
                    )}
                </div>

                <div className={classes.statusBadge}>
                    <span className={`${classes.status} ${classes[getStatusDisplay()]}`}>
                        {getStatusText()}
                    </span>
                </div>
            </div>

            <div className={classes.eventImage}>
                {coverImage ? (
                    <img
                        src={coverImage.url}
                        alt={`${event.title} cover image`}
                        className={classes.coverImage}
                        loading="lazy"
                    />
                ) : (
                    <div className={classes.placeholderImage}>
                        <span className={classes.placeholderIcon}>🎉</span>
                    </div>
                )}
            </div>

            {timeUntilEvent && (
                <div className={classes.timeUntilContainer}>
                    <span
                        className={`${classes.timeUntil} ${isEventSoon ? classes.eventSoon : ''}`}
                        title={`Time until event: ${timeUntilEvent}`}
                    >
                        {timeUntilEvent}
                    </span>
                </div>
            )}

            {(attendeeCount !== undefined && attendeeCount > 0) && (
                <div className={classes.eventStats}>
                    <span className={classes.attendees} title={`${attendeeCount} attendees`}>
                        👥 {formatAttendeeCount(attendeeCount)}
                    </span>
                </div>
            )}

            {timeUntilEvent === 'Live now' && (
                <div className={classes.liveIndicator}>
                    <span>🔴 LIVE</span>
                </div>
            )}
        </article>
    );
};
