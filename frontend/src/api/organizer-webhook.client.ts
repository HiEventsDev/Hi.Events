import {GenericDataResponse, IdParam, Webhook, WebhookLog} from "../types";
import {api} from "./client";

export interface OrganizerWebhookRequest {
    url: string;
    event_types: string[];
    status: 'ENABLED' | 'PAUSED';
}

export const organizerWebhookClient = {
    get: async (organizerId: IdParam, webhookId: IdParam) => {
        return await api.get<GenericDataResponse<Webhook>>(`organizers/${organizerId}/webhooks/${webhookId}`);
    },

    create: async (organizerId: IdParam, webhook: OrganizerWebhookRequest) => {
        return await api.post<GenericDataResponse<Webhook>>(`organizers/${organizerId}/webhooks`, webhook);
    },

    all: async (organizerId: IdParam) => {
        return await api.get<GenericDataResponse<Webhook[]>>(`organizers/${organizerId}/webhooks`);
    },

    logs: async (organizerId: IdParam, webhookId: IdParam) => {
        return await api.get<GenericDataResponse<WebhookLog[]>>(`organizers/${organizerId}/webhooks/${webhookId}/logs`);
    },

    delete: async (organizerId: IdParam, webhookId: IdParam) => {
        return await api.delete(`organizers/${organizerId}/webhooks/${webhookId}`);
    },

    update: async (organizerId: IdParam, webhookId: IdParam, webhook: OrganizerWebhookRequest) => {
        return await api.put<GenericDataResponse<Webhook>>(`organizers/${organizerId}/webhooks/${webhookId}`, webhook);
    },
}
