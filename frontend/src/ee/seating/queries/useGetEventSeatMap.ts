import {useQuery} from "@tanstack/react-query";
import {AxiosError} from "axios";
import {IdParam} from "../../../types.ts";
import {seatMapClient} from "../api/seat-map.client.ts";

export const GET_EVENT_SEAT_MAP_QUERY_KEY = 'getEventSeatMap';

export const useGetEventSeatMap = (eventId: IdParam, enabled = true) => {
    return useQuery({
        queryKey: [GET_EVENT_SEAT_MAP_QUERY_KEY, eventId],
        enabled,
        retry: false,
        queryFn: async () => {
            try {
                const {data} = await seatMapClient.getForEvent(eventId);
                return data;
            } catch (error) {
                if ((error as AxiosError).response?.status === 404) {
                    return null;
                }
                throw error;
            }
        },
    });
};
