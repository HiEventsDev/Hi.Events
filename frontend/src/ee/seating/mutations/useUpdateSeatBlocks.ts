import {useMutation, useQueryClient} from "@tanstack/react-query";
import {IdParam} from "../../../types.ts";
import {SeatBlockDates, seatMapClient} from "../api/seat-map.client.ts";
import {GET_OCCUPIED_SEATS_QUERY_KEY} from "../queries/useGetOccupiedSeats.ts";

type SeatBlockChange =
    | {action: 'block'; dates: SeatBlockDates; seatUids: string[]; reason: string | null}
    | {action: 'release'; dates: SeatBlockDates; seatUids: string[]};

export const useUpdateSeatBlocks = (eventId: IdParam) => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async (change: SeatBlockChange) => change.action === 'block'
            ? {action: change.action, ...await seatMapClient.blockSeats(eventId, change.dates, change.seatUids, change.reason)}
            : {action: change.action, ...await seatMapClient.releaseSeatBlocks(eventId, change.dates, change.seatUids)},

        onSettled: () => queryClient.invalidateQueries({queryKey: [GET_OCCUPIED_SEATS_QUERY_KEY, eventId]}),
    });
};
