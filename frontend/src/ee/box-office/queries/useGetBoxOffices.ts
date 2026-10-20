import {useQuery} from "@tanstack/react-query";
import {IdParam, QueryFilters} from "../../../types.ts";
import {boxOfficeClient} from "../api/box-office.client.ts";

export const GET_EVENT_BOX_OFFICES_QUERY_KEY = 'getEventBoxOffices';

export const useGetBoxOffices = (eventId: IdParam, pagination: QueryFilters | null = null, enabled = true) => {
    return useQuery({
        queryKey: [GET_EVENT_BOX_OFFICES_QUERY_KEY, eventId, pagination],
        queryFn: async () => await boxOfficeClient.all(eventId, pagination),
        enabled,
    });
};
