import {useQuery} from "@tanstack/react-query";
import {TwoFactorStatus} from "../types.ts";
import {twoFactorClient} from "../api/twoFactor.client.ts";

export const GET_TWO_FACTOR_STATUS_QUERY_KEY = 'getTwoFactorStatus';

export const useGetTwoFactorStatus = () => {
    return useQuery<TwoFactorStatus>({
        queryKey: [GET_TWO_FACTOR_STATUS_QUERY_KEY],
        queryFn: async () => {
            const {data} = await twoFactorClient.status();
            return data;
        },
    });
};
