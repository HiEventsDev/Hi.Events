import {t} from "@lingui/macro";
import dayjs from "dayjs";
import utc from "dayjs/plugin/utc";
import timezone from "dayjs/plugin/timezone";
import {formatDateWithLocale, isSameDayInTimezone} from "./dates.ts";
import {formatCurrency} from "./currency.ts";
import {EventPriceSummary, EventScheduleSummary} from "./eventListing.ts";

dayjs.extend(utc);
dayjs.extend(timezone);

const formatDay = (date: string, tz: string, withYear: boolean): string =>
    formatDateWithLocale(date, withYear ? "shortDate" : "weekdayDate", tz);

export const eventPriceLabel = (price: EventPriceSummary, currency: string): string => {
    switch (price.kind) {
        case 'soldOut':
            return t`Sold out`;
        case 'free':
            return t`Free`;
        case 'donation':
            return t`Donation`;
        case 'exact':
            return formatCurrency(price.amount, currency);
        case 'from': {
            if (price.amount === 0) {
                return t`From free`;
            }
            const amount = formatCurrency(price.amount, currency);
            return t`From ${amount}`;
        }
    }
};

export const eventScheduleLabel = (schedule: EventScheduleSummary, tz: string): string => {
    const {start, end} = schedule;
    if (!start) {
        return t`Date TBA`;
    }
    if (schedule.isSeriesRange) {
        const firstDay = formatDay(start, tz, true);
        return end ? `${firstDay} – ${formatDay(end, tz, true)}` : firstDay;
    }

    const withYear = schedule.hasEnded || dayjs.utc(start).tz(tz).year() !== dayjs().tz(tz).year();
    const startLabel = `${formatDay(start, tz, withYear)} · ${formatDateWithLocale(start, "timeOnly", tz)}`;
    if (!end) {
        return startLabel;
    }
    const endTime = formatDateWithLocale(end, "timeOnly", tz);

    return isSameDayInTimezone(start, end, tz)
        ? `${startLabel} – ${endTime}`
        : `${startLabel} – ${formatDay(end, tz, withYear)}, ${endTime}`;
};
