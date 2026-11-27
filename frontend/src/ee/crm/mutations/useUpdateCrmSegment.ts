import {useMutation, useQueryClient} from "@tanstack/react-query";
import {IdParam} from "../../../types.ts";
import {crmClient, CrmSegmentRequest} from "../api/crm.client.ts";
import {invalidateCrm, crmKeys} from "../queries/crmQueryKeys.ts";

export const useUpdateCrmSegment = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({organizerId, segmentId, segment}: {organizerId: IdParam, segmentId: IdParam, segment: CrmSegmentRequest}) => crmClient.updateSegment(organizerId, segmentId, segment),

        onSuccess: (_, variables) => invalidateCrm(queryClient, [crmKeys.segments(variables.organizerId), crmKeys.segmentCounts(variables.organizerId), crmKeys.contactLists(variables.organizerId)]),
    });
};
