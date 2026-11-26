import {ActionIcon} from '@mantine/core';
import {useAccess} from "../../../hooks/useAccess.ts";
import {useEventActions} from "../../../hooks/useEventActions.tsx";
import {Event, EventType} from "../../../types.ts";
import classes from "./EventCard.module.scss";
import {NavLink} from "react-router";
import {IconArmchair, IconDotsVertical, IconRepeat} from "@tabler/icons-react";
import {t} from "@lingui/macro"
import {ActionMenu} from '../ActionMenu';
import {formatCurrency} from "../../../utilites/currency.ts";
import {formatNumber} from "../../../utilites/helpers.ts";
import {formatDateWithLocale, isValidDate, relativeDate} from "../../../utilites/dates.ts";
import {
    getEventDisplayDate,
    getEventLocationText,
    getEventStatusSummary,
    getEventTicketAvailability,
} from "../../../utilites/eventSummary.ts";

const placeholderGradients = [
    'linear-gradient(135deg, var(--mantine-color-violet-5) 0%, var(--mantine-color-indigo-5) 100%)',
    'linear-gradient(135deg, var(--mantine-color-pink-5) 0%, var(--mantine-color-grape-5) 100%)',
    'linear-gradient(135deg, var(--mantine-color-blue-5) 0%, var(--mantine-color-cyan-5) 100%)',
    'linear-gradient(135deg, var(--mantine-color-teal-5) 0%, var(--mantine-color-green-5) 100%)',
    'linear-gradient(135deg, var(--mantine-color-orange-5) 0%, var(--mantine-color-yellow-5) 100%)',
    'linear-gradient(135deg, var(--mantine-color-indigo-5) 0%, var(--mantine-color-blue-5) 100%)',
    'linear-gradient(135deg, var(--mantine-color-grape-5) 0%, var(--mantine-color-violet-5) 100%)',
    'linear-gradient(135deg, var(--mantine-color-cyan-5) 0%, var(--mantine-color-teal-5) 100%)',
];

interface EventCardProps {
    event: Event;
}

export function EventCard({event}: EventCardProps) {
    const access = useAccess();
    const {menuItems, duplicateModal} = useEventActions(event);

    const coverImage = event.images?.find(img => img.type === 'EVENT_COVER');
    const gradientIndex = event.id ? Number(event.id) % placeholderGradients.length : 0;
    const placeholderGradient = placeholderGradients[gradientIndex];

    const locationText = getEventLocationText(event);

    const canViewFinancials = access.can('VIEW_FINANCIALS');
    const revenue = event?.statistics?.sales_total_gross || 0;
    const attendees = event?.statistics?.attendees_registered || 0;

    const statusConfig = getEventStatusSummary(event);
    const ticketAvailability = getEventTicketAvailability(event);

    const isEnded = event.lifecycle_status === 'ENDED';
    const isDraft = event.status === 'DRAFT';
    const isRecurring = event.type === EventType.RECURRING;
    const isSeated = event.has_seat_map === true;

    const displayDate = getEventDisplayDate(event);
    const hasDate = isValidDate(displayDate);
    const monthShort = formatDateWithLocale(displayDate, 'monthShort', event.timezone);
    const dayOfMonth = formatDateWithLocale(displayDate, 'dayOfMonth', event.timezone);
    const shortDateTime = formatDateWithLocale(displayDate, 'shortDateTime', event.timezone);
    const relativeDateStr = relativeDate(displayDate);

    return (
        <>
            <div className={`${classes.eventCardCompact} ${isEnded ? classes.isEnded : ''} ${isDraft ? classes.isDraft : ''}`}>
                <NavLink to={`/manage/event/${event.id}/dashboard`} className={classes.cardLinkCompact}>
                    <div className={classes.compactThumb}>
                        <div
                            className={`${classes.compactImage} ${!coverImage ? classes.placeholderImage : ''}`}
                            style={coverImage
                                ? {backgroundImage: `url(${coverImage.url})`}
                                : {background: placeholderGradient}
                            }
                        />
                        {hasDate && (
                            <div className={`${classes.compactDateBadge}`}>
                                <span className={classes.compactDateDay}>{dayOfMonth}</span>
                                <span className={classes.compactDateMonth}>{monthShort}</span>
                            </div>
                        )}
                    </div>

                    <div className={classes.compactContent}>
                        <div className={classes.compactPrimary}>
                            <span className={classes.compactTitle}>{event.title}</span>
                            {isRecurring && (
                                <span className={classes.compactRecurringIcon} aria-label={t`Recurring`}>
                                    <IconRepeat size={12}/>
                                </span>
                            )}
                            {isSeated && (
                                <span className={classes.compactRecurringIcon} aria-label={t`Reserved seating`}>
                                    <IconArmchair size={12}/>
                                </span>
                            )}
                        </div>
                        <div className={classes.compactMeta}>
                            <span className={`${classes.compactStatus} ${classes[`compactStatus-${statusConfig.tone}`]}`}>
                                {statusConfig.pulse && <span className={classes.compactStatusDot}/>}
                                {statusConfig.label}
                            </span>
                            <span className={classes.compactDot}>·</span>
                            <span className={classes.compactMetaItem}>
                                {hasDate ? shortDateTime : t`No date added`}
                            </span>
                            {hasDate && (
                                <>
                                    <span className={classes.compactDot}>·</span>
                                    <span className={classes.compactMetaItem}>{relativeDateStr}</span>
                                </>
                            )}
                            {locationText && (
                                <>
                                    <span className={classes.compactDot}>·</span>
                                    <span className={`${classes.compactMetaItem} ${classes.compactLocation}`}>
                                        {locationText}
                                    </span>
                                </>
                            )}
                            {ticketAvailability && (
                                <>
                                    <span className={classes.compactDot}>·</span>
                                    <span className={`${classes.compactTickets} ${classes[`compactTickets-${ticketAvailability.status}`]}`}>
                                        {ticketAvailability.text}
                                    </span>
                                </>
                            )}
                        </div>
                    </div>

                    <div className={classes.compactStats}>
                        <div className={classes.compactStat}>
                            <span className={classes.compactStatValue}>{formatNumber(attendees)}</span>
                            <span className={classes.compactStatLabel}>{t`Attendees`}</span>
                        </div>
                        {canViewFinancials && <div className={classes.compactStat}>
                            <span className={classes.compactStatValue}>{formatCurrency(revenue, event?.currency)}</span>
                            <span className={classes.compactStatLabel}>{t`Revenue`}</span>
                        </div>}
                    </div>

                    <div className={classes.compactMenuButton} onClick={(e) => e.preventDefault()}>
                        <ActionMenu
                            itemsGroups={menuItems}
                            target={
                                <ActionIcon
                                    className={classes.actionButton}
                                    size="sm"
                                    variant="subtle"
                                >
                                    <IconDotsVertical size={16}/>
                                </ActionIcon>
                            }
                        />
                    </div>
                </NavLink>
            </div>
            {duplicateModal}
        </>
    );
}
