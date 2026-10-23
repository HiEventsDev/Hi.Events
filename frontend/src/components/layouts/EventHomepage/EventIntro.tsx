import {ReactNode} from "react";
import {t} from "@lingui/macro";
import {IconArrowUpRight, IconCalendarPlus, IconCalendarRepeat, IconMapPin, IconShare2, IconWorld} from "@tabler/icons-react";
import {Event, EventOccurrence, Organizer, OrganizerStatus} from "../../../types.ts";
import {EventDateRange} from "../../common/EventDateRange";
import {ShareComponent} from "../../common/ShareIcon";
import {CalendarOptionsPopover} from "../../common/CalendarOptionsPopover";
import {formatDateWithLocale, formatLongDay, isSameDayInTimezone} from "../../../utilites/dates.ts";
import {eventCoverImage, eventHomepageUrl, organizerHomepageUrl} from "../../../utilites/urlHelper.ts";
import {EventLocationDisplay} from "../../../utilites/effectiveLocation.ts";
import {DateTile} from "../HomepageFrame/DateTile.tsx";
import {sizedImageUrl} from "../../../utilites/imageTransform.ts";
import classes from "./EventHomepage.module.scss";

const LONG_TITLE_LENGTH = 48;

export interface IntroStatus {
    label: string;
    tone: 'live' | 'muted';
}

interface EventHostProps {
    event: Event;
    organizer: Organizer;
    organizerLogo?: string;
    occurrence?: EventOccurrence;
    canAddToCalendar: boolean;
}

interface EventIntroProps extends EventHostProps {
    showHost: boolean;
    centered: boolean;
    dateStart: string | null;
    dateEnd: string | null;
    seriesLabel: string | null;
    lastOccurrenceDate: string | null;
    status: IntroStatus | null;
    location: EventLocationDisplay | null;
    variedLocationLabel: string | null;
}

export const EventHost = ({event, organizer, organizerLogo, occurrence, canAddToCalendar}: EventHostProps) => {
    const organizerIsLive = organizer.status === OrganizerStatus.LIVE;
    const organizerContent = (
        <>
            {organizerLogo
                ? <img src={sizedImageUrl(organizerLogo, 96)} alt="" className={classes.hostAvatar}/>
                : <span className={classes.hostAvatar} data-placeholder="" aria-hidden="true">{organizer.name.charAt(0).toUpperCase()}</span>}
            <span className={classes.hostName}>{organizer.name}</span>
        </>
    );

    return (
        <div className={classes.introTop}>
            {organizerIsLive
                ? <a href={organizerHomepageUrl(organizer)} className={classes.host}>{organizerContent}</a>
                : <div className={classes.host}>{organizerContent}</div>}

            <div className={classes.introActions}>
                <ShareComponent
                    title={t`Check out this event: ${event.title}`}
                    text={t`Check out this event: ${event.title}`}
                    url={eventHomepageUrl(event)}
                    imageUrl={eventCoverImage(event)?.url}
                >
                    <button type="button" className={classes.iconButton} title={t`Share`} aria-label={t`Share`}>
                        <IconShare2/>
                    </button>
                </ShareComponent>
                {canAddToCalendar && (
                    <CalendarOptionsPopover event={event} occurrence={occurrence}>
                        <button type="button" className={classes.iconButton} title={t`Add to Calendar`} aria-label={t`Add to Calendar`}>
                            <IconCalendarPlus/>
                        </button>
                    </CalendarOptionsPopover>
                )}
            </div>
        </div>
    );
};

export const EventIntro = ({
                               showHost,
                               centered,
                               dateStart,
                               dateEnd,
                               seriesLabel,
                               lastOccurrenceDate,
                               status,
                               location,
                               variedLocationLabel,
                               ...hostProps
                           }: EventIntroProps) => {
    const {event, occurrence} = hostProps;
    const tz = event.timezone;

    return (
        <header className={classes.intro} data-centered={centered || undefined}>
            {showHost && <EventHost {...hostProps}/>}

            {status && (
                <span className={classes.statusBadge} data-tone={status.tone}>
                    {status.tone === 'live' && <span className={classes.liveDot} aria-hidden="true"/>}
                    {status.label}
                </span>
            )}

            <h1 className={classes.title} data-long={event.title.length > LONG_TITLE_LENGTH || undefined}>{event.title}</h1>

            <ul className={classes.facts}>
                <Fact
                    tile={dateStart ? <DateTile date={dateStart} tz={tz}/> : <span className={classes.factTile}><IconCalendarRepeat/></span>}
                    title={dateStart ? formatLongDay(dateStart, tz) : seriesLabel ?? <EventDateRange event={event} occurrence={occurrence}/>}
                    detail={dateStart && <TimeRange start={dateStart} end={dateEnd} tz={tz}/>}
                    extra={lastOccurrenceDate && (
                        <span className={classes.factNote}>
                            <IconCalendarRepeat size={14}/>
                            {t`Multiple dates until ${lastOccurrenceDate}`}
                        </span>
                    )}
                />

                {location?.isOnline && (
                    <Fact tile={<span className={classes.factTile}><IconWorld/></span>} title={t`Online Event`} detail={t`Join from anywhere`}/>
                )}

                {location && !location.isOnline && (location.venueName || location.full) && (
                    <Fact
                        tile={<span className={classes.factTile}><IconMapPin/></span>}
                        title={location.venueName || location.full}
                        detail={location.venueName ? location.full : null}
                        extra={location.mapsUrl && (
                            <a href={location.mapsUrl} target="_blank" rel="nofollow ugc noopener noreferrer" className={classes.factLink}>
                                {t`Get directions`}
                                <IconArrowUpRight size={14}/>
                            </a>
                        )}
                    />
                )}

                {variedLocationLabel && (
                    <Fact tile={<span className={classes.factTile}><IconMapPin/></span>} title={variedLocationLabel} detail={t`See schedule`}/>
                )}
            </ul>
        </header>
    );
};

const Fact = ({tile, title, detail, extra}: { tile: ReactNode, title: ReactNode, detail?: ReactNode, extra?: ReactNode }) => (
    <li className={classes.fact}>
        {tile}
        <span className={classes.factBody}>
            <span className={classes.factTitle}>{title}</span>
            {detail && <span className={classes.factDetail}>{detail}</span>}
            {extra}
        </span>
    </li>
);

const TimeRange = ({start, end, tz}: { start: string, end: string | null, tz: string }) => {
    const startTime = formatDateWithLocale(start, 'timeOnly', tz);
    const zone = formatDateWithLocale(start, 'timezone', tz);

    const zoneLabel = <span suppressHydrationWarning>{zone}</span>;

    if (!end) {
        return <>{startTime} {zoneLabel}</>;
    }

    const endLabel = formatDateWithLocale(end, isSameDayInTimezone(start, end, tz) ? 'timeOnly' : 'dayMonthTime', tz);

    return <>{startTime} – {endLabel} {zoneLabel}</>;
};
