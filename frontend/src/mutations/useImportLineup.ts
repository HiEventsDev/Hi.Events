import {useMutation, useQueryClient} from "@tanstack/react-query";
import {IdParam} from "../types.ts";
import {ImportLineupRequest, lineupClient} from "../api/lineup.client.ts";
import {GET_EVENT_LINEUP_QUERY_KEY} from "../queries/useGetEventLineup.ts";
import {GET_EVENT_LINEUP_PUBLIC_QUERY_KEY} from "../queries/useGetEventLineupPublic.ts";
import {GET_PROFILES_QUERY_KEY} from "../queries/useGetProfiles.ts";

export const useImportLineup = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({eventId, payload}: { eventId: IdParam, organizerId: IdParam, payload: ImportLineupRequest }) =>
            lineupClient.importLineup(eventId, payload),

        onSuccess: (_, {eventId, organizerId}) => Promise.all([
            queryClient.invalidateQueries({queryKey: [GET_EVENT_LINEUP_QUERY_KEY, String(eventId)]}),
            queryClient.invalidateQueries({queryKey: [GET_EVENT_LINEUP_PUBLIC_QUERY_KEY, String(eventId)]}),
            queryClient.invalidateQueries({queryKey: [GET_PROFILES_QUERY_KEY, String(organizerId)]}),
        ]),
    });
};
