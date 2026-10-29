import {useQuery} from "@tanstack/react-query";
import {IdParam, QueryFilters} from "../types.ts";
import {organizerPublicClient} from "../api/organizer.client.ts";

export const GET_ORGANIZER_EVENTS_PUBLIC_QUERY = 'getOrganizerPublicEvents';

export const PAST_EVENTS_PREVIEW_FILTERS: QueryFilters = {
    pageNumber: 1,
    perPage: 6,
    sortBy: 'start_date',
    sortDirection: 'desc',
    additionalParams: {eventsStatus: 'ended'},
    filterFields: {},
};

export const UPCOMING_EVENTS_PREVIEW_FILTERS: QueryFilters = {
    pageNumber: 1,
    perPage: 4,
    sortBy: 'start_date',
    sortDirection: 'asc',
    additionalParams: {eventsStatus: 'upcoming'},
    filterFields: {},
};

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
