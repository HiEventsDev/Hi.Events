import {useQuery} from "@tanstack/react-query";
import {accountSsoClient} from "../api/accountSso.client.ts";
import {IdParam} from "../../../types.ts";

export const GET_SSO_ENFORCEMENT_PREVIEW_QUERY_KEY = 'getSsoEnforcementPreview';

export const useGetSsoEnforcementPreview = (accountId: IdParam | undefined, enabled = true) => {
    return useQuery({
        queryKey: [GET_SSO_ENFORCEMENT_PREVIEW_QUERY_KEY, String(accountId)],
        queryFn: () => accountSsoClient.enforcementPreview(accountId as IdParam),
        enabled: enabled && !!accountId,
    });
};
