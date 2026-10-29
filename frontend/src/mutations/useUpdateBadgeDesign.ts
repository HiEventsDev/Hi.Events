import {useMutation, useQueryClient} from "@tanstack/react-query";
import {BadgeDesign, IdParam} from "../types.ts";
import {badgeClient} from "../api/badge.client.ts";
import {GET_BADGE_DESIGN_QUERY_KEY} from "../queries/useGetBadgeDesign.ts";
import {GET_BADGES_QUERY_KEY} from "../queries/useGetBadges.ts";

export const useUpdateBadgeDesign = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({eventId, design}: { eventId: IdParam, design: BadgeDesign }) => badgeClient.updateDesign(eventId, design),
        onSuccess: (response, variables) => {
            queryClient.setQueryData([GET_BADGE_DESIGN_QUERY_KEY, variables.eventId], response.data);
            return queryClient.invalidateQueries({queryKey: [GET_BADGES_QUERY_KEY, variables.eventId]});
        },
    });
};
