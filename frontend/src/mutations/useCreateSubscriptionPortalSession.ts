import {useMutation} from "@tanstack/react-query";
import {IdParam} from "../types.ts";
import {accountClient} from "../api/account.client.ts";

export const useCreateSubscriptionPortalSession = () => {
    return useMutation({
        mutationFn: ({accountId}: { accountId: IdParam }) => accountClient.createSubscriptionPortalSession(accountId),
    });
}
