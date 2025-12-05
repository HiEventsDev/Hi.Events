import {useMutation, useQueryClient} from "@tanstack/react-query";
import {adminClient, UpdateConfigurationData} from "../api/admin.client";
import {IdParam} from "../types";

export const useUpdateConfiguration = (configurationId: IdParam) => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (data: UpdateConfigurationData) => adminClient.updateConfiguration(configurationId, data),
        onSuccess: () => {
            queryClient.invalidateQueries({queryKey: ['admin', 'configurations']});
        },
    });
};
