import {useQuery} from "@tanstack/react-query";
import {adminClient, GetAllFailedJobsParams} from "../api/admin.client";

export const GET_ADMIN_FAILED_JOBS_QUERY_KEY = ['admin', 'failed-jobs'];

export const useGetAdminFailedJobs = (params: GetAllFailedJobsParams = {}) => {
    return useQuery({
        queryKey: [...GET_ADMIN_FAILED_JOBS_QUERY_KEY, params],
        queryFn: () => adminClient.getAllFailedJobs(params),
    });
};
