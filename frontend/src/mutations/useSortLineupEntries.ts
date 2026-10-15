import {useMutation, useQueryClient} from "@tanstack/react-query";
import {IdParam} from "../types.ts";
import {EventLineup, lineupClient} from "../api/lineup.client.ts";
import {GET_EVENT_LINEUP_QUERY_KEY} from "../queries/useGetEventLineup.ts";
import {GET_EVENT_LINEUP_PUBLIC_QUERY_KEY} from "../queries/useGetEventLineupPublic.ts";

export const useSortLineupEntries = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({eventId, sortedIds}: { eventId: IdParam, sortedIds: number[] }) =>
            lineupClient.sortEntries(eventId, sortedIds),

        onMutate: async ({eventId, sortedIds}) => {
            const queryKey = [GET_EVENT_LINEUP_QUERY_KEY, String(eventId)];
            await queryClient.cancelQueries({queryKey});
            const previous = queryClient.getQueryData<EventLineup>(queryKey);

            if (previous) {
                const position = new Map(sortedIds.map((id, index) => [id, index]));
                queryClient.setQueryData<EventLineup>(queryKey, {
                    ...previous,
                    entries: [...previous.entries].sort((a, b) => (position.get(a.id) ?? 0) - (position.get(b.id) ?? 0)),
                });
            }

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
