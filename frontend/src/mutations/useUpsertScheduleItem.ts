import {useMutation, useQueryClient} from "@tanstack/react-query";
import {IdParam} from "../types.ts";
import {EventLineup, lineupClient, ScheduleItem, UpsertScheduleItemRequest} from "../api/lineup.client.ts";
import {GET_EVENT_LINEUP_QUERY_KEY} from "../queries/useGetEventLineup.ts";
import {GET_EVENT_LINEUP_PUBLIC_QUERY_KEY} from "../queries/useGetEventLineupPublic.ts";

export const useUpsertScheduleItem = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({eventId, itemId, item}: {
            eventId: IdParam,
            itemId?: IdParam,
            item: UpsertScheduleItemRequest,
            optimisticItem?: ScheduleItem,
        }) => itemId
            ? lineupClient.updateScheduleItem(eventId, itemId, item)
            : lineupClient.createScheduleItem(eventId, item),

        onMutate: async ({eventId, optimisticItem}) => {
            const queryKey = [GET_EVENT_LINEUP_QUERY_KEY, String(eventId)];
            const previous = queryClient.getQueryData<EventLineup>(queryKey);

            if (!optimisticItem || !previous) {
                return {previous: undefined, queryKey};
            }

            await queryClient.cancelQueries({queryKey});
            queryClient.setQueryData<EventLineup>(queryKey, {
                ...previous,
                schedule_items: previous.schedule_items.map(existing => existing.id === optimisticItem.id ? optimisticItem : existing),
            });

            return {previous, queryKey};
        },

        onError: (_, __, context) => {
            if (context?.previous) {
                queryClient.setQueryData(context.queryKey, context.previous);
            }
        },

        onSettled: (_, __, {eventId}) => Promise.all([
            queryClient.invalidateQueries({queryKey: [GET_EVENT_LINEUP_QUERY_KEY, String(eventId)]}),
            queryClient.invalidateQueries({queryKey: [GET_EVENT_LINEUP_PUBLIC_QUERY_KEY, String(eventId)]}),
        ]),
    });
};
