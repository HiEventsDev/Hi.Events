import {useQuery} from "@tanstack/react-query";
import {IdParam, QueryFilters} from "../types.ts";
import {capacityAssignmentClient} from "../api/capacity-assignment.client.ts";

export const GET_EVENT_CAPACITY_ASSIGNMENTS_QUERY_KEY = 'getEventCapacityAssignments';

export const useGetEventCapacityAssignments = (eventId: IdParam, pagination: QueryFilters) => {
    return useQuery(
        [GET_EVENT_CAPACITY_ASSIGNMENTS_QUERY_KEY, eventId, pagination],
        async () => {
            return await capacityAssignmentClient.all(eventId, pagination);
        }
    )
};
