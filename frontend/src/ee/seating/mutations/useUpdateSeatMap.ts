import {useMutation, useQueryClient} from "@tanstack/react-query";
import {IdParam} from "../../../types.ts";
import {seatMapClient} from "../api/seat-map.client.ts";
import {SeatMapLayout} from "../components/lib/types.ts";
import {GET_SEAT_MAPS_QUERY_KEY} from "../queries/useGetSeatMaps.ts";

export const useUpdateSeatMap = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({organizerId, seatMapId, name, layout, version}: {
            organizerId: IdParam;
            seatMapId: IdParam;
            name: string;
            layout: SeatMapLayout;
            version?: number;
        }) => seatMapClient.update(organizerId, seatMapId, name, layout, version),

        onSuccess: () => queryClient.invalidateQueries({queryKey: [GET_SEAT_MAPS_QUERY_KEY]}),
    });
};
