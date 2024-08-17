import {useMutation, useQueryClient} from "@tanstack/react-query";
import {CapacityAssignmentRequest, IdParam} from "../types.ts";
import {GET_EVENT_CAPACITY_ASSIGNMENTS_QUERY_KEY} from "../queries/useGetCapacityAssignments.ts";
import {capacityAssignmentClient} from "../api/capacity-assignment.client.ts";

export const useCreateCapacityAssignment = () => {
    const queryClient = useQueryClient();

    return useMutation(
        ({capacityAssignmentData, eventId}: {
            eventId: IdParam,
            capacityAssignmentData: CapacityAssignmentRequest,
        }) => capacityAssignmentClient.create(eventId, capacityAssignmentData),
        {
            onSuccess: (_, variables) => queryClient
                .invalidateQueries({queryKey: [GET_EVENT_CAPACITY_ASSIGNMENTS_QUERY_KEY, variables.eventId]}),
        }
    )
}
