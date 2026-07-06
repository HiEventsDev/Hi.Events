import {useQuery, UseQueryOptions} from '@tanstack/react-query';
import {AccountEmailSettings, IdParam} from '../types.ts';
import {accountClient} from '../api/account.client.ts';

export const GET_ACCOUNT_EMAIL_SETTINGS_QUERY_KEY = 'accountEmailSettings';

export const useGetAccountEmailSettings = (
    accountId: IdParam,
    options?: Partial<UseQueryOptions<AccountEmailSettings | null>>
) => {
    return useQuery({
        queryKey: [GET_ACCOUNT_EMAIL_SETTINGS_QUERY_KEY, accountId],
        queryFn: async () => {
            const {data} = await accountClient.getEmailSettings(accountId);
            return data;
        },
        ...options,
    });
};
