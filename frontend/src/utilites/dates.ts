/**
 * All dates are stored in UTC in the database. The timezone for the account or event should be used to
 * display the date in the correct timezone.
 */

import dayjs from "dayjs";
import relativeTime from "dayjs/plugin/relativeTime";
import utc from 'dayjs/plugin/utc';
import timezone from 'dayjs/plugin/timezone';
import advanced from 'dayjs/plugin/advancedFormat';
import { isSsr } from "./helpers.ts";
import { getClientLocale, SupportedLocales } from "../locales.ts";
import { localeFormats, setDayjsLocale } from "./dateLocales.ts";

dayjs.extend(utc);
dayjs.extend(relativeTime);
dayjs.extend(timezone);
dayjs.extend(advanced)

export const formatDate = (date: string, format: string, tz: string): string => {
    return dayjs.utc(date).tz(tz).format(format);
};

/**
 * Format date with locale-specific formatting
 */
export const formatDateWithLocale = (
    date: string,
    formatType: 'fullDateTime' | 'dayName' | 'timeOnly' | 'timezone',
    tz: string,
    locale?: SupportedLocales
): string => {
    try {
        const userLocale = locale || getClientLocale() as SupportedLocales;

        // Ensure we have a valid locale and format
        if (!userLocale || !localeFormats[userLocale] || !localeFormats[userLocale][formatType]) {
            throw new Error(`Invalid locale or format: ${userLocale}, ${formatType}`);
        }

        // Set the Day.js locale for this operation
        setDayjsLocale(userLocale);

        const format = localeFormats[userLocale][formatType];
        return dayjs.utc(date).tz(tz).format(format);
    } catch (error) {
        // Fallback to English formatting if there's any error
        console.warn('Date localization failed, falling back to English:', error);
        const fallbackFormats = {
            fullDateTime: 'ddd, MMM D, YYYY h:mm A',
            dayName: 'dddd, MMMM D',
            timeOnly: 'h:mm A',
            timezone: 'z'
        };
        return dayjs.utc(date).tz(tz).format(fallbackFormats[formatType]);
    }
};

/**
 * Format date with user's preferred locale (from user settings or browser)
 * This function can be enhanced to use data from useGetMe hook when available
 */
export const formatDateForUser = (
    date: string,
    formatType: 'fullDateTime' | 'dayName' | 'timeOnly' | 'timezone',
    tz: string,
    userLocaleFromSettings?: string
): string => {
    // Priority: user settings > cookie > browser locale > fallback to English
    let locale: SupportedLocales = 'en';

    if (userLocaleFromSettings && userLocaleFromSettings in localeFormats) {
        locale = userLocaleFromSettings as SupportedLocales;
    } else {
        locale = getClientLocale() as SupportedLocales;
    }

    // Debug logging to help identify the issue
    if (typeof window !== 'undefined' && window.console) {
        console.log('Date formatting debug:', {
            userLocaleFromSettings,
            detectedLocale: locale,
            formatType,
            availableFormats: Object.keys(localeFormats)
        });
    }

    return formatDateWithLocale(date, formatType, tz, locale);
};

/**
 * Legacy function for backward compatibility
 */
export const prettyDate = (date: string, tz: string): string => {
    // eslint-disable-next-line lingui/no-unlocalized-strings
    return dayjs.utc(date).tz(tz).format('MMM D, YYYY h:mma');
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
