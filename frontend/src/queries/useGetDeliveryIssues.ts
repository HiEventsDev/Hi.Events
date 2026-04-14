import {useQuery} from "@tanstack/react-query";
import {DeliveryIssue, GenericPaginatedResponse, IdParam, QueryFilters} from "../types.ts";
import {deliveryIssuesClient} from "../api/delivery-issues.client.ts";

export const GET_DELIVERY_ISSUES_QUERY_KEY = 'getDeliveryIssues';

export const useGetDeliveryIssues = (eventId: IdParam, pagination: QueryFilters, showResolved = false) => {
    return useQuery<GenericPaginatedResponse<DeliveryIssue>>({
        queryKey: [GET_DELIVERY_ISSUES_QUERY_KEY, eventId, pagination, showResolved],
        queryFn: async () => await deliveryIssuesClient.failures(eventId, pagination, showResolved),
        enabled: !!eventId,
    });
};
