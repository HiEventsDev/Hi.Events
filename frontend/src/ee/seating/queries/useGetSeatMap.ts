import {useQuery} from "@tanstack/react-query";
import {IdParam} from "../../../types.ts";
import {seatMapClient} from "../api/seat-map.client.ts";
import {GET_SEAT_MAPS_QUERY_KEY} from "./useGetSeatMaps.ts";

export const useGetSeatMap = (organizerId: IdParam, seatMapId: IdParam) => {
    return useQuery({
        queryKey: [GET_SEAT_MAPS_QUERY_KEY, organizerId, seatMapId],
        queryFn: async () => {
            const {data} = await seatMapClient.get(organizerId, seatMapId);
            return data;
        },
    });
};
