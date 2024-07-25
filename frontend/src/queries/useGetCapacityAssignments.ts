import {useQuery} from "@tanstack/react-query";
import {IdParam} from "../types.ts";
import {capacityAssignmentClient} from "../api/capacity-assignment.client.ts";

export const GET_EVENT_CAPACITY_ASSIGNMENTS_QUERY_KEY = 'getEventCapacityAssignments';

export const useGetEventCapacityAssignments = (eventId: IdParam) => {
    return useQuery(
        [GET_EVENT_CAPACITY_ASSIGNMENTS_QUERY_KEY, eventId],
        async () => {
            const {data} = await capacityAssignmentClient.all(eventId);
            return data;
        }
    )
};
