import {useQuery} from "@tanstack/react-query";
import dayjs from "dayjs";
import utc from "dayjs/plugin/utc";
import timezone from "dayjs/plugin/timezone";
import {eventOccurrenceClientPublic} from "../api/event-occurrence.client.ts";
import {EventOccurrence, IdParam} from "../types.ts";

dayjs.extend(utc);
dayjs.extend(timezone);

export const GET_EVENT_OCCURRENCES_PUBLIC_QUERY_KEY = "getEventOccurrencesPublic";

export const useGetEventOccurrencesPublic = (
    eventId: IdParam,
    monthKey: string,
    eventTimezone: string,
    enabled: boolean,
) => {
    return useQuery<EventOccurrence[]>({
        queryKey: [GET_EVENT_OCCURRENCES_PUBLIC_QUERY_KEY, eventId, monthKey],
        queryFn: async () => {
            const monthStart = dayjs.tz(`${monthKey}-01`, eventTimezone).startOf("month");
            const monthEnd = monthStart.endOf("month");
            const {data} = await eventOccurrenceClientPublic.all(
                eventId,
                monthStart.utc().format("YYYY-MM-DD HH:mm:ss"),
                monthEnd.utc().format("YYYY-MM-DD HH:mm:ss"),
            );
            return data;
        },
        staleTime: 60 * 1000,
        refetchOnWindowFocus: false,
        enabled: enabled && !!eventId && !!monthKey,
    });
};
