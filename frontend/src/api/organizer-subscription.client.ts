import {api} from "./client";
import {publicApi} from "./public-client";
import {GenericPaginatedResponse, IdParam} from "../types";

export interface OrganizerSubscriber {
    id: number;
    email: string;
    status: 'confirmed' | 'pending' | 'unsubscribed';
    source: 'public' | 'checkout';
    subscribed_at: string;
    confirmed_at: string | null;
    unsubscribed_at: string | null;
}

export type SubscriberFilters = {query: string; status: string; page: number};
export type SubscriberResponse = GenericPaginatedResponse<OrganizerSubscriber> & {
    meta: {counts: Record<OrganizerSubscriber['status'], number>};
};

const path = (id: IdParam) => `organizers/${id}/event-subscriptions`;

export const organizerSubscriptionClient = {
    subscribe: (id: IdParam, email: string, locale: string) => publicApi.post(`${path(id)}`, {email, locale}),
    confirm: (id: IdParam, token: string) => publicApi.post(`${path(id)}/confirm/${token}`),
    unsubscribe: (id: IdParam, token: string) => publicApi.post(`${path(id)}/unsubscribe/${token}`),
    list: async (id: IdParam, filters: SubscriberFilters) => {
        const {data} = await api.get<SubscriberResponse>(path(id), {params: {...filters, status: filters.status || undefined}});
        return data;
    },
    export: async (id: IdParam, filters: SubscriberFilters) => {
        const {data} = await api.get<Blob>(`${path(id)}/export`, {
            params: {query: filters.query, status: filters.status || undefined}, responseType: 'blob',
        });
        return data;
    },
    unsubscribeMany: (id: IdParam, ids: number[]) => api.post(`${path(id)}/unsubscribe`, {ids}),
};
