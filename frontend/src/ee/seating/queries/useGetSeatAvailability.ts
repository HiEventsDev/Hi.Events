import {useEffect} from "react";
import {useQuery, useQueryClient} from "@tanstack/react-query";
import {IdParam} from "../../../types.ts";
import {seatMapClientPublic} from "../api/seat-map.client.ts";
import {GET_EVENT_SEAT_MAP_PUBLIC_QUERY_KEY} from "./useGetEventSeatMapPublic.ts";

export const GET_SEAT_AVAILABILITY_QUERY_KEY = 'getSeatAvailability';

const POLL_INTERVAL_MS = 10_000;

export const useGetSeatAvailability = (
    eventId: IdParam,
    occurrenceId: IdParam | undefined,
    poll: boolean,
    seatMapVersion: number,
) => {
    const queryClient = useQueryClient();
    const query = useQuery({
        queryKey: [GET_SEAT_AVAILABILITY_QUERY_KEY, eventId, occurrenceId],
        enabled: occurrenceId !== undefined,
        refetchInterval: poll ? POLL_INTERVAL_MS : false,
        refetchIntervalInBackground: false,
        queryFn: async () => {
            const {data} = await seatMapClientPublic.getAvailability(eventId, occurrenceId as IdParam);
            return data;
        },
    });

    const {dataUpdatedAt, refetch} = query;

    useEffect(() => {
        if (poll && dataUpdatedAt > 0 && Date.now() - dataUpdatedAt > POLL_INTERVAL_MS) {
            refetch();
        }
    }, [poll, dataUpdatedAt, refetch]);

    const availableVersion = query.data?.version;

    useEffect(() => {
        if (availableVersion !== undefined && availableVersion !== seatMapVersion) {
            queryClient.invalidateQueries({queryKey: [GET_EVENT_SEAT_MAP_PUBLIC_QUERY_KEY, eventId]});
        }
    }, [availableVersion, seatMapVersion, eventId, queryClient]);

    return query;
};
