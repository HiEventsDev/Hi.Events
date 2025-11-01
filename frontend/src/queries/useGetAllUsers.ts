import {useQuery} from "@tanstack/react-query";
import {adminClient, GetAllUsersParams} from "../api/admin.client";

export const GET_ALL_USERS_QUERY_KEY = 'getAllUsers';

export const useGetAllUsers = (params: GetAllUsersParams = {}) => {
    return useQuery({
        queryKey: [GET_ALL_USERS_QUERY_KEY, params],
        queryFn: async () => await adminClient.getAllUsers(params),
    });
};
