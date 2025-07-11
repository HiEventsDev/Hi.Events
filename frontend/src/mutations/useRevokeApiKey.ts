import {useMutation} from "@tanstack/react-query";
import {RevokeApiKeyRequest} from "../types.ts";
import {apiKeysClient} from "../api/api-keys.client.ts";

export const useRevokeApiKey = () => {
    return useMutation({
        mutationFn: ({tokenId}: {
            tokenId: IdParam
        }) => apiKeysClient.revoke(tokenId)
    });
}