import {useQuery} from "@tanstack/react-query";
import {IdParam} from "../../../types.ts";
import {seatMapClientPublic} from "../api/seat-map.client.ts";

export const GET_EVENT_SEAT_MAP_PUBLIC_QUERY_KEY = 'getEventSeatMapPublic';

export const useGetEventSeatMapPublic = (eventId: IdParam, enabled: boolean) => {
    return useQuery({
        queryKey: [GET_EVENT_SEAT_MAP_PUBLIC_QUERY_KEY, eventId],
        enabled,
        queryFn: async () => {
            const {data} = await seatMapClientPublic.getSeatMap(eventId);
            return data;
        },
    });
};
