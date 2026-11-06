import {useQuery} from "@tanstack/react-query";
import {IdParam} from "../types.ts";
import {badgeClient} from "../api/badge.client.ts";

export const GET_BADGE_DESIGN_QUERY_KEY = 'getBadgeDesign';

export const useGetBadgeDesign = (eventId: IdParam) => {
    return useQuery({
        queryKey: [GET_BADGE_DESIGN_QUERY_KEY, eventId],
        queryFn: async () => {
            const {data} = await badgeClient.getDesign(eventId);
            return data;
        },
        enabled: !!eventId,
    });
};
