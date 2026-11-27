import {useMutation, useQueryClient} from "@tanstack/react-query";
import {IdParam} from "../../../types.ts";
import {crmClient} from "../api/crm.client.ts";
import {audienceMembershipKeys, crmKeys, exactly, invalidateCrm} from "../queries/crmQueryKeys.ts";

export const useLookupCrmSuppressions = () => useMutation({
    mutationFn: ({organizerId, emails}: {organizerId: IdParam, emails: string[]}) => crmClient.lookupSuppressions(organizerId, emails),
});

export const usePreviewCrmSuppressionImport = () => useMutation({
    mutationFn: ({organizerId, file}: {organizerId: IdParam, file: File}) => crmClient.previewSuppressionImport(organizerId, file),
});

export const useImportCrmSuppressions = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({organizerId, file}: {organizerId: IdParam, file: File}) => crmClient.importSuppressions(organizerId, file),
        onSuccess: (_, {organizerId}) => invalidateCrm(queryClient, [crmKeys.imports(organizerId), crmKeys.contacts(organizerId), ...audienceMembershipKeys(organizerId)]),
    });
};

export const useClearCrmBounce = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({organizerId, contactId, contactEmailId}: {organizerId: IdParam, contactId: IdParam, contactEmailId: IdParam}) =>
            crmClient.clearBounce(organizerId, contactId, contactEmailId),
        onSuccess: ({data}, {organizerId, contactId}) => {
            queryClient.setQueryData(crmKeys.contact(organizerId, contactId), data);
            return invalidateCrm(queryClient, [
                exactly(crmKeys.contact(organizerId, contactId)),
                crmKeys.contactConsent(organizerId, contactId),
                ...audienceMembershipKeys(organizerId),
            ]);
        },
    });
};
