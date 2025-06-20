import {useQuery} from "@tanstack/react-query";
import {GenericPaginatedResponse, IdParam, Order, QueryFilters} from "../types.ts";
import {organizerClient} from "../api/organizer.client.ts";

export const GET_ORGANIZER_ORDERS_QUERY_KEY = 'getOrganizerOrders';

export const useGetOrganizerOrders = (organizerId: IdParam, pagination: QueryFilters) => {
    return useQuery<GenericPaginatedResponse<Order>>({
            queryKey: [GET_ORGANIZER_ORDERS_QUERY_KEY, organizerId, pagination],
            queryFn: async () => await organizerClient.getOrganizerOrders(organizerId, pagination),
        }
    )
};
