import {useMutation, useQueryClient} from "@tanstack/react-query";
import {IdParam} from "../../../types.ts";
import {crmClient, CrmSegmentRequest} from "../api/crm.client.ts";
import {invalidateCrm, crmKeys} from "../queries/crmQueryKeys.ts";

export const useCreateCrmSegment = () => {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({organizerId, segment}: {organizerId: IdParam, segment: CrmSegmentRequest}) => crmClient.createSegment(organizerId, segment),

        onSuccess: (_, variables) => invalidateCrm(queryClient, [crmKeys.segments(variables.organizerId)]),
    });
};
