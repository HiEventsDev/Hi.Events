import {useMutation} from "@tanstack/react-query";
import {IdParam, SubscriptionBillingInterval} from "../types.ts";
import {accountClient} from "../api/account.client.ts";

export const useCreateSubscriptionCheckoutSession = () => {
    return useMutation({
        mutationFn: ({accountId, interval}: {
            accountId: IdParam,
            interval: SubscriptionBillingInterval,
        }) => accountClient.createSubscriptionCheckoutSession(accountId, interval),
    });
}
