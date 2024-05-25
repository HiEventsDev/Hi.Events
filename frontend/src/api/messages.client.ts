import {api} from "./client";
import {GenericPaginatedResponse, IdParam, Message, QueryFilters,} from "../types";
import {queryParamsHelper} from "../utilites/queryParamsHelper.ts";
import {AxiosResponse} from "axios";

export const messagesClient = {
    send: async (eventId: IdParam, messagesRequest: Message) => {
        return await api.post(`events/${eventId}/messages`, messagesRequest);
    },
    all: async (eventId: IdParam, pagination: QueryFilters) => {
        const response: AxiosResponse<GenericPaginatedResponse<Message>> = await api.get<GenericPaginatedResponse<Message>>(
            `events/${eventId}/messages` + queryParamsHelper.buildQueryString(pagination),
        );
        return response.data;
    },
}
