import {useMutation, useQueryClient} from "@tanstack/react-query";
import {IdParam} from "../../../types.ts";
import {crmClient, CrmTagRequest} from "../api/crm.client.ts";
import {invalidateCrm, crmKeys} from "../queries/crmQueryKeys.ts";

export const useCreateCrmTag = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({organizerId, tag}: {organizerId: IdParam, tag: CrmTagRequest}) => crmClient.createTag(organizerId, tag),

        onSuccess: (_, variables) => invalidateCrm(queryClient, [crmKeys.tags(variables.organizerId)]),
    });
};
