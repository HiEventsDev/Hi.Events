import {useState} from "react";
import {useQueryClient} from "@tanstack/react-query";
import {t} from "@lingui/macro";
import {IdParam, SubscriptionBillingInterval} from "../types.ts";
import {useCreateSubscriptionCheckoutSession} from "../mutations/useCreateSubscriptionCheckoutSession.ts";
import {useCreateSubscriptionPortalSession} from "../mutations/useCreateSubscriptionPortalSession.ts";
import {GET_ACCOUNT_SUBSCRIPTION_QUERY_KEY} from "../queries/useGetAccountSubscription.ts";
import {showError} from "../utilites/notifications.tsx";

export const useSubscriptionRedirect = (accountId: IdParam | undefined) => {
    const queryClient = useQueryClient();
    const checkoutMutation = useCreateSubscriptionCheckoutSession();
    const portalMutation = useCreateSubscriptionPortalSession();
    const [isRedirecting, setIsRedirecting] = useState(false);

    const callbacks = {
        onSuccess: ({url}: { url: string }) => {
            setIsRedirecting(true);
            window.location.assign(url);
        },
        onError: (error: any) => {
            if (error?.response?.status === 409) {
                queryClient.invalidateQueries({queryKey: [GET_ACCOUNT_SUBSCRIPTION_QUERY_KEY]});
            }
            showError(error?.response?.data?.message ?? t`Something went wrong. Please try again.`);
        },
    };

    return {
        startCheckout: (interval: SubscriptionBillingInterval) => {
            if (accountId) {
                checkoutMutation.mutate({accountId, interval}, callbacks);
            }
        },
        openPortal: () => {
            if (accountId) {
                portalMutation.mutate({accountId}, callbacks);
            }
        },
        isStartingCheckout: checkoutMutation.isPending || (isRedirecting && checkoutMutation.isSuccess),
        isOpeningPortal: portalMutation.isPending || (isRedirecting && portalMutation.isSuccess),
    };
};
