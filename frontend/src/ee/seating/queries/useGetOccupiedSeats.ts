import {useQuery} from "@tanstack/react-query";
import {IdParam} from "../../../types.ts";
import {seatMapClient} from "../api/seat-map.client.ts";

export const GET_OCCUPIED_SEATS_QUERY_KEY = 'getOccupiedSeats';

const POLL_INTERVAL_MS = 15_000;

export const useGetOccupiedSeats = (eventId: IdParam, occurrenceId: IdParam | undefined) => {
    return useQuery({
        queryKey: [GET_OCCUPIED_SEATS_QUERY_KEY, eventId, occurrenceId],
        enabled: occurrenceId !== undefined,
        refetchInterval: POLL_INTERVAL_MS,
        queryFn: async () => {
            const {data} = await seatMapClient.getOccupiedSeats(eventId, occurrenceId as IdParam);
            return data;
        },
    });
};
