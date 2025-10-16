import {useQuery, UseQueryOptions} from "@tanstack/react-query";
import {StripeConnectAccountsResponse, IdParam} from "../types.ts";
import {accountClient} from "../api/account.client.ts";

export const GET_STRIPE_CONNECT_ACCOUNTS_QUERY_KEY = 'getStripeConnectAccounts';

export const useGetStripeConnectAccounts = (accountId: IdParam, options?: Partial<UseQueryOptions<StripeConnectAccountsResponse>>) => {
    return useQuery<StripeConnectAccountsResponse>({
        queryKey: [GET_STRIPE_CONNECT_ACCOUNTS_QUERY_KEY, accountId],
        queryFn: async () => {
            const {data} = await accountClient.getStripeConnectAccounts(accountId);
            return data;
        },
        ...options
    });
};