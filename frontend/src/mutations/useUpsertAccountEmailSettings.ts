import {useMutation, useQueryClient} from '@tanstack/react-query';
import {AccountEmailSettings, IdParam} from '../types.ts';
import {accountClient} from '../api/account.client.ts';
import {GET_ACCOUNT_EMAIL_SETTINGS_QUERY_KEY} from '../queries/useGetAccountEmailSettings.ts';

export const useUpsertAccountEmailSettings = (accountId: IdParam) => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async (data: AccountEmailSettings) => {
            return await accountClient.updateEmailSettings(accountId, data);
        },
        onSuccess: () => {
            queryClient.invalidateQueries({
                queryKey: [GET_ACCOUNT_EMAIL_SETTINGS_QUERY_KEY, accountId],
            });
        },
    });
};
