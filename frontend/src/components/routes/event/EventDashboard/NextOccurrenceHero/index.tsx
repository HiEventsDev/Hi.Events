import {useEffect, useMemo, useState} from "react";
import {useNavigate} from "react-router";
import {t} from "@lingui/macro";
import {Button, Skeleton} from "@mantine/core";
import {
    IconArrowRight,
    IconBroadcast,
    IconCalendarEvent,
    IconUsers,
} from "@tabler/icons-react";
import dayjs from "dayjs";
import utc from "dayjs/plugin/utc";
import timezone from "dayjs/plugin/timezone";
import {Event, EventOccurrence, EventOccurrenceStatus, IdParam} from "../../../../../types.ts";
import {useGetEventOccurrence} from "../../../../../queries/useGetEventOccurrence.ts";
import {useOccurrenceCheckIn} from "../../../../../hooks/useOccurrenceCheckIn.tsx";
import {formatDateWithLocale, formatOccurrenceEnd} from "../../../../../utilites/dates.ts";
import classes from "./NextOccurrenceHero.module.scss";

dayjs.extend(utc);
dayjs.extend(timezone);

interface NextOccurrenceHeroProps {
    event: Event;
    eventId: IdParam;
}

type UrgencyState = 'future' | 'imminent' | 'live' | 'wrapping';

interface Countdown {
    state: UrgencyState;
    primary: string;
    secondary?: string;
    tickMs: number;
}

const computeCountdown = (occurrence: EventOccurrence, tz: string, now: dayjs.Dayjs): Countdown => {
    const start = dayjs.utc(occurrence.start_date).tz(tz);
    const end = occurrence.end_date ? dayjs.utc(occurrence.end_date).tz(tz) : null;

    const effectiveEnd = end ?? start.add(8, 'hour');
    if (now.isAfter(start) && now.isBefore(effectiveEnd)) {
        return {
            state: 'live',
            primary: t`Happening now`,
            secondary: end ? t`Ends ${end.from(now)}` : undefined,
            tickMs: 60_000,
        };
    }

    if (end && now.isAfter(end) && now.diff(end, 'hour') < 1) {
        return {
            state: 'wrapping',
            primary: t`That's a wrap`,
            secondary: t`Ended ${end.from(now)}`,
            tickMs: 60_000,
        };
    }

    const diffMs = start.diff(now);
    const diffMinutes = start.diff(now, 'minute');
    const diffHours = start.diff(now, 'hour');
    const diffDays = start.diff(now, 'day');

    if (diffMs < 60 * 60 * 1000) {
        if (diffMinutes < 1) {
            const seconds = Math.max(0, Math.floor(diffMs / 1000));
            return {
                state: 'imminent',
                primary: t`Starts in ${seconds}s`,
                tickMs: 1_000,
            };
        }
        return {
            state: 'imminent',
            primary: t`Starts in ${diffMinutes} min`,
            tickMs: 1_000,
        };
    }

    if (diffHours < 24) {
        const h = Math.floor(diffMs / (1000 * 60 * 60));
        const m = Math.floor((diffMs % (1000 * 60 * 60)) / (1000 * 60));
        return {
            state: 'imminent',
            primary: t`Starts in ${h}h ${m}m`,
            tickMs: 60_000,
        };
    }

    return {
        state: 'future',
        primary: diffDays === 1 ? t`Starts tomorrow` : t`Starts in ${diffDays} days`,
        tickMs: 60_000,
    };
};

const pickNextOccurrence = (occurrences: EventOccurrence[] | undefined): EventOccurrence | null => {
    if (!occurrences || occurrences.length === 0) return null;
    const now = dayjs();

    const live = occurrences
        .filter(o => o.status === EventOccurrenceStatus.ACTIVE)
        .find(o => {
            const s = dayjs.utc(o.start_date);
            const e = o.end_date ? dayjs.utc(o.end_date) : s.add(8, 'hour');
            return now.isAfter(s) && now.isBefore(e);
        });
    if (live) return live;

    const upcoming = occurrences
        .filter(o => o.status === EventOccurrenceStatus.ACTIVE && !o.is_past)
        .sort((a, b) => a.start_date.localeCompare(b.start_date));
    return upcoming[0] ?? null;
};

