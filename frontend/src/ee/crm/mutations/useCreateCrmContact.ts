import {useMutation, useQueryClient} from "@tanstack/react-query";
import {IdParam} from "../../../types.ts";
import {crmClient, CrmContactRequest} from "../api/crm.client.ts";
import {invalidateCrm, audienceMembershipKeys} from "../queries/crmQueryKeys.ts";

export const useCreateCrmContact = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({organizerId, contact}: {organizerId: IdParam, contact: CrmContactRequest}) => crmClient.createContact(organizerId, contact),

        onSuccess: (_, variables) => invalidateCrm(queryClient, audienceMembershipKeys(variables.organizerId)),
    });
};
