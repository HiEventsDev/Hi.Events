import {useQuery, keepPreviousData} from "@tanstack/react-query";
import {QueryFilters} from "../types.ts";
import {imageClient} from "../api/image.client.ts";

export const GET_ACCOUNT_IMAGES_QUERY_KEY = 'getAccountImages';

export const useGetAccountImages = (queryFilters: QueryFilters) => {
    return useQuery({
        queryKey: [GET_ACCOUNT_IMAGES_QUERY_KEY, queryFilters],
        queryFn: async () => await imageClient.getAll(queryFilters),
        placeholderData: keepPreviousData,
    });
};
