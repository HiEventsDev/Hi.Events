import {useMutation, useQueryClient} from "@tanstack/react-query";
import {CapacityAssignmentRequest, IdParam} from "../types.ts";
import {GET_EVENT_CAPACITY_ASSIGNMENTS_QUERY_KEY} from "../queries/useGetCapacityAssignments.ts";
import {capacityAssignmentClient} from "../api/capacity-assignment.client.ts";

export const useEditCapacityAssignment = () => {
    const queryClient = useQueryClient();

    return useMutation(
        ({capacityAssignmentData, eventId, capacityAssignmentId}: {
            eventId: IdParam,
            capacityAssignmentData: CapacityAssignmentRequest,
            capacityAssignmentId: IdParam,
        }) => capacityAssignmentClient.update(
            eventId,
            capacityAssignmentId,
            capacityAssignmentData,
        ),
        {
            onSuccess: () => queryClient.invalidateQueries({queryKey: [GET_EVENT_CAPACITY_ASSIGNMENTS_QUERY_KEY]}),
        }
    )
}
