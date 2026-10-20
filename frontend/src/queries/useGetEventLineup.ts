import {useQuery} from "@tanstack/react-query";
import {IdParam} from "../types.ts";
import {EventLineup, lineupClient} from "../api/lineup.client.ts";

export const GET_EVENT_LINEUP_QUERY_KEY = 'getEventLineup';

export const useGetEventLineup = (eventId: IdParam) => {
    return useQuery<EventLineup>({
        queryKey: [GET_EVENT_LINEUP_QUERY_KEY, String(eventId)],
        queryFn: async () => {
            const {data} = await lineupClient.getLineup(eventId);
            return data;
        },
    });
};
