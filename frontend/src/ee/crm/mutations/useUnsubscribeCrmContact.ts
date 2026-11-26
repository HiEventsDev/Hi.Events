import {useMutation, useQueryClient} from "@tanstack/react-query";
import {IdParam} from "../../../types.ts";
import {crmClient} from "../api/crm.client.ts";
import {CrmMarketingChannel} from "../types.ts";
import {exactly, invalidateCrm, crmKeys, audienceMembershipKeys} from "../queries/crmQueryKeys.ts";

export const useUnsubscribeCrmContact = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({organizerId, contactId, channel}: {organizerId: IdParam, contactId: IdParam, channel?: CrmMarketingChannel}) => crmClient.unsubscribeContact(organizerId, contactId, channel),

        onSuccess: (_, variables) => invalidateCrm(queryClient, [exactly(crmKeys.contact(variables.organizerId, variables.contactId)), crmKeys.contactConsent(variables.organizerId, variables.contactId), ...audienceMembershipKeys(variables.organizerId)]),
    });
};
