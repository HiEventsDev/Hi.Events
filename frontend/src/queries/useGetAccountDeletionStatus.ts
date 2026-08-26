import {useQuery} from "@tanstack/react-query";
import {AccountDeletionStatus} from "../types.ts";
import {accountClient} from "../api/account.client.ts";

export const GET_ACCOUNT_DELETION_STATUS_QUERY_KEY = 'getAccountDeletionStatus';

export const useGetAccountDeletionStatus = () => {
    return useQuery<AccountDeletionStatus>({
        queryKey: [GET_ACCOUNT_DELETION_STATUS_QUERY_KEY],

        queryFn: async () => {
            const {data} = await accountClient.getDeletionStatus();
            return data;
        }
    });
};
