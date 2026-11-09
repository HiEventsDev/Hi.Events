import {useMutation, useQueryClient} from "@tanstack/react-query";
import {oauthClient} from "../api/oauth.client.ts";
import {GET_OAUTH_CONNECTIONS_QUERY_KEY} from "../queries/useGetOAuthConnections.ts";
import {ApproveOAuthRequest, IdParam} from "../types.ts";

export const useApproveOAuthRequest = () => {
    return useMutation({
        mutationFn: ({requestId, request}: { requestId: string, request: ApproveOAuthRequest }) => oauthClient.approve(requestId, request),
    });
};

export const useDenyOAuthRequest = () => {
    return useMutation({
        mutationFn: (requestId: string) => oauthClient.deny(requestId),
    });
};

export const useDisconnectOAuthConnection = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (connectionId: IdParam) => oauthClient.disconnect(connectionId),
        onSuccess: () => queryClient.invalidateQueries({queryKey: [GET_OAUTH_CONNECTIONS_QUERY_KEY]}),
    });
};
