import {useQuery} from "@tanstack/react-query";
import {IdParam, QueryFilters} from "../types.ts";
import {locationClient} from "../api/location.client.ts";

export const GET_ORGANIZER_LOCATIONS_QUERY_KEY = "getOrganizerLocations";

export const useGetOrganizerLocations = (organizerId: IdParam, pagination?: QueryFilters, enabled = true) => {
    return useQuery({
        queryKey: [GET_ORGANIZER_LOCATIONS_QUERY_KEY, organizerId, pagination],
        queryFn: () => locationClient.all(organizerId, pagination),
        enabled: enabled && organizerId !== undefined && organizerId !== null,
    });
};
