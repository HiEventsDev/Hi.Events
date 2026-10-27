import {useQuery} from "@tanstack/react-query";
import {AccountSubscription, IdParam} from "../types.ts";
import {accountClient} from "../api/account.client.ts";

export const GET_ACCOUNT_SUBSCRIPTION_QUERY_KEY = 'getAccountSubscription';

interface Options {
    enabled?: boolean;
    refreshFromStripe?: boolean;
}

export const useGetAccountSubscription = (accountId: IdParam | undefined, {enabled = true, refreshFromStripe = false}: Options = {}) => {
    return useQuery<AccountSubscription>({
        queryKey: [GET_ACCOUNT_SUBSCRIPTION_QUERY_KEY, accountId?.toString(), refreshFromStripe],
        queryFn: async () => {
            const {data} = await accountClient.getSubscription(accountId as IdParam, refreshFromStripe);
            return data;
        },
        enabled: enabled && !!accountId,
    });
};
