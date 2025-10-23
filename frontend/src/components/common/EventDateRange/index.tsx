import { Event } from "../../../types.ts";
import { formatDateForUser, formatDate } from "../../../utilites/dates.ts";
import { useGetMe } from "../../../queries/useGetMe.ts";
import { getClientLocale } from "../../../locales.ts";
import { useEffect, useState } from "react";

interface EventDateRangeProps {
    event: Event
}

export const EventDateRange = ({ event }: EventDateRangeProps) => {
    const { data: user } = useGetMe();
    const [isClient, setIsClient] = useState(false);

    // Ensure this only runs on the client side
    useEffect(() => {
        setIsClient(true);
    }, []);

    // If we're on the server side or user locale is not available, use a simple fallback
    if (!isClient) {
        // Server-side rendering fallback - use simple format
        const startDateFormatted = formatDate(event.start_date, "ddd, MMM D, YYYY h:mm A", event.timezone);
        const endDateFormatted = event.end_date ? formatDate(event.end_date, "ddd, MMM D, YYYY h:mm A", event.timezone) : null;
        const timezone = formatDate(event.start_date, "z", event.timezone);

        const isSameDay = event.end_date && event.start_date.substring(0, 10) === event.end_date.substring(0, 10);

        return (
            <>
                {isSameDay ? (
                    <span>
                        {formatDate(event.start_date, "dddd, MMMM D", event.timezone)} · {formatDate(event.start_date, "h:mm A", event.timezone)} - {formatDate(event.end_date!, "h:mm A", event.timezone)} {timezone}
                    </span>
                ) : (
                    <span>
                        {startDateFormatted}
                        {endDateFormatted && ` - ${endDateFormatted}`} {timezone}
                    </span>
                )}
            </>
        );
    }

    // Client-side rendering with locale support
    const userLocale = user?.locale || getClientLocale();

    const startDateFormatted = formatDateForUser(event.start_date, "fullDateTime", event.timezone, userLocale);
    const endDateFormatted = event.end_date ? formatDateForUser(event.end_date, "fullDateTime", event.timezone, userLocale) : null;
    const sameDayFormatted = formatDateForUser(event.start_date, "dayName", event.timezone, userLocale);
    const startTimeFormatted = formatDateForUser(event.start_date, "timeOnly", event.timezone, userLocale);
    const endTimeFormatted = event.end_date ? formatDateForUser(event.end_date, "timeOnly", event.timezone, userLocale) : null;
    const timezone = formatDateForUser(event.start_date, "timezone", event.timezone, userLocale);

    const isSameDay = event.end_date && event.start_date.substring(0, 10) === event.end_date.substring(0, 10);

    return (
        <>
            {isSameDay ? (
                <span>
                    {sameDayFormatted} · {startTimeFormatted} - {endTimeFormatted} {timezone}
                </span>
            ) : (
                <span>
                    {startDateFormatted}
                    {endDateFormatted && ` - ${endDateFormatted}`} {timezone}
                </span>
            )}
        </>
    );
}
