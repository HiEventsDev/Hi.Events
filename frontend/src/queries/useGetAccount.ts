import {useQuery} from "@tanstack/react-query";
import {Account} from "../types.ts";
import {accountClient} from "../api/account.client.ts";

export const GET_ACCOUNT_QUERY_KEY = 'getAccount';

export const useGetAccount = () => {
    return useQuery<Account>({
        queryKey: [GET_ACCOUNT_QUERY_KEY],

        queryFn: async () => {
            const {data} = await accountClient.getAccount();
            return data;
        }
    });
};
