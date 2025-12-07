import {useMutation, useQueryClient} from '@tanstack/react-query';
import {UpsertVatSettingRequest, vatClient} from '../api/vat.client.ts';
import {IdParam} from '../types.ts';
import {GET_ACCOUNT_VAT_SETTING_QUERY_KEY} from '../queries/useGetAccountVatSetting.ts';

export const useUpsertAccountVatSetting = (accountId: IdParam) => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async (data: UpsertVatSettingRequest) => {
            return await vatClient.upsertVatSetting(accountId, data);
        },
        onSuccess: () => {
            queryClient.invalidateQueries({
                queryKey: [GET_ACCOUNT_VAT_SETTING_QUERY_KEY, accountId],
            });
        },
    });
};
