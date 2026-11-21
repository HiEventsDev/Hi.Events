import dayjs from "dayjs";
import utc from "dayjs/plugin/utc";
import timezone from "dayjs/plugin/timezone";
import {formatDateWithLocale} from "./dates.ts";

dayjs.extend(utc);
dayjs.extend(timezone);

export const REPORT_PERIODS = ['today', '7d', '14d', '30d', '90d', '6m', 'ytd', '12m', 'custom'] as const;

export type ReportPeriod = typeof REPORT_PERIODS[number];

export const DEFAULT_REPORT_PERIOD: ReportPeriod = '90d';

export const isReportPeriod = (value: string | null): value is ReportPeriod =>
    value !== null && (REPORT_PERIODS as readonly string[]).includes(value);

export interface WallDateRange {
    from: string;
    to: string;
}

const WALL_DATE = 'YYYY-MM-DD';

export const isWallDate = (value: string | null): value is string =>
    value !== null && /^\d{4}-\d{2}-\d{2}$/.test(value) && dayjs(value).isValid();

export const reportPeriodToWallDates = (period: Exclude<ReportPeriod, 'custom'>, tz: string, now: Date = new Date()): WallDateRange => {
    const today = dayjs(now).tz(tz);
    const from = {
        'today': today,
        '7d': today.subtract(6, 'day'),
        '14d': today.subtract(13, 'day'),
        '30d': today.subtract(29, 'day'),
        '90d': today.subtract(89, 'day'),
        '6m': today.subtract(6, 'month'),
        'ytd': today.startOf('year'),
        '12m': today.subtract(12, 'month'),
    }[period];

    return {from: from.format(WALL_DATE), to: today.format(WALL_DATE)};
};

export const wallDatesToInstants = ({from, to}: WallDateRange, tz: string): [Date, Date] => [
    dayjs.tz(from, tz).startOf('day').toDate(),
    dayjs.tz(to, tz).endOf('day').toDate(),
];

export const formatReportRange = (from: string, to: string): string =>
    `${formatDateWithLocale(from, 'shortDate', 'UTC')} – ${formatDateWithLocale(to, 'shortDate', 'UTC')}`;
