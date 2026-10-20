import {api} from "./client";
import {
    GenericDataResponse,
    GenericPaginatedResponse,
    GeoPlace,
    GeoSuggestion,
    IdParam,
    Location,
    QueryFilters,
} from "../types";
import {queryParamsHelper} from "../utilites/queryParamsHelper.ts";

export type UpsertLocationPayload = Partial<Location> & {
    structured_address: Location["structured_address"];
};

export const locationClient = {
    all: async (organizerId: IdParam, pagination?: QueryFilters) => {
        const response = await api.get<GenericPaginatedResponse<Location>>(
            "organizers/" + organizerId + "/locations" + queryParamsHelper.buildQueryString(pagination ?? {}),
        );
        return response.data;
    },

    create: async (organizerId: IdParam, payload: UpsertLocationPayload) => {
        const response = await api.post<GenericDataResponse<Location>>(
            "organizers/" + organizerId + "/locations",
            payload,
        );
        return response.data;
    },

    update: async (organizerId: IdParam, locationId: IdParam, payload: UpsertLocationPayload) => {
        const response = await api.put<GenericDataResponse<Location>>(
            "organizers/" + organizerId + "/locations/" + locationId,
            payload,
        );
        return response.data;
    },

    delete: async (organizerId: IdParam, locationId: IdParam) => {
        const response = await api.delete(
            "organizers/" + organizerId + "/locations/" + locationId,
        );
        return response.data;
    },

    geoStatus: async () => {
        const response = await api.get<GenericDataResponse<{available: boolean}>>("geo/status");
        return response.data;
    },

    autocomplete: async (organizerId: IdParam, query: string, opts: {locale?: string; country?: string} = {}) => {
        const params = new URLSearchParams();
        params.set("query", query);
        if (opts.locale) params.set("locale", opts.locale);
        if (opts.country) params.set("country", opts.country);
        const response = await api.get<{data: GeoSuggestion[]}>(
            "organizers/" + organizerId + "/locations/autocomplete?" + params.toString(),
        );
        return response.data;
    },

    placeDetails: async (organizerId: IdParam, placeId: string, opts: {locale?: string} = {}) => {
        const params = new URLSearchParams();
        if (opts.locale) params.set("locale", opts.locale);
        const qs = params.toString();
        const response = await api.get<{data: GeoPlace}>(
            "organizers/" + organizerId + "/locations/places/" + encodeURIComponent(placeId) + (qs ? "?" + qs : ""),
        );
        return response.data;
    },
};
