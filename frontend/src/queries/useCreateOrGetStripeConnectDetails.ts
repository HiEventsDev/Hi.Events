import {useQuery} from '@tanstack/react-query';
import {IdParam, StripeConnectDetails} from '../types.ts';
import {accountClient} from "../api/account.client.ts";
import {AxiosError} from "axios";

export const GET_STRIPE_CONNECT_ACCOUNT_DETAILS = 'getStripeConnectAccountDetails';

export const useCreateOrGetStripeConnectDetails = (accountId: IdParam) => {
    return useQuery<StripeConnectDetails, AxiosError>(
        [GET_STRIPE_CONNECT_ACCOUNT_DETAILS],
        async () => {
            const {data} = await accountClient.getStripeConnectDetails(accountId);
            return data;
        }, {
            enabled: accountId !== undefined,
            retry: false,
        }
    )
};