import {useQuery} from "@tanstack/react-query";
import {apiKeyClient} from "../api/apiKey.client.ts";

export const GET_API_KEYS_QUERY_KEY = 'getApiKeys';

export const useGetApiKeys = () => {
    return useQuery({
        queryKey: [GET_API_KEYS_QUERY_KEY],
        queryFn: () => apiKeyClient.all(),
    });
};
