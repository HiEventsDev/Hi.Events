import {useMutation} from "@tanstack/react-query";
import {CreateApiKeyRequest} from "../types.ts";
import {apiKeysClient} from "../api/api-keys.client.ts";

export const useCreateApiKey = () => {
    return useMutation({
        mutationFn: ({apiKeyData}: {
            apiKeyData: CreateApiKeyRequest
        }) => apiKeysClient.createApiKey(apiKeyData)
    });
}