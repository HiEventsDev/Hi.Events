import {useQuery} from "@tanstack/react-query";
import {adminClient, GetAllAdminMessagesParams} from "../api/admin.client";

export const GET_ADMIN_MESSAGES_QUERY_KEY = ['admin', 'messages'];

export const useGetAdminMessages = (params: GetAllAdminMessagesParams = {}) => {
    return useQuery({
        queryKey: [...GET_ADMIN_MESSAGES_QUERY_KEY, params],
        queryFn: () => adminClient.getAllAdminMessages(params),
    });
};
