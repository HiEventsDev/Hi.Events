import {useMutation, useQueryClient} from "@tanstack/react-query";
import {IdParam} from "../../../types.ts";
import {EventSeatMapRules, seatMapClient, SeatMapBandProducts} from "../api/seat-map.client.ts";
import {SeatMapLayout} from "../components/lib/types.ts";
import {GET_EVENT_SEAT_MAP_QUERY_KEY} from "../queries/useGetEventSeatMap.ts";
import {GET_EVENT_QUERY_KEY} from "../../../queries/useGetEvent.ts";

type EventSeatMapChange =
    | {action: 'attach'; seatMapId: IdParam}
    | {action: 'detach'}
    | {action: 'layout'; layout: SeatMapLayout; version?: number; confirmRelabel?: boolean}
    | {action: 'bandProducts'; bandProducts: SeatMapBandProducts[]}
    | {action: 'rules'; rules: EventSeatMapRules}
    | {action: 'sync'};

export const useUpdateEventSeatMap = (eventId: IdParam) => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async (change: EventSeatMapChange) => {
            switch (change.action) {
                case 'attach':
                    return seatMapClient.attach(eventId, change.seatMapId);
                case 'detach':
                    return seatMapClient.detach(eventId);
                case 'layout':
                    return seatMapClient.updateLayout(eventId, change.layout, change.version, change.confirmRelabel);
                case 'bandProducts':
                    return seatMapClient.updateBandProducts(eventId, change.bandProducts);
                case 'rules':
                    return seatMapClient.updateRules(eventId, change.rules);
                case 'sync':
                    return seatMapClient.syncFromSource(eventId, false);
            }
        },

        onSuccess: () => Promise.all([
            queryClient.invalidateQueries({queryKey: [GET_EVENT_SEAT_MAP_QUERY_KEY, eventId]}),
            queryClient.invalidateQueries({queryKey: [GET_EVENT_QUERY_KEY, eventId]}),
        ]),
    });
};
