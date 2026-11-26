import {useMutation, useQueryClient} from "@tanstack/react-query";
import {IdParam} from "../../../types.ts";
import {crmClient, CrmContactRequest} from "../api/crm.client.ts";
import {exactly, invalidateCrm, crmKeys} from "../queries/crmQueryKeys.ts";

export const useUpdateCrmContact = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({organizerId, contactId, contact}: {organizerId: IdParam, contactId: IdParam, contact: CrmContactRequest}) => crmClient.updateContact(organizerId, contactId, contact),

        onSuccess: (_, variables) => invalidateCrm(queryClient, [exactly(crmKeys.contact(variables.organizerId, variables.contactId)), crmKeys.contactLists(variables.organizerId)]),
    });
};