export const NextOccurrenceHero = ({event, eventId}: NextOccurrenceHeroProps) => {
    const navigate = useNavigate();
    const tz = event.timezone;

    const nextOccurrence = useMemo(() => pickNextOccurrence(event.occurrences), [event.occurrences]);

    const occurrenceQuery = useGetEventOccurrence(eventId, nextOccurrence?.id);
    const occurrence = occurrenceQuery.data ?? nextOccurrence;

    const {launchCheckIn, checkInModals} = useOccurrenceCheckIn(eventId);

    const [now, setNow] = useState(() => dayjs());
    const countdown = useMemo(
        () => occurrence ? computeCountdown(occurrence, tz, now) : null,
        [occurrence, tz, now],
    );

    useEffect(() => {
        if (!countdown) return;
        const id = setInterval(() => setNow(dayjs()), countdown.tickMs);
        return () => clearInterval(id);
    }, [countdown?.tickMs]);

    if (occurrenceQuery.isLoading && nextOccurrence) {
        return <Skeleton height={220} radius="lg" mt="20px"/>;
    }

    if (!occurrence || !countdown) {
        return null;
    }

    const stats = occurrence.statistics;
    const registered = stats?.attendees_registered ?? 0;
    const capacity = occurrence.capacity ?? 0;
    const percent = capacity > 0 ? Math.min(100, Math.round((registered / capacity) * 100)) : 0;

    const dateLine = formatDateWithLocale(occurrence.start_date, 'dayName', tz)
        || formatDateWithLocale(occurrence.start_date, 'shortDate', tz);
    const timeLine = formatDateWithLocale(occurrence.start_date, 'timeOnly', tz)
        + (occurrence.end_date ? ' – ' + formatOccurrenceEnd(occurrence.end_date, occurrence.start_date, tz) : '');

    const labelText = countdown.state === 'live'
        ? t`Happening now`
        : countdown.state === 'wrapping'
            ? t`Just wrapped`
            : t`Next occurrence`;

    return (
        <div className={`${classes.hero} ${classes[`state_${countdown.state}`]}`}>
            <div className={classes.label}>
                <div className={classes.labelTop}>
                    {countdown.state === 'live'
                        ? <IconBroadcast size={12} className={classes.labelIcon}/>
                        : <IconCalendarEvent size={12} className={classes.labelIcon}/>}
                    <span>{labelText}</span>
                </div>
                <div className={classes.countdown}>
                    {countdown.state === 'live' && <span className={classes.pulseDot}/>}
                    <span>{countdown.primary}</span>
                </div>
            </div>

            <div className={classes.details}>
                <div className={classes.dateLine}>{dateLine}</div>
                <div className={classes.timeLine}>{timeLine}</div>
                {occurrence.label && <div className={classes.occurrenceLabel}>{occurrence.label}</div>}
            </div>

            <div className={classes.stats}>
                <div className={classes.statLine}>
                    <IconUsers size={14} className={classes.statIcon}/>
                    {capacity > 0 ? (
                        <>
                            <span>{registered} / {capacity}</span>
                            <span className={classes.statSecondary}>· {percent}%</span>
                        </>
                    ) : (
                        <span>{registered} {registered === 1 ? t`attendee` : t`attendees`}</span>
                    )}
                </div>
                {capacity > 0 && (
                    <div className={classes.bar} role="progressbar" aria-valuenow={percent} aria-valuemin={0} aria-valuemax={100}>
                        <div className={classes.barFill} style={{width: `${percent}%`}}/>
                    </div>
                )}
            </div>

            <Button
                variant="light"
                rightSection={<IconArrowRight size={14}/>}
                onClick={() => {
                    if (countdown.state === 'live') {
                        launchCheckIn(Number(occurrence.id));
                        return;
                    }
                    navigate(`/manage/event/${eventId}/occurrences/${occurrence.id}`);
                }}
                size="sm"
                classNames={{root: classes.cta}}
            >
                {countdown.state === 'live' ? t`Open check-in` : t`Open occurrence`}
            </Button>

            {checkInModals}
        </div>
    );
};
