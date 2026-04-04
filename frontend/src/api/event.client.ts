import {api} from "./client";
import {
    CheckInStats,
    Event,
    EventDuplicatePayload,
    EventStats,
    GenericDataResponse,
    GenericPaginatedResponse,
    IdParam,
    Image,
    ImageType,
    QueryFilters,
} from "../types";
import {publicApi} from "./public-client.ts";
import {queryParamsHelper} from "../utilites/queryParamsHelper.ts";

export const eventsClient = {
    create: async (event: Partial<Event>) => {
        const response = await api.post<GenericDataResponse<Event>>('events', event);
        return response.data;
    },

    all: async (pagination: QueryFilters) => {
        const response = await api.get<GenericPaginatedResponse<Event>>(
            'events' + queryParamsHelper.buildQueryString(pagination)
        );
        return response.data;
    },

    update: async (eventId: IdParam, event: Partial<Event>) => {
        const response = await api.put<GenericDataResponse<Event>>('events/' + eventId, event);
        return response.data;
    },

    findByID: async (eventId: IdParam) => {
        const response = await api.get<GenericDataResponse<Event>>('events/' + eventId);
        return response.data;
    },

    getEventStats: async (eventId: IdParam, occurrenceId?: IdParam) => {
        const params = occurrenceId ? `?occurrence_id=${occurrenceId}` : '';
        const response = await api.get<GenericDataResponse<EventStats>>(`events/${eventId}/stats${params}`);
        return response.data;
    },

    getEventCheckInStats: async (eventId: IdParam) => {
        const response = await api.get<GenericDataResponse<CheckInStats>>('events/' + eventId + '/check_in_stats');
        return response.data;
    },

    getEventImages: async (eventId: IdParam) => {
        const response = await api.get<GenericDataResponse<Image[]>>('events/' + eventId + '/images');
        return response.data;
    },

    uploadEventImage: async (eventId: IdParam, image: File, type: ImageType = 'EVENT_COVER') => {
        const formData = new FormData();
        formData.append('image', image);
        formData.append('type', type);
        const response = await api.post<GenericDataResponse<Image>>('events/' + eventId + '/images', formData, {
            headers: {
                'Content-Type': 'multipart/form-data'
            }
        });
        return response.data;
    },

    deleteEventImage: async (eventId: IdParam, imageId: IdParam) => {
        const response = await api.delete<GenericDataResponse<Image>>('events/' + eventId + '/images/' + imageId);
        return response.data;
    },

    delete: async (eventId: IdParam) => {
        const response = await api.delete('events/' + eventId);
        return response.data;
    },

    getDeletionStatus: async (eventId: IdParam) => {
        const response = await api.get<GenericDataResponse<{ can_delete: boolean; reason?: string }>>('events/' + eventId + '/deletion-status');
        return response.data;
    },

    duplicate: async (eventId: IdParam, event: EventDuplicatePayload) => {
        const response = await api.post<GenericDataResponse<Event>>('events/' + eventId + '/duplicate', event);
        return response.data;
    },

    updateEventStatus: async (eventId: IdParam, status: string) => {
        const response = await api.put<GenericDataResponse<Event>>('events/' + eventId + '/status', {
            status
        });
        return response.data;
    },

    getEventReport: async (eventId: IdParam, reportType: IdParam, startDate?: string, endDate?: string, occurrenceId?: IdParam) => {
        const params = new URLSearchParams();
        if (startDate) params.append('start_date', startDate);
        if (endDate) params.append('end_date', endDate);
        if (occurrenceId) params.append('occurrence_id', String(occurrenceId));
        const response = await api.get<GenericDataResponse<any>>('events/' + eventId + '/reports/' + reportType + '?' + params.toString());
        return response.data;
    }
}

export const eventsClientPublic = {
    all: async () => {
        const response = await publicApi.get<GenericPaginatedResponse<Event>>('events');
        return response.data;
    },

    findByID: async (eventId: any, promoCode?: null | string, eventOccurrenceId?: number | null) => {
        const params = new URLSearchParams();
        if (promoCode) params.set('promo_code', promoCode);
        if (eventOccurrenceId) params.set('event_occurrence_id', String(eventOccurrenceId));
        const queryString = params.toString();
        const response = await publicApi.get<GenericDataResponse<Event>>('events/' + eventId + (queryString ? '?' + queryString : ''));
        return response.data;
    },
}
