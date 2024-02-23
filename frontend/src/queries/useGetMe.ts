import {useQuery} from "@tanstack/react-query";
import {User} from "../types.ts";
import {userClient} from "../api/user.client.ts";

export const GET_ME_QUERY_KEY = 'getGetMe';

export const useGetMe = () => {
    return useQuery<User, Error>(
        [GET_ME_QUERY_KEY],
        async () => {
            const {data} = await userClient.me();
            return data;
        },
        {
            retry: false,
        },
    )
};