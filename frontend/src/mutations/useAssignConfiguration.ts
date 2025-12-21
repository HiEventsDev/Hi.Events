import {useMutation, useQueryClient} from "@tanstack/react-query";
import {adminClient, AssignConfigurationData} from "../api/admin.client";
import {IdParam} from "../types";

export const useAssignConfiguration = (accountId: IdParam) => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (data: AssignConfigurationData) => adminClient.assignConfiguration(accountId, data),
        onSuccess: () => {
            queryClient.invalidateQueries({queryKey: ['admin', 'account', accountId]});
        },
    });
};
