import {useQuery} from "@tanstack/react-query";
import {ssoClient} from "../api/sso.client.ts";

export const GET_SSO_IDENTITIES_QUERY_KEY = 'getSsoIdentities';

export const useGetSsoIdentities = (enabled = true) => {
    return useQuery({
        queryKey: [GET_SSO_IDENTITIES_QUERY_KEY],
        queryFn: () => ssoClient.identities(),
        enabled,
    });
};
