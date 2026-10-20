import {useQuery} from "@tanstack/react-query";
import {IdParam} from "../../../types.ts";
import {seatMapClient} from "../api/seat-map.client.ts";

export const GET_SEAT_MAPS_QUERY_KEY = 'getSeatMaps';

export const useGetSeatMaps = (organizerId: IdParam | undefined) => {
    return useQuery({
        queryKey: [GET_SEAT_MAPS_QUERY_KEY, organizerId],
        enabled: organizerId !== undefined,
        queryFn: async () => {
            const {data} = await seatMapClient.all(organizerId as IdParam);
            return data;
        },
    });
};
