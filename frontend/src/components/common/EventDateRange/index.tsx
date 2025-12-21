import { Event } from "../../../types.ts";
import { formatDateWithLocale } from "../../../utilites/dates.ts";

interface EventDateRangeProps {
    event: Event;
}

export const EventDateRange = ({ event }: EventDateRangeProps) => {
    const isSameDay = event.end_date && event.start_date.substring(0, 10) === event.end_date.substring(0, 10);
    const timezone = formatDateWithLocale(event.start_date, "timezone", event.timezone);

    if (isSameDay) {
        const dayFormatted = formatDateWithLocale(event.start_date, "dayName", event.timezone);
        const startTime = formatDateWithLocale(event.start_date, "timeOnly", event.timezone);
        const endTime = formatDateWithLocale(event.end_date!, "timeOnly", event.timezone);

        return (
            <span>
                {dayFormatted} · {startTime} - {endTime} {timezone}
            </span>
        );
    }

    const startDateFormatted = formatDateWithLocale(event.start_date, "fullDateTime", event.timezone);
    const endDateFormatted = event.end_date
        ? formatDateWithLocale(event.end_date, "fullDateTime", event.timezone)
        : null;

    return (
        <span>
            {startDateFormatted}
            {endDateFormatted && ` - ${endDateFormatted}`} {timezone}
        </span>
    );
}
