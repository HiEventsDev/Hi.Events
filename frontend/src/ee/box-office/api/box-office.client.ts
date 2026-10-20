import {api} from "../../../api/client";
import {
    BoxOffice,
    BoxOfficeRequest,
    BoxOfficeStats,
    BoxOfficeStatsRange,
    BoxOfficeWithPin,
    GenericDataResponse,
    GenericPaginatedResponse,
    IdParam,
    QueryFilters,
} from "../../../types";
import {queryParamsHelper} from "../../../utilites/queryParamsHelper.ts";

export const boxOfficeClient = {
    create: async (eventId: IdParam, boxOffice: BoxOfficeRequest) => {
        const response = await api.post<GenericDataResponse<BoxOfficeWithPin>>(`events/${eventId}/box-offices`, boxOffice);
        return response.data;
    },
    update: async (eventId: IdParam, boxOfficeId: IdParam, boxOffice: BoxOfficeRequest) => {
        const response = await api.put<GenericDataResponse<BoxOffice>>(`events/${eventId}/box-offices/${boxOfficeId}`, boxOffice);
        return response.data;
    },
    all: async (eventId: IdParam, pagination: QueryFilters | null = null) => {
        const paginationQuery = (pagination) ? queryParamsHelper.buildQueryString(pagination as QueryFilters) : '';
        const response = await api.get<GenericPaginatedResponse<BoxOffice>>(`events/${eventId}/box-offices` + paginationQuery);
        return response.data;
    },
    get: async (eventId: IdParam, boxOfficeId: IdParam) => {
        const response = await api.get<GenericDataResponse<BoxOffice>>(`events/${eventId}/box-offices/${boxOfficeId}`);
        return response.data;
    },
    resetPin: async (eventId: IdParam, boxOfficeId: IdParam) => {
        const response = await api.post<GenericDataResponse<BoxOfficeWithPin>>(`events/${eventId}/box-offices/${boxOfficeId}/reset-pin`);
        return response.data;
    },
    stats: async (eventId: IdParam, boxOfficeId: IdParam, range: BoxOfficeStatsRange) => {
        const response = await api.get<GenericDataResponse<BoxOfficeStats>>(`events/${eventId}/box-offices/${boxOfficeId}/stats`, {
            params: {from: range.from ?? undefined, to: range.to ?? undefined},
        });
        return response.data;
    },
    delete: async (eventId: IdParam, boxOfficeId: IdParam) => {
        const response = await api.delete<GenericDataResponse<BoxOffice>>(`events/${eventId}/box-offices/${boxOfficeId}`);
        return response.data;
    },
}
