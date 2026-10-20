import {useMutation, useQueryClient} from "@tanstack/react-query";
import {apiKeyClient} from "../api/apiKey.client.ts";
import {GET_API_KEYS_QUERY_KEY} from "../queries/useGetApiKeys.ts";
import {CreateApiKeyRequest, IdParam} from "../types.ts";

export const useCreateApiKey = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (request: CreateApiKeyRequest) => apiKeyClient.create(request),
        gcTime: 0,
        onSuccess: () => queryClient.invalidateQueries({queryKey: [GET_API_KEYS_QUERY_KEY]}),
    });
};

export const useDeleteApiKey = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (apiKeyId: IdParam) => apiKeyClient.delete(apiKeyId),
        onSuccess: () => queryClient.invalidateQueries({queryKey: [GET_API_KEYS_QUERY_KEY]}),
    });
};
