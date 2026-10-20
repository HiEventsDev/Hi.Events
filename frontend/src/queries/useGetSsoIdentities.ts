import {useQuery} from "@tanstack/react-query";
import {ssoClient} from "../api/sso.client.ts";
import {SsoSignInMethods} from "../types.ts";

export const GET_SSO_IDENTITIES_QUERY_KEY = 'getSsoIdentities';

const signInMethodsQuery = (enabled: boolean) => ({
    queryKey: [GET_SSO_IDENTITIES_QUERY_KEY],
    queryFn: () => ssoClient.identities(),
    enabled,
});

export const useGetSsoIdentities = (enabled = true) => {
    return useQuery({
        ...signInMethodsQuery(enabled),
        select: (methods: SsoSignInMethods) => methods.identities,
    });
};

export const useGetSsoSignInMethods = () => {
    return useQuery(signInMethodsQuery(true));
};
