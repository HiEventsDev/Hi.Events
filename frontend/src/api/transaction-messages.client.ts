import {api} from "./client";
import {GenericDataResponse, GenericPaginatedResponse, IdParam, OutgoingTransactionMessage, QueryFilters} from "../types";
import {queryParamsHelper} from "../utilites/queryParamsHelper.ts";
import {AxiosResponse} from "axios";

export const transactionMessagesClient = {
    all: async (eventId: IdParam, pagination: QueryFilters) => {
        const response: AxiosResponse<GenericPaginatedResponse<OutgoingTransactionMessage>> = await api.get<GenericPaginatedResponse<OutgoingTransactionMessage>>(
            `events/${eventId}/transaction-messages` + queryParamsHelper.buildQueryString(pagination),
        );
        return response.data;
    },

    resend: async (eventId: IdParam, messageId: IdParam, email?: string) => {
        const response: AxiosResponse<GenericDataResponse<OutgoingTransactionMessage>> = await api.post(
            `events/${eventId}/transaction-messages/${messageId}/resend`,
            email ? {email} : {},
        );
        return response.data;
    },
};
