import {useMemo} from "react";
import {useSearchParams} from "react-router";
import {
    DEFAULT_REPORT_PERIOD,
    isReportPeriod,
    isWallDate,
    ReportPeriod,
    reportPeriodToWallDates,
    WallDateRange,
    wallDatesToInstants,
} from "../utilites/reportPeriod.ts";

export const SHARED_REPORT_PARAMS = ['period', 'from', 'to'];

type ParamUpdates = Record<string, string | null>;

export const useReportParams = () => {
    const [searchParams, setSearchParams] = useSearchParams();

    const setParams = (updates: ParamUpdates) => setSearchParams(previous => {
        const next = new URLSearchParams(previous);
        Object.entries(updates).forEach(([key, value]) => value === null ? next.delete(key) : next.set(key, value));
        return next;
    }, {replace: true});

    return {
        param: (key: string) => searchParams.get(key),
        setParams,
        setFilter: (key: string, value: string | null) => setParams({[key]: value, page: null}),
    };
};

export const useReportPeriod = (tz: string) => {
    const {param, setParams} = useReportParams();
    const rawPeriod = param('period');
    const from = param('from');
    const to = param('to');
    const hasCustomRange = rawPeriod === 'custom' && isWallDate(from) && isWallDate(to) && from <= to;
    const period: ReportPeriod = hasCustomRange
        ? 'custom'
        : isReportPeriod(rawPeriod) && rawPeriod !== 'custom' ? rawPeriod : DEFAULT_REPORT_PERIOD;

    const range: WallDateRange = period === 'custom'
        ? {from: from as string, to: to as string}
        : reportPeriodToWallDates(period, tz);

    const [startDate, endDate] = useMemo(
        () => wallDatesToInstants({from: range.from, to: range.to}, tz),
        [range.from, range.to, tz],
    );

    return {
        period,
        range,
        startDate,
        endDate,
        setPeriod: (next: ReportPeriod) => setParams(next === 'custom'
            ? {period: 'custom', from: range.from, to: range.to, page: null}
            : {period: next === DEFAULT_REPORT_PERIOD ? null : next, from: null, to: null, page: null}),
        setCustomRange: (next: WallDateRange) => setParams({period: 'custom', from: next.from, to: next.to, page: null}),
    };
};
