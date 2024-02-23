import {useQuery} from "@tanstack/react-query";
import {GenericDataResponse, User} from "../types.ts";
import {userClient} from "../api/user.client.ts";

export const GET_USERS_QUERY_KEY = 'getUsers';

export const useGetUsers = () => {
    return useQuery<GenericDataResponse<User[]>>({
            queryKey: [GET_USERS_QUERY_KEY],
            queryFn: async () => await userClient.all(),
        }
    )
}