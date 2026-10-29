import {keepPreviousData, useQuery} from "@tanstack/react-query";
import {BadgeQuery, IdParam} from "../types.ts";
import {badgeClient} from "../api/badge.client.ts";

export const GET_BADGES_QUERY_KEY = 'getBadges';

export const useGetBadges = (eventId: IdParam, query: BadgeQuery, enabled = true) => {
    return useQuery({
        queryKey: [GET_BADGES_QUERY_KEY, eventId, query],
        queryFn: async () => await badgeClient.getBadges(eventId, query),
        placeholderData: keepPreviousData,
        enabled: enabled && !!eventId,
    });
};
