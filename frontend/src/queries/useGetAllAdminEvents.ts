import {useQuery} from "@tanstack/react-query";
import {adminClient, GetAllEventsParams} from "../api/admin.client";

export const GET_ALL_EVENTS_QUERY_KEY = 'getAllEvents';

export const useGetAllAdminEvents = (params: GetAllEventsParams = {}) => {
    return useQuery({
        queryKey: [GET_ALL_EVENTS_QUERY_KEY, params],
        queryFn: async () => await adminClient.getAllEvents(params),
    });
};
