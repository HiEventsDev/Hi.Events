import {useMutation, useQueryClient} from "@tanstack/react-query";
import {IdParam} from "../../../types.ts";
import {seatMapClient} from "../api/seat-map.client.ts";
import {SeatMapLayout} from "../components/lib/types.ts";
import {GET_SEAT_MAPS_QUERY_KEY} from "../queries/useGetSeatMaps.ts";

export const useCreateSeatMap = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({organizerId, name, layout}: {organizerId: IdParam; name: string; layout: SeatMapLayout}) =>
            seatMapClient.create(organizerId, name, layout),

        onSuccess: () => queryClient.invalidateQueries({queryKey: [GET_SEAT_MAPS_QUERY_KEY]}),
    });
};
