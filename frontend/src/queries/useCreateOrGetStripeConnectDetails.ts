import { useQuery } from '@tanstack/react-query';
import { IdParam, StripeConnectDetails } from '../types';
import { accountClient } from "../api/account.client";
import { AxiosError } from "axios";

export const GET_STRIPE_CONNECT_ACCOUNT_DETAILS = 'getStripeConnectAccountDetails';

export const useCreateOrGetStripeConnectDetails = (accountId: IdParam) => {
    return useQuery<StripeConnectDetails, AxiosError>({
        queryKey: [GET_STRIPE_CONNECT_ACCOUNT_DETAILS, accountId],

        queryFn: async (): Promise<StripeConnectDetails> => {
            const { data } = await accountClient.getStripeConnectDetails(accountId);
            return data;
        },

        enabled: accountId !== undefined,
        retry: false,
    });
};
