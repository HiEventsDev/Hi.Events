import {useQuery} from "@tanstack/react-query";
import {adminClient} from "../api/admin.client";
import {IdParam} from "../types";

export const useGetAdminAccount = (accountId: IdParam) => {
    return useQuery({
        queryKey: ['admin', 'account', accountId],
        queryFn: () => adminClient.getAccount(accountId),
        enabled: !!accountId,
    });
};
