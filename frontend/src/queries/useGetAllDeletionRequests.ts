import {useQuery} from "@tanstack/react-query";
import {adminClient, GetAllDeletionRequestsParams} from "../api/admin.client";

export const GET_ALL_DELETION_REQUESTS_QUERY_KEY = 'getAllDeletionRequests';

export const useGetAllDeletionRequests = (params: GetAllDeletionRequestsParams = {}) => {
    return useQuery({
        queryKey: [GET_ALL_DELETION_REQUESTS_QUERY_KEY, params],
        queryFn: () => adminClient.getDeletionRequests(params),
    });
};
