/**
 * All dates are stored in UTC in the database. The timezone for the account or event should be used to
 * display the date in the correct timezone.
 */

import dayjs from "dayjs";
import relativeTime from "dayjs/plugin/relativeTime";
import utc from 'dayjs/plugin/utc';
import timezone from 'dayjs/plugin/timezone';

dayjs.extend(utc);
dayjs.extend(relativeTime);
dayjs.extend(timezone);

export const prettyDate = (date: string, tz: string): string => {
    // eslint-disable-next-line lingui/no-unlocalized-strings
    return dayjs.utc(date).tz(tz).format('MMM D, YYYY h:mma');
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
