import {useMutation, useQueryClient} from '@tanstack/react-query';
import {adminClient, UpdateAccountConfigurationData} from '../api/admin.client';
import {IdParam} from '../types';

export const useUpdateAccountConfiguration = (accountId: IdParam) => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async (data: UpdateAccountConfigurationData) => {
            return await adminClient.updateAccountConfiguration(accountId, data);
        },
        onSuccess: () => {
            queryClient.invalidateQueries({
                queryKey: ['admin', 'account', accountId],
            });
        },
    });
};
