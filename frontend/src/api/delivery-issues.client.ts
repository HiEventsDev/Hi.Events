import {api} from "./client";
import {DeliveryIssue, GenericPaginatedResponse, IdParam, QueryFilters} from "../types";
import {queryParamsHelper} from "../utilites/queryParamsHelper.ts";
import {AxiosResponse} from "axios";

export const deliveryIssuesClient = {
    failures: async (eventId: IdParam, pagination: QueryFilters, showResolved = false) => {
        const params = queryParamsHelper.buildQueryString(pagination);
        const separator = params ? '&' : '?';
        const resolvedParam = showResolved ? `${separator}show_resolved=1` : '';
        const response: AxiosResponse<GenericPaginatedResponse<DeliveryIssue>> = await api.get<GenericPaginatedResponse<DeliveryIssue>>(
            `events/${eventId}/delivery-issues${params}${resolvedParam}`,
        );
        return response.data;
    },

    resolve: async (eventId: IdParam, messageId: IdParam, sourceType: 'transaction' | 'announcement') => {
        const response = await api.post(
            `events/${eventId}/delivery-issues/${messageId}/resolve`,
            {source_type: sourceType},
        );
        return response.data;
    },
};
