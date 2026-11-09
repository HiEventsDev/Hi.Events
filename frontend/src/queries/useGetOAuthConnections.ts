import {useQuery} from "@tanstack/react-query";
import {oauthClient} from "../api/oauth.client.ts";

export const GET_OAUTH_CONNECTIONS_QUERY_KEY = 'getOAuthConnections';

export const useGetOAuthConnections = () => {
    return useQuery({
        queryKey: [GET_OAUTH_CONNECTIONS_QUERY_KEY],
        queryFn: () => oauthClient.connections(),
    });
};
