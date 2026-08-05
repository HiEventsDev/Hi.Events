import {useQuery, UseQueryOptions} from '@tanstack/react-query';
import {VatSetting, vatClient} from '../api/vat.client.ts';
import {IdParam} from '../types.ts';

export const GET_ORGANIZER_VAT_SETTING_QUERY_KEY = 'organizerVatSetting';

export const useGetOrganizerVatSetting = (
    organizerId: IdParam,
    options?: Partial<UseQueryOptions<VatSetting | null>>,
) => {
    return useQuery({
        queryKey: [GET_ORGANIZER_VAT_SETTING_QUERY_KEY, organizerId],
        queryFn: async () => {
            const {data} = await vatClient.getVatSetting(organizerId);
            return data;
        },
        ...options,
    });
};
