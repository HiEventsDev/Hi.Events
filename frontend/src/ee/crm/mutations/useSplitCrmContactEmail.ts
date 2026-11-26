import {useMutation, useQueryClient} from "@tanstack/react-query";
import {IdParam} from "../../../types.ts";
import {crmClient} from "../api/crm.client.ts";
import {invalidateCrm, crmKeys, audienceMembershipKeys} from "../queries/crmQueryKeys.ts";

export const useSplitCrmContactEmail = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({organizerId, contactId, contactEmailId}: {organizerId: IdParam, contactId: IdParam, contactEmailId: IdParam}) => crmClient.splitContactEmail(organizerId, contactId, contactEmailId),

        onSuccess: (_, variables) => invalidateCrm(queryClient, [crmKeys.contact(variables.organizerId, variables.contactId), ...audienceMembershipKeys(variables.organizerId)]),
    });
};
