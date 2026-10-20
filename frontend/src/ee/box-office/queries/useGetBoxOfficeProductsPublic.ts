import {useQuery} from "@tanstack/react-query";
import {IdParam} from "../../../types.ts";
import {publicBoxOfficeClient} from "../api/box-office-public.client.ts";

export const GET_BOX_OFFICE_PRODUCTS_PUBLIC_QUERY_KEY = 'getBoxOfficeProductsPublic';

export const useGetBoxOfficeProductsPublic = (boxOfficeShortId: IdParam, eventOccurrenceId: number | null | undefined) => {
    return useQuery({
        queryKey: [GET_BOX_OFFICE_PRODUCTS_PUBLIC_QUERY_KEY, boxOfficeShortId, eventOccurrenceId ?? null],
        queryFn: async () => {
            const {data} = await publicBoxOfficeClient.getProducts(boxOfficeShortId);
            return data;
        },
        refetchInterval: 60000,
    });
};
