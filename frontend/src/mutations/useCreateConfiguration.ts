import {useMutation, useQueryClient} from "@tanstack/react-query";
import {adminClient, CreateConfigurationData} from "../api/admin.client";

export const useCreateConfiguration = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (data: CreateConfigurationData) => adminClient.createConfiguration(data),
        onSuccess: () => {
            queryClient.invalidateQueries({queryKey: ['admin', 'configurations']});
        },
    });
};
