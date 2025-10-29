/**
 * All dates are stored in UTC in the database. The timezone for the account or event should be used to
 * display the date in the correct timezone.
 */

import dayjs from "dayjs";
import relativeTime from "dayjs/plugin/relativeTime";
import utc from 'dayjs/plugin/utc';
import timezone from 'dayjs/plugin/timezone';
import advanced from 'dayjs/plugin/advancedFormat';
import {isSsr} from "./helpers.ts";

dayjs.extend(utc);
dayjs.extend(relativeTime);
dayjs.extend(timezone);
dayjs.extend(advanced)

export const prettyDate = (date: string, tz: string, showTimezoneOffset: boolean = false): string => {
    // eslint-disable-next-line lingui/no-unlocalized-strings
    return dayjs.utc(date).tz(tz).format('MMM D, YYYY h:mma' + (showTimezoneOffset ? ' (z)' : ''));
};

export const formatDate = (date: string, format: string, tz: string): string => {
    return dayjs.utc(date).tz(tz).format(format);
};

/**
 * We don't explicitly convert to the event timezone here as we want to
 * display the 'ago' time in the user's timezone.
 *
 * @param date string
 */
export const relativeDate = (date: string): string => {
    const dateInUTC = dayjs.utc(date);

    return dayjs().to(dateInUTC);
};

export const utcToTz = (date: undefined | string | Date, tz: string): string | undefined => {
    if (!date) {
        return undefined;
    }
    // eslint-disable-next-line lingui/no-unlocalized-strings
    return dayjs.utc(date).tz(tz).format('YYYY-MM-DDTHH:mm');
};

/**
 * Converts a datetime to the user's browser timezone, with a fallback timezone for SSR.
 *
 * @param date string
 * @param fallbackTz string
 */
export const dateToBrowserTz = (date: string, fallbackTz: string): string => {
    const userTimezone = !isSsr()
        ? Intl.DateTimeFormat().resolvedOptions().timeZone
        : fallbackTz;

    return dayjs.utc(date).tz(userTimezone).format('MMM D, YYYY h:mma z');
};

export const isDateInFuture = (date: string): boolean => {
    return dayjs.utc(date).diff(dayjs()) > 0;
};
