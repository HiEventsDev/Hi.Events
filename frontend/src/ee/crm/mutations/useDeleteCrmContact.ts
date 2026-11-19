import {useMutation, useQueryClient} from "@tanstack/react-query";
import {IdParam} from "../../../types.ts";
import {crmClient} from "../api/crm.client.ts";
import {audienceMembershipKeys, crmKeys, invalidateCrm} from "../queries/crmQueryKeys.ts";

export const useDeleteCrmContact = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({organizerId, contactId}: {organizerId: IdParam, contactId: IdParam}) => crmClient.deleteContact(organizerId, contactId),

        onSuccess: (_, variables) => {
            queryClient.removeQueries({queryKey: crmKeys.contact(variables.organizerId, variables.contactId)});
            void invalidateCrm(queryClient, [...audienceMembershipKeys(variables.organizerId), crmKeys.tags(variables.organizerId)]);
        },
    });
};
