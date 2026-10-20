import {useQuery} from "@tanstack/react-query";
import {IdParam} from "../types.ts";
import {EventLineup, lineupClientPublic} from "../api/lineup.client.ts";

export const GET_EVENT_LINEUP_PUBLIC_QUERY_KEY = 'getEventLineupPublic';

export const getEventLineupPublicQuery = (eventId: IdParam) => ({
    queryKey: [GET_EVENT_LINEUP_PUBLIC_QUERY_KEY, String(eventId)] as const,
    queryFn: async (): Promise<EventLineup> => {
        const {data} = await lineupClientPublic.getLineup(eventId);
        return data;
    },
    refetchOnWindowFocus: false,
    staleTime: 60_000,
    retry: false,
});

export const useGetEventLineupPublic = (eventId: IdParam | undefined) => {
    return useQuery<EventLineup>({
        ...getEventLineupPublicQuery(eventId as IdParam),
        enabled: !!eventId,
    });
};
