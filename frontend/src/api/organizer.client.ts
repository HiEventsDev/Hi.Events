import {api} from "./client";
import {GenericDataResponse, GenericPaginatedResponse, IdParam, Organizer,} from "../types";

export const organizerClient = {
    create: async (organizer: Partial<Organizer>) => {
        const response = await api.post<GenericDataResponse<Organizer>>('organizers', organizer);
        return response.data;
    },

    all: async () => {
        const response = await api.get<GenericPaginatedResponse<Organizer>>('organizers');
        return response.data;
    },

    update: async (organizer: Organizer) => {
        const response = await api.post<GenericDataResponse<Organizer>>('organizers/' + organizer.id, organizer);
        return response.data;
    },

    findByID: async (organizerId: IdParam) => {
        const response = await api.get<GenericDataResponse<Organizer>>('organizers/' + organizerId);
        return response.data;
    },
}