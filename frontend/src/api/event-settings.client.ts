import {api} from "./client";
import {Event, EventSettings, GenericDataResponse, IdParam,} from "../types";

export interface PlatformFeePreview {
    event_currency: string;
    fee_currency: string | null;
    fixed_fee_original: number;
    fixed_fee_converted: number;
    percentage_fee: number;
    sample_price: number;
    platform_fee: number;
    total: number;
}

export const eventsSettingsClient = {
    partialUpdate: async (eventId: IdParam, event: Partial<EventSettings>) => {
        const response = await api.patch<GenericDataResponse<Event>>('events/' + eventId + '/settings', event);
        return response.data;
    },

    all: async (eventId: IdParam) => {
        const response = await api.get<GenericDataResponse<EventSettings>>('events/' + eventId + '/settings');
        return response.data;
    },

    getPlatformFeePreview: async (eventId: IdParam, price: number) => {
        const response = await api.get<GenericDataResponse<PlatformFeePreview>>('events/' + eventId + '/settings/platform-fee-preview', {
            params: { price }
        });
        return response.data.data;
    },
}
