import {useMutation, useQueryClient} from "@tanstack/react-query";
import {IdParam} from "../../../types.ts";
import {crmClient, CrmSmsConnectionRequest} from "../api/crm.client.ts";
import {crmKeys} from "../queries/crmQueryKeys.ts";

export const useListCrmSmsSenders = () => useMutation({
    mutationFn: ({organizerId, accountSid, authToken}: {organizerId: IdParam, accountSid: string, authToken: string | null}) =>
        crmClient.smsSenders(organizerId, accountSid, authToken),
});

export const useSaveCrmSmsConnection = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({organizerId, connection}: {organizerId: IdParam, connection: CrmSmsConnectionRequest}) =>
            crmClient.saveSmsConnection(organizerId, connection),
        onSuccess: (_, {organizerId}) => queryClient.invalidateQueries({queryKey: crmKeys.smsConnection(organizerId)}),
    });
};

export const useSendCrmSmsTest = () => useMutation({
    mutationFn: ({organizerId, phone}: {organizerId: IdParam, phone: string}) => crmClient.sendSmsTest(organizerId, phone),
});

export const useDeleteCrmSmsConnection = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({organizerId}: {organizerId: IdParam}) => crmClient.deleteSmsConnection(organizerId),
        onSuccess: (_, {organizerId}) => queryClient.invalidateQueries({queryKey: crmKeys.smsConnection(organizerId)}),
    });
};
