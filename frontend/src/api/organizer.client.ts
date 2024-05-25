import {api} from "./client";
import {Event, GenericDataResponse, GenericPaginatedResponse, IdParam, Organizer, QueryFilters,} from "../types";
import {queryParamsHelper} from "../utilites/queryParamsHelper.ts";

export const organizerClient = {
    create: async (organizer: Partial<Organizer>) => {
        const response = await api.post<GenericDataResponse<Organizer>>('organizers', organizer);
        return response.data;
    },

    all: async () => {
        const response = await api.get<GenericPaginatedResponse<Organizer>>('organizers');
        return response.data;
    },

    update: async (organizerId: IdParam, organizer: Partial<Organizer>) => {
        const response = await api.post<GenericDataResponse<Organizer>>('organizers/' + organizerId, organizer);
        return response.data;
    },

    findByID: async (organizerId: IdParam) => {
        const response = await api.get<GenericDataResponse<Organizer>>('organizers/' + organizerId);
        return response.data;
    },

    findEventsByOrganizerId: async (organizerId: IdParam, pagination: QueryFilters) => {
        const response = await api.get<GenericPaginatedResponse<Event>>(
            'organizers/' + organizerId + '/events' + queryParamsHelper.buildQueryString(pagination)
        );
        return response.data;
    },
}