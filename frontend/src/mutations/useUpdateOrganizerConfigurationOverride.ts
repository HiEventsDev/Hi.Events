import {useMutation, useQueryClient} from "@tanstack/react-query";
import {adminClient, UpdateOrganizerConfigurationOverrideData} from "../api/admin.client";
import {IdParam} from "../types";
import {GET_ADMIN_ACCOUNT_QUERY_KEY} from "../queries/useGetAdminAccount";

export const useUpdateOrganizerConfigurationOverride = (organizerId: IdParam) => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (data: UpdateOrganizerConfigurationOverrideData) =>
            adminClient.updateOrganizerConfigurationOverride(organizerId, data),
        onSuccess: () => {
            queryClient.invalidateQueries({queryKey: GET_ADMIN_ACCOUNT_QUERY_KEY});
        },
    });
};
