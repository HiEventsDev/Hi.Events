import {useMutation, useQueryClient} from '@tanstack/react-query';
import {UpsertVatSettingRequest, vatClient} from '../api/vat.client.ts';
import {IdParam} from '../types.ts';
import {GET_ORGANIZER_VAT_SETTING_QUERY_KEY} from '../queries/useGetOrganizerVatSetting.ts';

export const useUpsertOrganizerVatSetting = (organizerId: IdParam) => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async (data: UpsertVatSettingRequest) => {
            return await vatClient.upsertVatSetting(organizerId, data);
        },
        onSuccess: () => {
            queryClient.invalidateQueries({
                queryKey: [GET_ORGANIZER_VAT_SETTING_QUERY_KEY, organizerId],
            });
        },
    });
};
