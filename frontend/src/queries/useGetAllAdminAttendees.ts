import {useQuery} from "@tanstack/react-query";
import {adminClient, GetAllAttendeesParams} from "../api/admin.client";

export const GET_ALL_ADMIN_ATTENDEES_QUERY_KEY = 'getAllAdminAttendees';

export const useGetAllAdminAttendees = (params: GetAllAttendeesParams = {}) => {
    return useQuery({
        queryKey: [GET_ALL_ADMIN_ATTENDEES_QUERY_KEY, params],
        queryFn: async () => await adminClient.getAllAttendees(params),
    });
};
