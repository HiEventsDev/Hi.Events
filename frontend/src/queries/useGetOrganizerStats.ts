import {useQuery} from "@tanstack/react-query";
import {IdParam} from "../types.ts";
import {organizerClient} from "../api/organizer.client.ts";

export const GET_ORGANIZER_STATS_QUERY_KEY = 'getOrganizerStats';

export const useGetOrganizerStats = (
    organizerId: IdParam,
    currencyCode?: string,
    options?: {startDate?: string; endDate?: string},
) => {
    const startDate = options?.startDate;
    const endDate = options?.endDate;
    const datesRequired = options !== undefined;
    const datesReady = !datesRequired || (!!startDate && !!endDate);

    return useQuery({
        queryKey: [GET_ORGANIZER_STATS_QUERY_KEY, organizerId, currencyCode, startDate, endDate],
        enabled: !!organizerId && !!currencyCode && datesReady,
        queryFn: async () => {
            const {data} = await organizerClient.getOrganizerStats(organizerId, {
                currencyCode: currencyCode as string,
                startDate,
                endDate,
            });
            return data;
        }
    });
};
