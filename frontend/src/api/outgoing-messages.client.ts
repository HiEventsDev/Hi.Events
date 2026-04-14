import {api} from "./client";
import {GenericDataResponse, GenericPaginatedResponse, IdParam, OutgoingMessage, QueryFilters} from "../types";
import {queryParamsHelper} from "../utilites/queryParamsHelper.ts";
import {AxiosResponse} from "axios";

export const outgoingMessagesClient = {
    all: async (eventId: IdParam, pagination: QueryFilters) => {
        const response: AxiosResponse<GenericPaginatedResponse<OutgoingMessage>> = await api.get<GenericPaginatedResponse<OutgoingMessage>>(
            `events/${eventId}/outgoing-messages` + queryParamsHelper.buildQueryString(pagination),
        );
        return response.data;
    },

    resend: async (eventId: IdParam, messageId: IdParam, email?: string) => {
        const response: AxiosResponse<GenericDataResponse<OutgoingMessage>> = await api.post(
            `events/${eventId}/outgoing-messages/${messageId}/resend`,
            email ? {email} : {},
        );
        return response.data;
    },
};
