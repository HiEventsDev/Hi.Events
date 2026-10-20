import {useMutation, useQueryClient} from "@tanstack/react-query";
import {IdParam} from "../types.ts";
import {lineupClient, UpsertEventSpaceRequest} from "../api/lineup.client.ts";
import {GET_EVENT_LINEUP_QUERY_KEY} from "../queries/useGetEventLineup.ts";
import {GET_EVENT_LINEUP_PUBLIC_QUERY_KEY} from "../queries/useGetEventLineupPublic.ts";

export const useUpsertEventSpace = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({eventId, spaceId, space}: {
            eventId: IdParam,
            spaceId?: IdParam,
            space: UpsertEventSpaceRequest,
        }) => spaceId
            ? lineupClient.updateSpace(eventId, spaceId, space)
            : lineupClient.createSpace(eventId, space),

        onSuccess: (_, {eventId}) => Promise.all([
            queryClient.invalidateQueries({queryKey: [GET_EVENT_LINEUP_QUERY_KEY, String(eventId)]}),
            queryClient.invalidateQueries({queryKey: [GET_EVENT_LINEUP_PUBLIC_QUERY_KEY, String(eventId)]}),
        ]),
    });
};
