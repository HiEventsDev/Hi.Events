import {useMutation, useQueryClient} from "@tanstack/react-query";
import {IdParam} from "../types.ts";
import {capacityAssignmentClient} from "../api/capacity-assignment.client.ts";
import {GET_EVENT_CAPACITY_ASSIGNMENTS_QUERY_KEY} from "../queries/useGetCapacityAssignments.ts";

export const useDeleteCapacityAssignment = () => {
    const queryClient = useQueryClient();

    return useMutation(
        ({capacityAssignmentId, eventId}: {
            capacityAssignmentId: IdParam,
            eventId: IdParam,
        }) => capacityAssignmentClient.delete(eventId, capacityAssignmentId),
        {
            onSuccess: () => queryClient.invalidateQueries([GET_EVENT_CAPACITY_ASSIGNMENTS_QUERY_KEY]),
        }
    )
}
