import {useQuery} from "@tanstack/react-query";
import {IdParam, QueryFilters} from "../types.ts";
import {organizerClient} from "../api/organizer.client.ts";

export const GET_ORGANIZER_EVENTS_QUERY = 'getOrganizerEvents';

export const useGetOrganizerEvents = (organizerId: IdParam, pagination: QueryFilters) => {
    return useQuery(
        [GET_ORGANIZER_EVENTS_QUERY, organizerId, pagination],
        async () => {
            return await organizerClient.findEventsByOrganizerId(organizerId, pagination);
        }
    )
};