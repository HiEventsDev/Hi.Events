import {useMutation} from "@tanstack/react-query";
import {adminClient, LifecycleEmailContent, LifecycleNudgeType} from "../api/admin.client";

export const useSendTestLifecycleEmail = () => {
    return useMutation({
        mutationFn: ({nudgeType, data}: { nudgeType: LifecycleNudgeType, data: LifecycleEmailContent }) =>
            adminClient.sendTestLifecycleEmail(nudgeType, data),
    });
};
