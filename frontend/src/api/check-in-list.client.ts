import {api} from "./client";
import {
    CheckInList,
    CheckInListRequest,
    GenericDataResponse,
    GenericPaginatedResponse,
    IdParam, QueryFilters,
} from "../types";
import {queryParamsHelper} from "../utilites/queryParamsHelper.ts";

export const checkInListClient = {
    create: async (eventId: IdParam, checkInList: CheckInListRequest) => {
        const response = await api.post<GenericDataResponse<CheckInList>>(`events/${eventId}/check-in-lists`, checkInList);
        return response.data;
    },
    update: async (eventId: IdParam, checkInListId: IdParam, checkInList: CheckInListRequest) => {
        const response = await api.put<GenericDataResponse<CheckInList>>(`events/${eventId}/check-in-lists/${checkInListId}`, checkInList);
        return response.data;
    },
    all: async (eventId: IdParam, pagination: QueryFilters) => {
        const response = await api.get<GenericPaginatedResponse<CheckInList>>(`events/${eventId}/check-in-lists` + queryParamsHelper.buildQueryString(pagination));
        return response.data;
    },
    get: async (eventId: IdParam, checkInListId: IdParam) => {
        const response = await api.get<GenericDataResponse<CheckInList>>(`events/${eventId}/check-in-lists/${checkInListId}`);
        return response.data;
    },
    delete: async (eventId: IdParam, checkInListId: IdParam) => {
        const response = await api.delete<GenericDataResponse<CheckInList>>(`events/${eventId}/check-in-lists/${checkInListId}`);
        return response.data;
    },
}
