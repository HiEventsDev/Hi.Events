import {useMutation, useQueryClient} from "@tanstack/react-query";
import {adminClient, LifecycleEmailContent, LifecycleNudgeType} from "../api/admin.client";
import {GET_ADMIN_LIFECYCLE_EMAILS_QUERY_KEY} from "../queries/useGetAdminLifecycleEmails";

export const useUpdateLifecycleEmail = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({nudgeType, data}: { nudgeType: LifecycleNudgeType, data: LifecycleEmailContent & { enabled: boolean } }) =>
            adminClient.updateLifecycleEmail(nudgeType, data),
        onSuccess: () => queryClient.invalidateQueries({queryKey: GET_ADMIN_LIFECYCLE_EMAILS_QUERY_KEY}),
    });
};
