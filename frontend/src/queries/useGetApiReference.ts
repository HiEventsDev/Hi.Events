import {useQuery} from "@tanstack/react-query";
import {apiReferenceClient} from "../api/apiReference.client.ts";

export const GET_API_REFERENCE_QUERY_KEY = 'getApiReference';

export const useGetApiReference = () => {
    return useQuery({
        queryKey: [GET_API_REFERENCE_QUERY_KEY],
        queryFn: () => apiReferenceClient.get(),
        staleTime: Infinity,
    });
};
