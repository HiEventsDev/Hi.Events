import {useQuery} from "@tanstack/react-query";
import {oauthClient} from "../api/oauth.client.ts";

export const GET_OAUTH_AUTHORIZATION_REQUEST_QUERY_KEY = 'getOAuthAuthorizationRequest';

export const useGetOAuthAuthorizationRequest = (requestId: string | null) => {
    return useQuery({
        queryKey: [GET_OAUTH_AUTHORIZATION_REQUEST_QUERY_KEY, requestId],
        queryFn: () => oauthClient.authorizationRequest(requestId as string),
        enabled: !!requestId,
        retry: false,
    });
};
