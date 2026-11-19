import {useMutation, useQueryClient} from "@tanstack/react-query";
import {IdParam} from "../../../types.ts";
import {crmClient} from "../api/crm.client.ts";
import {invalidateCrm, crmKeys} from "../queries/crmQueryKeys.ts";

export const useDeleteCrmTag = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({organizerId, tagId}: {organizerId: IdParam, tagId: IdParam}) => crmClient.deleteTag(organizerId, tagId),

        onSuccess: (_, variables) => invalidateCrm(queryClient, [crmKeys.tags(variables.organizerId), crmKeys.contacts(variables.organizerId), crmKeys.contactLists(variables.organizerId), crmKeys.segments(variables.organizerId), crmKeys.segmentCounts(variables.organizerId)]),
    });
};
