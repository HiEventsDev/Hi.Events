import {api} from "./client";
import {GenericPaginatedResponse, IdParam, Message, OutgoingMessage, QueryFilters,} from "../types";
import {queryParamsHelper} from "../utilites/queryParamsHelper.ts";
import {AxiosResponse} from "axios";

export interface MessagePreviewRequest {
    subject: string;
    message: string;
}

export interface MessagePreview {
    html: string;
    subject: string;
    from_name: string;
    reply_to: string | null;
}

export const messagesClient = {
    preview: async (eventId: IdParam, draft: MessagePreviewRequest) => {
        const response = await api.post<{ data: MessagePreview }>(`events/${eventId}/messages/preview`, draft);
        return response.data;
    },
    send: async (eventId: IdParam, messagesRequest: Message) => {
        return await api.post(`events/${eventId}/messages`, messagesRequest);
    },
    all: async (eventId: IdParam, pagination: QueryFilters) => {
        const response: AxiosResponse<GenericPaginatedResponse<Message>> = await api.get<GenericPaginatedResponse<Message>>(
            `events/${eventId}/messages` + queryParamsHelper.buildQueryString(pagination),
        );
        return response.data;
    },
    cancel: async (eventId: IdParam, messageId: IdParam) => {
        return await api.post(`events/${eventId}/messages/${messageId}/cancel`);
    },
    recipients: async (eventId: IdParam, messageId: IdParam, pagination: QueryFilters) => {
        const response: AxiosResponse<GenericPaginatedResponse<OutgoingMessage>> = await api.get<GenericPaginatedResponse<OutgoingMessage>>(
            `events/${eventId}/messages/${messageId}/recipients` + queryParamsHelper.buildQueryString(pagination),
        );
        return response.data;
    },
}
