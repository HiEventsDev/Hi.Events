import {useMutation, useQueryClient} from "@tanstack/react-query";
import {IdParam} from "../../../types.ts";
import {seatMapClient} from "../api/seat-map.client.ts";
import {GET_SEAT_MAPS_QUERY_KEY} from "../queries/useGetSeatMaps.ts";

export const useDeleteSeatMap = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({organizerId, seatMapId}: {organizerId: IdParam; seatMapId: IdParam}) =>
            seatMapClient.delete(organizerId, seatMapId),

        onSuccess: () => queryClient.invalidateQueries({queryKey: [GET_SEAT_MAPS_QUERY_KEY]}),
    });
};
