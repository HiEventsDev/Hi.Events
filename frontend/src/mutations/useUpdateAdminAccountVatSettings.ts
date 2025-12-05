import {useMutation, useQueryClient} from '@tanstack/react-query';
import {adminClient, UpdateAccountVatSettingsData} from '../api/admin.client';
import {IdParam} from '../types';

export const useUpdateAdminAccountVatSettings = (accountId: IdParam) => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async (data: UpdateAccountVatSettingsData) => {
            return await adminClient.updateAccountVatSettings(accountId, data);
        },
        onSuccess: () => {
            queryClient.invalidateQueries({
                queryKey: ['admin', 'account', accountId],
            });
        },
    });
};
