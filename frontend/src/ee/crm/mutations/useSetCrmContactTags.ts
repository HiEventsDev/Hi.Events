import {useMutation, useQueryClient} from "@tanstack/react-query";
import {IdParam} from "../../../types.ts";
import {crmClient} from "../api/crm.client.ts";
import {exactly, invalidateCrm, crmKeys} from "../queries/crmQueryKeys.ts";

export const useSetCrmContactTags = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({organizerId, contactId, tagIds}: {organizerId: IdParam, contactId: IdParam, tagIds: number[]}) => crmClient.setContactTags(organizerId, contactId, tagIds),

        onSuccess: (_, variables) => invalidateCrm(queryClient, [exactly(crmKeys.contact(variables.organizerId, variables.contactId)), crmKeys.contactLists(variables.organizerId), crmKeys.tags(variables.organizerId), crmKeys.segments(variables.organizerId), crmKeys.segmentCounts(variables.organizerId)]),
    });
};
