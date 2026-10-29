import {useGetAccount} from "../queries/useGetAccount.ts";
import {useGetAccountSubscription} from "../queries/useGetAccountSubscription.ts";
import {useAccess} from "./useAccess.ts";

export const useBrandingSubscription = ({refreshFromStripe = false}: { refreshFromStripe?: boolean } = {}) => {
    const {can, isLoaded, isRestricted} = useAccess();
    const {data: account} = useGetAccount();
    const subscriptionQuery = useGetAccountSubscription(account?.id, {enabled: isLoaded, refreshFromStripe});
    const subscription = subscriptionQuery.data;

    return {
        account,
        subscription,
        subscriptionQuery,
        isSettled: subscriptionQuery.isSuccess || subscriptionQuery.isError,
        isAvailable: !!subscription?.available,
        isActive: !!subscription?.active,
        canManageBilling: isLoaded && !isRestricted && can('MANAGE_ACCOUNT'),
        prices: subscription?.prices ?? [],
    };
};
