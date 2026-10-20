import {useQuery} from "@tanstack/react-query";
import {IdParam, QueryFilters} from "../../../types.ts";
import {publicBoxOfficeClient} from "../api/box-office-public.client.ts";

export const GET_BOX_OFFICE_ORDERS_PUBLIC_QUERY_KEY = 'getBoxOfficeOrdersPublic';

export const useGetBoxOfficeOrdersPublic = (boxOfficeShortId: IdParam, pagination: QueryFilters) => {
    return useQuery({
        queryKey: [GET_BOX_OFFICE_ORDERS_PUBLIC_QUERY_KEY, boxOfficeShortId, pagination],
        queryFn: async () => await publicBoxOfficeClient.getOrders(boxOfficeShortId, pagination),
        refetchInterval: 15000,
    });
};
