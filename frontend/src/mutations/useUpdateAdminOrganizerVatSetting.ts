import {useMutation, useQueryClient} from "@tanstack/react-query";
import {adminClient, UpdateAdminOrganizerVatSettingData} from "../api/admin.client";
import {IdParam} from "../types";
import {GET_ADMIN_ACCOUNT_QUERY_KEY} from "../queries/useGetAdminAccount";

export const useUpdateAdminOrganizerVatSetting = (organizerId: IdParam) => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (data: UpdateAdminOrganizerVatSettingData) =>
            adminClient.updateOrganizerVatSetting(organizerId, data),
        onSuccess: () => {
            queryClient.invalidateQueries({queryKey: GET_ADMIN_ACCOUNT_QUERY_KEY});
        },
    });
};
