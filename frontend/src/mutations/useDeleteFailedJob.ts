import {useMutation, useQueryClient} from "@tanstack/react-query";
import {adminClient} from "../api/admin.client";
import {IdParam} from "../types";
import {GET_ADMIN_FAILED_JOBS_QUERY_KEY} from "../queries/useGetAdminFailedJobs";

export const useDeleteFailedJob = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (jobId: IdParam) => adminClient.deleteFailedJob(jobId),
        onSuccess: () => {
            queryClient.invalidateQueries({queryKey: GET_ADMIN_FAILED_JOBS_QUERY_KEY});
        },
    });
};
