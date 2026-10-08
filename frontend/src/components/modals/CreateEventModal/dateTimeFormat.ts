import dayjs from "dayjs";
import {getClientLocale} from "../../../locales.ts";
import {localeFormats} from "../../../utilites/dateLocales.ts";
import {getSafeLocale} from "../../../utilites/dates.ts";

// eslint-disable-next-line lingui/no-unlocalized-strings
export const NAIVE_DATE_TIME_FORMAT = 'YYYY-MM-DD HH:mm:ss';

const formatsForClient = () => {
    const locale = getSafeLocale(getClientLocale());
    return {locale, formats: localeFormats[locale]};
};

export const uses12HourClock = (): boolean => /[hA]/.test(formatsForClient().formats.timeOnly);

export const formatChipDateTime = (value: string): string => {
    const {locale, formats} = formatsForClient();
    const date = dayjs(value).locale(locale);
    return `${date.format(formats.weekdayDate)} · ${date.format(formats.timeOnly)}`;
};

export const formatChipEnd = (end: string, start?: string | null): string => {
    const {locale, formats} = formatsForClient();
    const date = dayjs(end).locale(locale);
    if (start && dayjs(start).isSame(dayjs(end), 'day')) {
        return date.format(formats.timeOnly);
    }
    return `${date.format(formats.weekdayDate)} · ${date.format(formats.timeOnly)}`;
};

const nowIn = (timezone?: string | null): dayjs.Dayjs => {
    try {
        return timezone ? dayjs().tz(timezone) : dayjs();
    } catch {
        return dayjs();
    }
};

export const currentDateTimeIn = (timezone?: string | null): string => nowIn(timezone).format(NAIVE_DATE_TIME_FORMAT);

export const defaultStartDate = (timezone?: string | null): string =>
    nowIn(timezone).add(1, 'day').hour(21).minute(0).second(0).format(NAIVE_DATE_TIME_FORMAT);
