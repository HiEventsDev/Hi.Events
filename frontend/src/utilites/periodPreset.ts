import {PeriodPreset} from "../components/common/PeriodSelector";
import {Event} from "../types.ts";

export interface DateRange {
    startDate: string;
    endDate: string;
}

const formatDate = (d: Date): string => {
    const pad = (n: number) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`;
};

const startOfDay = (d: Date): Date =>
    new Date(d.getFullYear(), d.getMonth(), d.getDate(), 0, 0, 0);

const endOfDay = (d: Date): Date =>
    new Date(d.getFullYear(), d.getMonth(), d.getDate(), 23, 59, 59);

const parseEventDate = (raw: string | undefined | null): Date | null => {
    if (!raw) return null;
    const normalized = raw.includes('T') ? raw : raw.replace(' ', 'T');
    const date = new Date(normalized);
    return isNaN(date.getTime()) ? null : date;
};

export const EVENT_RELATIVE_PRESETS: ReadonlySet<PeriodPreset> = new Set([
    'event_first_week',
    'event_first_month',
    'event_first_quarter',
    'event_full',
]);

export const isEventRelativePreset = (preset: PeriodPreset): boolean =>
    EVENT_RELATIVE_PRESETS.has(preset);

export const periodPresetToDateRange = (preset: PeriodPreset, event?: Event): DateRange => {
    const now = new Date();

    if (isEventRelativePreset(preset)) {
        const eventStart = parseEventDate(event?.start_date) ?? now;
        const eventEnd = parseEventDate(event?.end_date);

        let start: Date;
        let end: Date;
        switch (preset) {
            case 'event_first_week':
                start = eventStart;
                end = new Date(eventStart);
                end.setDate(end.getDate() + 7);
                break;
            case 'event_first_month':
                start = eventStart;
                end = new Date(eventStart);
                end.setDate(end.getDate() + 30);
                break;
            case 'event_first_quarter':
                start = eventStart;
                end = new Date(eventStart);
                end.setDate(end.getDate() + 90);
                break;
            case 'event_full':
            default: {
                end = eventEnd && eventEnd.getTime() < now.getTime() ? eventEnd : now;
                const desiredStart = new Date(eventStart);
                desiredStart.setDate(desiredStart.getDate() - 365);
                const floorStart = new Date(end);
                floorStart.setDate(floorStart.getDate() - 369);
                start = desiredStart.getTime() > floorStart.getTime() ? desiredStart : floorStart;
                if (start.getTime() > end.getTime()) {
                    start = floorStart;
                }
                break;
            }
        }
        return {startDate: formatDate(startOfDay(start)), endDate: formatDate(endOfDay(end))};
    }

    const end = new Date(now);
    let start: Date;
    switch (preset) {
        case 'today':
            start = new Date(now.getFullYear(), now.getMonth(), now.getDate(), 0, 0, 0);
            break;
        case 'last_7_days':
            start = new Date(now);
            start.setDate(start.getDate() - 7);
            break;
        case 'last_30_days':
            start = new Date(now);
            start.setDate(start.getDate() - 30);
            break;
        case 'last_90_days':
            start = new Date(now);
            start.setDate(start.getDate() - 90);
            break;
        case 'year_to_date':
            start = new Date(now.getFullYear(), 0, 1, 0, 0, 0);
            break;
        default:
            start = new Date(now);
            start.setDate(start.getDate() - 30);
    }

    return {startDate: formatDate(startOfDay(start)), endDate: formatDate(endOfDay(end))};
};

export const previousPeriodRange = (current: DateRange): DateRange => {
    const start = new Date(current.startDate.replace(' ', 'T'));
    const end = new Date(current.endDate.replace(' ', 'T'));
    const lengthMs = end.getTime() - start.getTime();
    const prevEnd = new Date(start.getTime() - 1000);
    const prevStart = new Date(prevEnd.getTime() - lengthMs);
    return {startDate: formatDate(prevStart), endDate: formatDate(prevEnd)};
};

export const computeDelta = (
    current: number | undefined | null,
    previous: number | undefined | null,
): {percent: number; trend: 'up' | 'down' | 'flat'} | null => {
    if (current == null || previous == null) {
        return null;
    }
    if (previous === 0) {
        return null;
    }
    const percent = ((current - previous) / previous) * 100;
    if (Math.abs(percent) < 0.05) {
        if (current === 0) {
            return null;
        }
        return {percent: 0, trend: 'flat'};
    }
    return {percent, trend: percent > 0 ? 'up' : 'down'};
};
