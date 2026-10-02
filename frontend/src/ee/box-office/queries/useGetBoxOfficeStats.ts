import {keepPreviousData, useQuery} from "@tanstack/react-query";
import {BoxOfficeStatsRange, IdParam} from "../../../types.ts";
import {boxOfficeClient} from "../api/box-office.client.ts";

export const GET_BOX_OFFICE_STATS_QUERY_KEY = 'getBoxOfficeStats';

export const useGetBoxOfficeStats = (eventId: IdParam, boxOfficeId: IdParam, range: BoxOfficeStatsRange) => {
    return useQuery({
        queryKey: [GET_BOX_OFFICE_STATS_QUERY_KEY, eventId, boxOfficeId, range.from, range.to],
        placeholderData: keepPreviousData,

        queryFn: async () => {
            const {data} = await boxOfficeClient.stats(eventId, boxOfficeId, range);
            return data;
        }
    });
};
