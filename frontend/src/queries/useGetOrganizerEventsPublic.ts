import {useQuery} from "@tanstack/react-query";
import {IdParam, QueryFilters} from "../types.ts";
import {organizerPublicClient} from "../api/organizer.client.ts";

export const GET_ORGANIZER_EVENTS_PUBLIC_QUERY = 'getOrganizerPublicEvents';

export const useGetOrganizerPublicEvents = (organizerId: IdParam, pagination: QueryFilters, options?: {enabled?: boolean}) => {
    return useQuery({
        ...getOrganizerPublicEventsQuery(organizerId, pagination),
        enabled: options?.enabled ?? true
    });
}

export const getOrganizerPublicEventsQuery = (organizerId: IdParam, pagination: QueryFilters) => ({
    queryKey: [GET_ORGANIZER_EVENTS_PUBLIC_QUERY, organizerId, pagination],

    queryFn: async () => {
        return await organizerPublicClient.getEvents(organizerId, pagination);
    }
});
