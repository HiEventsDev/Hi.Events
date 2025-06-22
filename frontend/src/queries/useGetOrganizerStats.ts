import {useQuery} from "@tanstack/react-query";
import {IdParam} from "../types.ts";
import {organizerClient} from "../api/organizer.client.ts";

export const GET_ORGANIZER_STATS_QUERY_KEY = 'getOrganizerStats';

export const useGetOrganizerStats = (
    organizerId: IdParam,
    currencyCode?: string,
) => {
    return useQuery({
        queryKey: [GET_ORGANIZER_STATS_QUERY_KEY, organizerId, currencyCode],
        enabled: !!organizerId && !!currencyCode,
        queryFn: async () => {
            const {data} = await organizerClient.getOrganizerStats(organizerId, currencyCode as string);
            return data;
        }
    });
};
