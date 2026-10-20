import type {EventLineup, ScheduleItem} from "../api/lineup.client.ts";

export const DAY_BOUNDARY_HOUR = 5;

// eslint-disable-next-line lingui/no-unlocalized-strings
export const LOCAL_DATETIME_FORMAT = 'YYYY-MM-DD HH:mm:ss';

// eslint-disable-next-line lingui/no-unlocalized-strings
export const DATE_INPUT_FORMAT = 'YYYY-MM-DD';

// eslint-disable-next-line lingui/no-unlocalized-strings
export const TIME_INPUT_FORMAT = 'HH:mm';

const toInstant = (utcDate: string): number => /[zZ]$|[+-]\d{2}:?\d{2}$/.test(utcDate)
    ? Date.parse(utcDate)
    : Date.parse(`${utcDate.replace(' ', 'T')}Z`);

const localParts = (instant: number, tz: string) => {
    // eslint-disable-next-line lingui/no-unlocalized-strings
    const parts = new Intl.DateTimeFormat('en-CA', {timeZone: tz, year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', hourCycle: 'h23'})
        .formatToParts(new Date(instant));
    const value = (type: string) => Number(parts.find(part => part.type === type)?.value);
    return {year: value('year'), month: value('month'), day: value('day'), hour: value('hour')};
};

export const scheduleDayKey = (utcDate: string, tz: string): string => {
    const {year, month, day, hour} = localParts(toInstant(utcDate), tz);
    const date = new Date(Date.UTC(year, month - 1, hour < DAY_BOUNDARY_HOUR ? day - 1 : day));
    return date.toISOString().slice(0, 10);
};

export interface ScheduleDay {
    key: string;
    items: ScheduleItem[];
}

export const groupScheduleByDay = (items: ScheduleItem[], tz: string): ScheduleDay[] => {
    const days = new Map<string, ScheduleItem[]>();

    [...items]
        .sort((a, b) => toInstant(a.starts_at) - toInstant(b.starts_at))
        .forEach(item => {
            const key = scheduleDayKey(item.starts_at, tz);
            days.set(key, [...(days.get(key) ?? []), item]);
        });

    return [...days.entries()].map(([key, dayItems]) => ({key, items: dayItems}));
};

export const lineupForOccurrence = (
    lineup: EventLineup,
    occurrence: { start_date: string, end_date?: string | null },
    tz: string,
): EventLineup => {
    const firstDay = scheduleDayKey(occurrence.start_date, tz);
    const lastDay = scheduleDayKey(occurrence.end_date ?? occurrence.start_date, tz);
    const items = lineup.schedule_items.filter(item => {
        const day = scheduleDayKey(item.starts_at, tz);
        return day >= firstDay && day <= lastDay;
    });
    const scheduledAnywhere = new Set(lineup.schedule_items.flatMap(item => item.lineup_entry_ids));
    const scheduledOnDate = new Set(items.flatMap(item => item.lineup_entry_ids));

    return {
        ...lineup,
        entries: lineup.entries.filter(entry => scheduledOnDate.has(entry.id) || !scheduledAnywhere.has(entry.id)),
        schedule_items: items,
    };
};
