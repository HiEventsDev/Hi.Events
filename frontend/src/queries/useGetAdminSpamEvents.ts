import {useQuery} from "@tanstack/react-query";
import {adminClient, GetAllAdminSpamEventsParams} from "../api/admin.client";

export const GET_ADMIN_SPAM_EVENTS_QUERY_KEY = ['admin', 'spam-events'];

export const useGetAdminSpamEvents = (params: GetAllAdminSpamEventsParams = {}) => {
    return useQuery({
        queryKey: [...GET_ADMIN_SPAM_EVENTS_QUERY_KEY, params],
        queryFn: () => adminClient.getAllAdminSpamEvents(params),
    });
};
