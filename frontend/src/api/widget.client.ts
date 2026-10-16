import {api} from "./client";
import {publicApi} from "./public-client.ts";
import {GenericDataResponse, IdParam} from "../types";
import {WidgetSettings, WidgetType} from "../utilites/widgetConfig.ts";

export interface WidgetStats {
    views: number;
    orders: number;
    revenue?: Record<string, number>;
}

export interface Widget {
    id: number;
    short_id: string;
    name: string;
    type: WidgetType;
    event_id: number | null;
    organizer_id: number;
    settings: WidgetSettings;
    stats: WidgetStats;
    created_at: string;
    updated_at: string;
}

export interface PublicWidget {
    short_id: string;
    type: WidgetType;
    event_id: number | null;
    organizer_id: number;
    settings: WidgetSettings;
}

export interface UpsertWidgetRequest {
    name: string;
    type: WidgetType;
    settings: WidgetSettings;
}

export type WidgetOwner = {eventId: IdParam} | {organizerId: IdParam};

const basePath = (owner: WidgetOwner) => 'eventId' in owner
    ? `events/${owner.eventId}/widgets`
    : `organizers/${owner.organizerId}/widgets`;

export const widgetClient = {
    all: async (owner: WidgetOwner) => {
        const response = await api.get<GenericDataResponse<Widget[]>>(basePath(owner));
        return response.data;
    },
    create: async (owner: WidgetOwner, widget: UpsertWidgetRequest) => {
        const response = await api.post<GenericDataResponse<Widget>>(basePath(owner), widget);
        return response.data;
    },
    update: async (owner: WidgetOwner, widgetId: IdParam, widget: UpsertWidgetRequest) => {
        const response = await api.put<GenericDataResponse<Widget>>(`${basePath(owner)}/${widgetId}`, widget);
        return response.data;
    },
    delete: async (owner: WidgetOwner, widgetId: IdParam) => {
        await api.delete(`${basePath(owner)}/${widgetId}`);
    },
};

export const widgetClientPublic = {
    get: async (shortId: string) => {
        const response = await publicApi.get<GenericDataResponse<PublicWidget>>(`widgets/${encodeURIComponent(shortId)}`);
        return response.data;
    },
};
