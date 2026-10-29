import dayjs from "dayjs";
import utc from "dayjs/plugin/utc";
import timezone from "dayjs/plugin/timezone";
import {Event, EventOccurrence, EventOccurrenceStatus, EventType} from "../types.ts";

dayjs.extend(utc);
dayjs.extend(timezone);

export const dateKey = (occ: EventOccurrence, tz: string): string =>
    dayjs.utc(occ.start_date).tz(tz).format('YYYY-MM-DD');

export const byStartDate = (a: EventOccurrence, b: EventOccurrence): number =>
    a.start_date < b.start_date ? -1 : a.start_date > b.start_date ? 1 : 0;

export const isBookable = (occ: EventOccurrence): boolean =>
    occ.status === EventOccurrenceStatus.ACTIVE && !occ.is_past;

export const isSelectable = (occ: EventOccurrence, waitlistAvailable?: boolean): boolean =>
    isBookable(occ) || (occ.status === EventOccurrenceStatus.SOLD_OUT && !!waitlistAvailable);

export const getActiveOccurrences = (occurrences: EventOccurrence[]): EventOccurrence[] =>
    occurrences.filter(
        occ => (occ.status === EventOccurrenceStatus.ACTIVE || occ.status === EventOccurrenceStatus.SOLD_OUT) && !occ.is_past
    );

export const defaultSlot = (slots: EventOccurrence[], waitlistAvailable?: boolean): EventOccurrence | undefined =>
    slots.find(isBookable) ?? slots.find(occ => isSelectable(occ, waitlistAvailable));

export const defaultDateKey = (event: Event, activeOccurrences: EventOccurrence[]): string => {
    const nextBookable = event.next_occurrence_start_date
        ? activeOccurrences.find(o => dayjs.utc(o.start_date).isSame(dayjs.utc(event.next_occurrence_start_date)))
        : undefined;
    return dateKey(nextBookable ?? activeOccurrences[0], event.timezone);
};

export const defaultOccurrence = (event: Event, waitlistAvailable?: boolean): EventOccurrence | undefined => {
    const activeOccurrences = getActiveOccurrences(event.occurrences || []).sort(byStartDate);
    if (event.type !== EventType.RECURRING || activeOccurrences.length === 0) {
        return undefined;
    }
    const focusedDate = defaultDateKey(event, activeOccurrences);
    return defaultSlot(activeOccurrences.filter(occ => dateKey(occ, event.timezone) === focusedDate), waitlistAvailable);
};
