import {useMutation, useQueryClient} from "@tanstack/react-query";
import {IdParam} from "../../../types.ts";
import {crmClient, CrmTagRequest} from "../api/crm.client.ts";
import {invalidateCrm, crmKeys} from "../queries/crmQueryKeys.ts";

export const useUpdateCrmTag = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({organizerId, tagId, tag}: {organizerId: IdParam, tagId: IdParam, tag: CrmTagRequest}) => crmClient.updateTag(organizerId, tagId, tag),

        onSuccess: (_, variables) => invalidateCrm(queryClient, [crmKeys.tags(variables.organizerId), crmKeys.contacts(variables.organizerId), crmKeys.contactLists(variables.organizerId)]),
    });
};
