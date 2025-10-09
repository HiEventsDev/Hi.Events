import { useQuery } from '@tanstack/react-query';
import { IdParam, StripeConnectDetails } from '../types';
import { accountClient } from "../api/account.client";
import { AxiosError } from "axios";

export const GET_STRIPE_CONNECT_ACCOUNT_DETAILS = 'getStripeConnectAccountDetails';

export const useCreateOrGetStripeConnectDetails = (accountId: IdParam, enabled: boolean, platform?: string) => {
    return useQuery<StripeConnectDetails, AxiosError>({
        queryKey: [GET_STRIPE_CONNECT_ACCOUNT_DETAILS, accountId, platform],

        queryFn: async (): Promise<StripeConnectDetails> => {
            const { data } = await accountClient.getStripeConnectDetails(accountId, platform);
            return data;
        },

        enabled: enabled,
        retry: false,
    });
};
