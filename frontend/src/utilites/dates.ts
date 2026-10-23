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
import { getClientLocale, SupportedLocales, toDayjsLocale, toIntlLocale } from "../locales.ts";
import { localeFormats } from "./dateLocales.ts";

dayjs.extend(utc);
dayjs.extend(relativeTime);
dayjs.extend(timezone);
dayjs.extend(advanced)

export const daysSinceWeekStart = (dayOfWeek: number, firstDayOfWeek: number): number =>
    (dayOfWeek - firstDayOfWeek + 7) % 7;

export const getCalendarGridStart = (monthStart: dayjs.Dayjs, firstDayOfWeek: number): dayjs.Dayjs =>
    monthStart.subtract(daysSinceWeekStart(monthStart.day(), firstDayOfWeek), 'day').startOf('day');

export const formatDate = (date: string, format: string, tz: string): string => {
    return dayjs.utc(date).tz(tz).format(format);
};

export type DateFormatType = 'fullDateTime' | 'shortDateTime' | 'shortDate' | 'chartDate' | 'weekdayDate' | 'dayMonthTime' | 'monthShort' | 'dayOfMonth' | 'dayName' | 'timeOnly' | 'timezone';

/**
 * Safely get a supported locale, falling back to 'en' if not supported.
 */
export const getSafeLocale = (locale?: string): SupportedLocales => {
    if (locale && locale in localeFormats) {
        return locale as SupportedLocales;
    }
    return 'en';
};

export const formatDateWithLocale = (
    date: string,
    formatType: DateFormatType,
    tz: string,
    locale?: SupportedLocales | string
): string => {
    const resolvedLocale = locale || getClientLocale();
    const safeLocale = getSafeLocale(resolvedLocale);
    const format = localeFormats[safeLocale][formatType];

    return dayjs.utc(date).tz(tz).locale(toDayjsLocale(safeLocale)).format(format);
};

export const formatLongDay = (date: string, tz: string): string => {
    const withYear = dayjs.utc(date).tz(tz).year() !== dayjs().tz(tz).year();

    return new Intl.DateTimeFormat(toIntlLocale(getSafeLocale(getClientLocale())), {
        weekday: 'long',
        month: 'long',
        day: 'numeric',
        year: withYear ? 'numeric' : undefined,
        timeZone: tz,
    }).format(dayjs.utc(date).toDate());
};

export const formatWeekday = (date: string, tz: string, weekday: 'long' | 'short' = 'long'): string => new Intl.DateTimeFormat(
    toIntlLocale(getSafeLocale(getClientLocale())),
    {weekday, timeZone: tz},
).format(dayjs.utc(date).toDate());

export const formatMonthHeading = (date: string, tz: string): string => {
    const withYear = dayjs.utc(date).tz(tz).year() !== dayjs().tz(tz).year();

    return new Intl.DateTimeFormat(toIntlLocale(getSafeLocale(getClientLocale())), {
        month: 'long',
        year: withYear ? 'numeric' : undefined,
        timeZone: tz,
    }).format(dayjs.utc(date).toDate());
};

export const isSameDayInTimezone = (a: string, b: string, tz: string): boolean =>
    dayjs.utc(a).tz(tz).isSame(dayjs.utc(b).tz(tz), 'day');

export const formatOccurrenceEnd = (
    endDate: string,
    startDate: string,
    tz: string,
    locale?: SupportedLocales | string
): string => {
    return formatDateWithLocale(
        endDate,
        isSameDayInTimezone(startDate, endDate, tz) ? 'timeOnly' : 'dayMonthTime',
        tz,
        locale
    );
};

export const formatDateForUser = (
    date: string,
    formatType: DateFormatType,
    tz: string,
    userLocaleFromSettings?: string
): string => {
    return formatDateWithLocale(date, formatType, tz, userLocaleFromSettings);
};

export const prettyDate = (date: string, tz: string, showTimezoneOffset: boolean = false, locale?: string): string => {
    const formatted = formatDateWithLocale(date, 'shortDateTime', tz, locale);

    if (showTimezoneOffset) {
        const tzAbbr = formatDateWithLocale(date, 'timezone', tz, locale);
        return `${formatted} (${tzAbbr})`;
    }

    return formatted;
};

/**
 * We don't explicitly convert to the event timezone here as we want to
 * display the 'ago' time in the user's timezone.
 *
 * @param date string
 */
export const relativeDate = (date: string): string => {
    const dateInUTC = dayjs.utc(date);
    const locale = getClientLocale();

    return dayjs().locale(toDayjsLocale(locale)).to(dateInUTC);
};

export const getDateTimePickerFormat = (): string => {
    const locale = getClientLocale();
    const safeLocale = getSafeLocale(locale);
    return localeFormats[safeLocale].dateTimePicker;
};

export const getDatePickerFormat = (): string => localeFormats[getSafeLocale(getClientLocale())].shortDate;

export const getTimePickerFormat = (): '12h' | '24h' => getDateTimePickerFormat().includes('A') ? '12h' : '24h';

export const isValidDate = (date?: string | null): boolean => {
    return !!date && dayjs(date).isValid();
};

const toWallClock = (instant: Date, tz: string): string => {
    const parts = Object.fromEntries(
        new Intl.DateTimeFormat('en-US', {
            timeZone: tz,
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            hourCycle: 'h23',
        }).formatToParts(instant).map(part => [part.type, part.value])
    );

    return `${parts.year}-${parts.month}-${parts.day}T${parts.hour}:${parts.minute}`;
};

export const utcToTz = (date: undefined | string | Date, tz: string): string | undefined => {
    if (!date) {
        return undefined;
    }
    const instant = dayjs.utc(date);

    return instant.isValid() ? toWallClock(instant.toDate(), tz) : undefined;
};

export const nowInTimezone = (tz: string): string => toWallClock(new Date(), tz);

export const dateToBrowserTz = (date: string, fallbackTz: string, locale?: string): string => {
    const userTimezone = !isSsr()
        ? Intl.DateTimeFormat().resolvedOptions().timeZone
        : fallbackTz;

    const formatted = formatDateWithLocale(date, 'shortDateTime', userTimezone, locale);
    const tzAbbr = formatDateWithLocale(date, 'timezone', userTimezone, locale);

    return `${formatted} ${tzAbbr}`;
};

export const isDateInFuture = (date: string): boolean => {
    return dayjs.utc(date).diff(dayjs()) > 0;
};

export const utcDateToEpochMs = (date: string): number => {
    return dayjs.utc(date).valueOf();
};

