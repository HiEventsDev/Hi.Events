import {useMemo} from "react";
import {Event, EventType} from "../../../../types.ts";
import {useGetAllEventOccurrences} from "../../../../queries/useGetAllEventOccurrences.ts";
import {occurrenceDayKeys} from "./ScheduleBuilder/scheduleDays.ts";

export const useOccurrenceDays = (event: Event): ReadonlySet<string> | undefined => {
    const isRecurring = event.type === EventType.RECURRING;
    const {data: occurrences} = useGetAllEventOccurrences(event.id, isRecurring);

    return useMemo(
        () => isRecurring && occurrences?.length ? new Set(occurrenceDayKeys(occurrences, event.timezone)) : undefined,
        [isRecurring, occurrences, event.timezone],
    );
};
