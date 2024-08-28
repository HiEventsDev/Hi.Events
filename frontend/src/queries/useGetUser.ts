import {useQuery} from "@tanstack/react-query";
import {IdParam, User} from "../types.ts";
import {userClient} from "../api/user.client.ts";

export const GET_USER_QUERY_KEY = 'getUser';

export const useGetUser = (userId: IdParam) => {
    return useQuery<User>({
        queryKey: [GET_USER_QUERY_KEY, userId],

        queryFn: async () => {
            const {data} = await userClient.findByID(userId);
            return data;
        }
    });
};
