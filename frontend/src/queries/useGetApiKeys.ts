import {useQuery} from "@tanstack/react-query";
import {GenericDataResponse, ApiKey} from "../types.ts";
import {apiKeysClient} from "../api/api-keys.client.ts";

export const GET_API_KEYS_QUERY_KEY = 'getApiKeys';

export const useGetApiKeys = () => {
    return useQuery<GenericDataResponse<ApiKey[]>>({
            queryKey: [GET_API_KEYS_QUERY_KEY],
            queryFn: async () => await apiKeysClient.all(),
        }
    )
}