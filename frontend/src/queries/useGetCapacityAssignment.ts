import {useQuery} from "@tanstack/react-query";
import {IdParam} from "../types.ts";
import {capacityAssignmentClient} from "../api/capacity-assignment.client.ts";

export const GET_EVENT_CAPACITY_ASSIGNMENT_QUERY_KEY = 'getEventCapacityAssignment';

export const useGetEventCapacityAssignment = (capacityAssignmentId: IdParam, eventId: IdParam) => {
    return useQuery({
        queryKey: [GET_EVENT_CAPACITY_ASSIGNMENT_QUERY_KEY, eventId, capacityAssignmentId],

        queryFn: async () => {
            const {data} = await capacityAssignmentClient.get(eventId, capacityAssignmentId);
            return data;
        }
    });
};
