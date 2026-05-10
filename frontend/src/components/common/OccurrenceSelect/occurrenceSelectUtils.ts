import dayjs from "dayjs";
import utc from "dayjs/plugin/utc";
import timezone from "dayjs/plugin/timezone";
import {EventOccurrence} from "../../../types.ts";
import {formatDateWithLocale} from "../../../utilites/dates.ts";

dayjs.extend(utc);
dayjs.extend(timezone);

/**
 * Pure presentation + filtering helpers shared by occurrence pickers
 * (OccurrenceSelect, check-in filter pill). Keeps rendering in the consumer.
 */

export const formatOccurrenceLabel = (occ: EventOccurrence, tz: string): string => {
    const date = formatDateWithLocale(occ.start_date, 'shortDate', tz);
    const time = formatDateWithLocale(occ.start_date, 'timeOnly', tz);
    return date + ' ' + time + (occ.label ? ` — ${occ.label}` : '');
};

export const getMonthKey = (occ: EventOccurrence, tz: string): string =>
    dayjs.utc(occ.start_date).tz(tz).format('YYYY-MM');

export const getMonthLabel = (monthKey: string): string =>
    dayjs(monthKey + '-01').format('MMMM YYYY');

export const isToday = (occ: EventOccurrence, tz: string): boolean => {
    const occDate = dayjs.utc(occ.start_date).tz(tz).format('YYYY-MM-DD');
    const today = dayjs().tz(tz).format('YYYY-MM-DD');
    return occDate === today;
};

export interface OccurrenceGroup {
    key: string;
    label: string;
    items: EventOccurrence[];
}

export interface FilterAndGroupResult {
    grouped: OccurrenceGroup[];
    /** Total occurrences after cancellation filter, before search/visibility cap. */
    totalAvailable: number;
    /** Matching occurrences after the search filter is applied. */
    totalFiltered: number;
    /** Whether the visible list was truncated by maxVisible. */
    truncated: boolean;
}

interface FilterOptions {
    search?: string;
    tz: string;
    filterCancelled?: boolean;
    filterPast?: boolean;
    maxVisible?: number;
}

/**
 * Filter, group by month (in the event's tz), and cap the visible entries.
 * Consumers use the `truncated` flag to show a "type to search" hint.
 */
export const filterAndGroupOccurrences = (
    occurrences: EventOccurrence[],
    {search, tz, filterCancelled = true, filterPast = false, maxVisible = 50}: FilterOptions,
): FilterAndGroupResult => {
    let items = occurrences;
    if (filterCancelled) {
        items = items.filter(o => o.status !== 'CANCELLED');
    }
    if (filterPast) {
        items = items.filter(o => !o.is_past);
    }
    const totalAvailable = items.length;

    const q = search?.trim().toLowerCase();
    if (q) {
        items = items.filter(occ => formatOccurrenceLabel(occ, tz).toLowerCase().includes(q));
    }
    const totalFiltered = items.length;

    const truncated = items.length > maxVisible;
    const visible = items.slice(0, maxVisible);

    const map = new Map<string, EventOccurrence[]>();
    for (const occ of visible) {
        const key = getMonthKey(occ, tz);
        if (!map.has(key)) map.set(key, []);
        map.get(key)!.push(occ);
    }

    const grouped: OccurrenceGroup[] = [];
    for (const [key, items] of map) {
        grouped.push({key, label: getMonthLabel(key), items});
    }

    return {grouped, totalAvailable, totalFiltered, truncated};
};
