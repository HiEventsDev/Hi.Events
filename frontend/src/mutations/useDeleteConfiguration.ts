import {useMutation, useQueryClient} from "@tanstack/react-query";
import {adminClient} from "../api/admin.client";
import {IdParam} from "../types";

export const useDeleteConfiguration = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (configurationId: IdParam) => adminClient.deleteConfiguration(configurationId),
        onSuccess: () => {
            queryClient.invalidateQueries({queryKey: ['admin', 'configurations']});
        },
    });
};
