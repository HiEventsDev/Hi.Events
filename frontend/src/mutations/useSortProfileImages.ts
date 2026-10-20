import {useMutation, useQueryClient} from "@tanstack/react-query";
import {IdParam} from "../types.ts";
import {lineupClient} from "../api/lineup.client.ts";
import {GET_PROFILES_QUERY_KEY} from "../queries/useGetProfiles.ts";
import {GET_EVENT_LINEUP_QUERY_KEY} from "../queries/useGetEventLineup.ts";
import {GET_EVENT_LINEUP_PUBLIC_QUERY_KEY} from "../queries/useGetEventLineupPublic.ts";

export const useSortProfileImages = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({organizerId, profileId, sortedIds}: {
            organizerId: IdParam,
            profileId: IdParam,
            sortedIds: IdParam[],
        }) => lineupClient.sortProfileImages(organizerId, profileId, sortedIds),

        onSettled: () => Promise.all([
            queryClient.invalidateQueries({queryKey: [GET_EVENT_LINEUP_QUERY_KEY]}),
            queryClient.invalidateQueries({queryKey: [GET_EVENT_LINEUP_PUBLIC_QUERY_KEY]}),
            queryClient.invalidateQueries({queryKey: [GET_PROFILES_QUERY_KEY]}),
        ]),
    });
};
