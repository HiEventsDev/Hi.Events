import {useQuery} from "@tanstack/react-query";
import {ssoClient} from "../api/sso.client.ts";

export const GET_SSO_PROVIDERS_QUERY_KEY = 'getSsoProviders';

export const getSsoProvidersQuery = () => ({
    queryKey: [GET_SSO_PROVIDERS_QUERY_KEY] as const,
    queryFn: () => ssoClient.providers(),
    staleTime: 5 * 60 * 1000,
    retry: false,
});

export const useGetSsoProviders = () => useQuery(getSsoProvidersQuery());
