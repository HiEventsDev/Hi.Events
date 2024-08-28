import {useQuery} from "@tanstack/react-query";
import {IdParam, QueryFilters} from "../types.ts";
import {organizerClient} from "../api/organizer.client.ts";

export const GET_ORGANIZER_EVENTS_QUERY = 'getOrganizerEvents';

export const useGetOrganizerEvents = (organizerId: IdParam, pagination: QueryFilters) => {
    return useQuery({
        queryKey: [GET_ORGANIZER_EVENTS_QUERY, organizerId, pagination],

        queryFn: async () => {
            return await organizerClient.findEventsByOrganizerId(organizerId, pagination);
        }
    });
};