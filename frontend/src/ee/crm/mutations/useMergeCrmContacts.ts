import {useMutation, useQueryClient} from "@tanstack/react-query";
import {IdParam} from "../../../types.ts";
import {crmClient} from "../api/crm.client.ts";
import {audienceMembershipKeys, crmKeys, invalidateCrm} from "../queries/crmQueryKeys.ts";

export const useMergeCrmContacts = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({organizerId, contactId, sourceContactId}: {organizerId: IdParam, contactId: IdParam, sourceContactId: IdParam}) => crmClient.mergeContacts(organizerId, contactId, sourceContactId),

        onSuccess: (_, variables) => {
            queryClient.removeQueries({queryKey: crmKeys.contact(variables.organizerId, variables.sourceContactId)});
            void invalidateCrm(queryClient, [crmKeys.contact(variables.organizerId, variables.contactId), ...audienceMembershipKeys(variables.organizerId), crmKeys.tags(variables.organizerId)]);
        },
    });
};
