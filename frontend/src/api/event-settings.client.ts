import {api} from "./client";
import {Event, EventSettings, GenericDataResponse, IdParam,} from "../types";

export const eventsSettingsClient = {
    partialUpdate: async (eventId: IdParam, event: Partial<EventSettings>) => {
        const response = await api.patch<GenericDataResponse<Event>>('events/' + eventId + '/settings', event);
        return response.data;
    },

    all: async (eventId: IdParam) => {
        const response = await api.get<GenericDataResponse<EventSettings>>('events/' + eventId + '/settings');
        return response.data;
    },
}
