import {PeriodPreset} from "../components/common/PeriodSelector";

export interface DateRange {
    startDate: string;
    endDate: string;
}

const formatDate = (d: Date): string => {
    const pad = (n: number) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`;
};

export const periodPresetToDateRange = (preset: PeriodPreset): DateRange => {
    const now = new Date();
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
    }

    return {startDate: formatDate(start), endDate: formatDate(end)};
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
