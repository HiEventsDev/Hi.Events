import {useMutation, useQueryClient} from "@tanstack/react-query";
import {IdParam} from "../types.ts";
import {lineupClient, UpsertLineupEntryRequest} from "../api/lineup.client.ts";
import {GET_EVENT_LINEUP_QUERY_KEY} from "../queries/useGetEventLineup.ts";
import {GET_PROFILES_QUERY_KEY} from "../queries/useGetProfiles.ts";
import {GET_EVENT_LINEUP_PUBLIC_QUERY_KEY} from "../queries/useGetEventLineupPublic.ts";

export const useUpsertLineupEntry = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({eventId, entryId, entry}: {
            eventId: IdParam,
            entryId?: IdParam,
            entry: UpsertLineupEntryRequest,
        }) => entryId
            ? lineupClient.updateEntry(eventId, entryId, entry)
            : lineupClient.createEntry(eventId, entry),

        onSuccess: (_, {eventId}) => Promise.all([
            queryClient.invalidateQueries({queryKey: [GET_EVENT_LINEUP_QUERY_KEY, String(eventId)]}),
            queryClient.invalidateQueries({queryKey: [GET_EVENT_LINEUP_PUBLIC_QUERY_KEY, String(eventId)]}),
            queryClient.invalidateQueries({queryKey: [GET_PROFILES_QUERY_KEY], refetchType: 'none'}),
        ]),
    });
};
