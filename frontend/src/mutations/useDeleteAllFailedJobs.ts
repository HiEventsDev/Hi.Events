import {useMutation, useQueryClient} from "@tanstack/react-query";
import {adminClient} from "../api/admin.client";
import {GET_ADMIN_FAILED_JOBS_QUERY_KEY} from "../queries/useGetAdminFailedJobs";

export const useDeleteAllFailedJobs = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: () => adminClient.deleteAllFailedJobs(),
        onSuccess: () => {
            queryClient.invalidateQueries({queryKey: GET_ADMIN_FAILED_JOBS_QUERY_KEY});
        },
    });
};
