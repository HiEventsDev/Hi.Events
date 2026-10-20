import {useMutation} from "@tanstack/react-query";
import {lifecycleEmailClient} from "../api/lifecycle-email.client";

export const useUnsubscribeFromLifecycleEmails = () => {
    return useMutation({
        mutationFn: (token: string) => lifecycleEmailClient.unsubscribe(token),
    });
};
