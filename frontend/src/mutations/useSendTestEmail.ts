import {useMutation} from '@tanstack/react-query';
import {IdParam} from '../types.ts';
import {accountClient} from '../api/account.client.ts';

export const useSendTestEmail = (accountId: IdParam) => {
    return useMutation({
        mutationFn: async () => {
            return await accountClient.sendTestEmail(accountId);
        },
    });
};
