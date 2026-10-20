import {useQuery} from "@tanstack/react-query";
import {accountSsoClient} from "../api/accountSso.client.ts";
import {IdParam} from "../../../types.ts";

export const GET_ACCOUNT_SSO_QUERY_KEY = 'getAccountSso';

export const useGetAccountSso = (accountId: IdParam | undefined, enabled = true) => {
    return useQuery({
        queryKey: [GET_ACCOUNT_SSO_QUERY_KEY, String(accountId)],
        queryFn: () => accountSsoClient.get(accountId as IdParam),
        enabled: enabled && !!accountId,
    });
};
