import {t} from "@lingui/macro";
import {Event, EventOccurrence, EventOccurrenceStatus, EventType} from "../../../types.ts";
import {formatDateWithLocale} from "../../../utilites/dates.ts";

interface EventDateRangeProps {
    event: Event;
    occurrence?: EventOccurrence;
}

const formatRange = (startDate: string, endDate: string | undefined, tz: string) => {
    const isSameDay = endDate && startDate.substring(0, 10) === endDate.substring(0, 10);
    const timezone = formatDateWithLocale(startDate, "timezone", tz);

    if (isSameDay) {
        const dayFormatted = formatDateWithLocale(startDate, "dayName", tz);
        const startTime = formatDateWithLocale(startDate, "timeOnly", tz);
        const endTime = formatDateWithLocale(endDate!, "timeOnly", tz);

        return (
            <span>
                {dayFormatted} · {startTime} - {endTime} {timezone}
            </span>
        );
    }

    const startDateFormatted = formatDateWithLocale(startDate, "fullDateTime", tz);
    const endDateFormatted = endDate
        ? formatDateWithLocale(endDate, "fullDateTime", tz)
        : null;

    return (
        <span>
            {startDateFormatted}
            {endDateFormatted && ` - ${endDateFormatted}`} {timezone}
        </span>
    );
};

export const EventDateRange = ({event, occurrence}: EventDateRangeProps) => {
    if (occurrence) {
        return formatRange(occurrence.start_date, occurrence.end_date, event.timezone);
    }

    if (event.type === EventType.RECURRING) {
        const activeOccurrences = (event.occurrences || [])
            .filter(o => o.status === EventOccurrenceStatus.ACTIVE && !o.is_past)
            .sort((a, b) => a.start_date.localeCompare(b.start_date));

        if (activeOccurrences.length > 0) {
            const next = activeOccurrences[0];
            if (activeOccurrences.length === 1) {
                return formatRange(next.start_date, next.end_date, event.timezone);
            }
            const nextFormatted = formatDateWithLocale(next.start_date, "shortDateTime", event.timezone);
            return (
                <span>
                    {t`Next: ${nextFormatted}`} · {t`${activeOccurrences.length} upcoming dates`}
                </span>
            );
        }

        return <span>{t`No upcoming dates`}</span>;
    }

    return formatRange(event.start_date, event.end_date, event.timezone);
};
