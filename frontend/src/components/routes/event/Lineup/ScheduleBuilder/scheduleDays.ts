import type {EventLineup} from "../../../../../api/lineup.client.ts";
import {DAY_BOUNDARY_HOUR, scheduleDayKey} from "../../../../../utilites/lineupSchedule.ts";

interface OccurrenceSpan {
    start_date: string;
    end_date?: string | null;
}

export interface ScheduleCoverage {
    scheduled: number;
    offDateOnly: number;
}

const addDays = (dayKey: string, days: number): string => {
    const date = new Date(`${dayKey}T00:00:00Z`);
    date.setUTCDate(date.getUTCDate() + days);
    return date.toISOString().slice(0, 10);
};

export const localScheduleDayKey = (local: string): string => {
    const [day, time = ''] = local.split(/[ T]/);
    return Number(time.slice(0, 2)) < DAY_BOUNDARY_HOUR ? addDays(day, -1) : day;
};

export const occurrenceDayKeys = (occurrences: OccurrenceSpan[], tz: string): string[] => {
    const days = new Set<string>();

    occurrences.forEach(occurrence => {
        const firstDay = scheduleDayKey(occurrence.start_date, tz);
        const lastDay = scheduleDayKey(occurrence.end_date ?? occurrence.start_date, tz);
        days.add(firstDay);
        for (let day = addDays(firstDay, 1); day <= lastDay; day = addDays(day, 1)) {
            days.add(day);
        }
    });

    return [...days].sort();
};

export const adjacentOccurrenceDay = (days: Iterable<string>, current: string, direction: 1 | -1): string | undefined => {
    const sorted = [...days];
    return direction > 0
        ? sorted.find(day => day > current)
        : sorted.reverse().find(day => day < current);
};

export const scheduleCoverage = (lineup: EventLineup, tz: string, occurrenceDays?: ReadonlySet<string>): ScheduleCoverage => {
    const onDate = new Set<number>();
    const anywhere = new Set<number>();

    lineup.schedule_items.forEach(item => {
        const shows = !occurrenceDays || occurrenceDays.has(scheduleDayKey(item.starts_at, tz));
        item.lineup_entry_ids.forEach(id => {
            anywhere.add(id);
            if (shows) {
                onDate.add(id);
            }
        });
    });

    return {
        scheduled: lineup.entries.filter(entry => onDate.has(entry.id)).length,
        offDateOnly: lineup.entries.filter(entry => anywhere.has(entry.id) && !onDate.has(entry.id)).length,
    };
};
