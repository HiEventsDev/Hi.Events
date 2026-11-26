import {useMutation, useQueryClient} from "@tanstack/react-query";
import {IdParam} from "../../../types.ts";
import {crmClient} from "../api/crm.client.ts";
import {invalidateCrm, crmKeys} from "../queries/crmQueryKeys.ts";

export const useDeleteCrmSegment = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({organizerId, segmentId}: {organizerId: IdParam, segmentId: IdParam}) => crmClient.deleteSegment(organizerId, segmentId),

        onSuccess: (_, variables) => invalidateCrm(queryClient, [crmKeys.segments(variables.organizerId)]),
    });
};
