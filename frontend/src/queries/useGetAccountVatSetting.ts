import {useQuery, UseQueryOptions} from '@tanstack/react-query';
import {AccountVatSetting, vatClient} from '../api/vat.client.ts';
import {IdParam} from '../types.ts';

export const GET_ACCOUNT_VAT_SETTING_QUERY_KEY = 'accountVatSetting';

export const useGetAccountVatSetting = (
    accountId: IdParam,
    options?: Partial<UseQueryOptions<AccountVatSetting | null>>
) => {
    return useQuery({
        queryKey: [GET_ACCOUNT_VAT_SETTING_QUERY_KEY, accountId],
        queryFn: async () => {
            const {data} = await vatClient.getVatSetting(accountId);
            return data;
        },
        ...options,
    });
};
