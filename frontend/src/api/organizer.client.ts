import {api} from "./client";
import {
    Event,
    GenericDataResponse,
    GenericPaginatedResponse,
    IdParam,
    Order,
    Organizer,
    OrganizerSettings,
    OrganizerStats,
    QueryFilters,
} from "../types";
import {queryParamsHelper} from "../utilites/queryParamsHelper.ts";
import {publicApi} from "./public-client.ts";

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

    updateStatus: async (organizerId: IdParam, status: string) => {
        const response = await api.put<GenericDataResponse<Organizer>>('organizers/' + organizerId + '/status', {
            status
        });
        return response.data;
    },

    findEventsByOrganizerId: async (organizerId: IdParam, pagination: QueryFilters) => {
        const response = await api.get<GenericPaginatedResponse<Event>>(
            'organizers/' + organizerId + '/events' + queryParamsHelper.buildQueryString(pagination)
        );
        return response.data;
    },

    getOrganizerStats: async (organizerId: IdParam, currencyCode: string) => {
        const response = await api.get<GenericDataResponse<OrganizerStats>>('organizers/' + organizerId + '/stats?currency_code=' + currencyCode);
        return response.data;
    },

    getOrganizerOrders: async (organizerId: IdParam, pagination: QueryFilters) => {
        const response = await api.get<GenericPaginatedResponse<Order>>(
            `organizers/${organizerId}/orders` + queryParamsHelper.buildQueryString(pagination),
        );
        return response.data;
    },
}

export const organizerPublicClient = {
    findByID: async (organizerId: IdParam) => {
        const response = await publicApi.get<GenericDataResponse<Organizer>>('organizers/' + organizerId);
        return response.data;
    },

    findEventsByOrganizerId: async (organizerId: IdParam, pagination: QueryFilters) => {
        const response = await publicApi.get<GenericPaginatedResponse<Event>>(
            'organizers/' + organizerId + '/events' + queryParamsHelper.buildQueryString(pagination)
        );
        return response.data;
    },

    getEvents: async (organizerId: IdParam, pagination: QueryFilters) => {
        const response = await publicApi.get<GenericPaginatedResponse<Event>>(
            'organizers/' + organizerId + '/events' + queryParamsHelper.buildQueryString(pagination)
        );
        return response.data;
    },

    contactOrganizer: async (organizerId: IdParam, contactData: {
        name: string;
        email: string;
        message: string;
    }) => {
        const response = await publicApi.post<GenericDataResponse<any>>(
            `organizers/${organizerId}/contact`, 
            contactData
        );
        return response.data;
    },
}

export const organizerSettingsClient = {
    partialUpdate: async (organizerId: IdParam, settings: Partial<OrganizerSettings>) => {
        const response = await api.patch<GenericDataResponse<OrganizerSettings>>('organizers/' + organizerId + '/settings', settings);
        return response.data;
    },

    all: async (organizerId: IdParam) => {
        const response = await api.get<GenericDataResponse<OrganizerSettings>>('organizers/' + organizerId + '/settings');
        return response.data;
    },
}
