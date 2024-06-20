import {api} from "./client";
import {Attendee, GenericDataResponse, GenericPaginatedResponse, IdParam, QueryFilters, TaxAndFee} from "../types";
import {queryParamsHelper} from "../utilites/queryParamsHelper.ts";
import {publicApi} from "./public-client.ts";
import {SupportedLocales} from "../locales.ts";

export interface EditAttendeeRequest {
    first_name: string;
    last_name: string;
    email: string;
    ticket_id?: IdParam;
    ticket_price_id?: IdParam;
    status?: string;
}

export interface CreateAttendeeRequest extends EditAttendeeRequest {
    amount_paid: number,
    send_confirmation_email: boolean,
    taxes_and_fees: TaxAndFee[],
    locale: SupportedLocales,
}

export const attendeesClient = {
    create: async (eventId: IdParam, attendee: CreateAttendeeRequest) => {
        const response = await api.post<GenericDataResponse<Attendee>>(
            `events/${eventId}/attendees`, attendee
        );
        return response.data;
    },
    update: async (eventId: IdParam, attendeeId: IdParam, attendee: EditAttendeeRequest) => {
        const response = await api.put<GenericDataResponse<Attendee>>(
            `events/${eventId}/attendees/${attendeeId}`, attendee
        );
        return response.data;
    },
    modify: async (eventId: IdParam, attendeeId: IdParam, attendee: Partial<EditAttendeeRequest>) => {
        const response = await api.patch<GenericDataResponse<Attendee>>(
            `events/${eventId}/attendees/${attendeeId}`, attendee
        );
        return response.data;
    },
    all: async (eventId: IdParam, queryFilters: QueryFilters) => {
        const response = await api.get<GenericPaginatedResponse<Attendee>>(
            `/events/${eventId}/attendees` + queryParamsHelper.buildQueryString(queryFilters)
        );
        return response.data;
    },
    findById: async (eventId: IdParam, attendeeId: IdParam) => {
        const response = await api.get<GenericDataResponse<Attendee>>(`events/${eventId}/attendees/${attendeeId}`);
        return response.data;
    },
    checkIn: async (eventId: IdParam, attendeePublicId: string, action: 'check_in' | 'check_out') => {
        const response = await api.post<GenericDataResponse<Attendee>>(`events/${eventId}/attendees/${attendeePublicId}/check_in`, {
            action: action,
        });
        return response.data;
    },
    export: async (eventId: IdParam): Promise<Blob> => {
        const response = await api.post(`events/${eventId}/attendees/export`, {}, {
            responseType: 'blob',
        });

        return new Blob([response.data]);
    },
    resendTicket: async (eventId: IdParam, attendeeId: IdParam) => {
        return await api.post(`events/${eventId}/attendees/${attendeeId}/resend-ticket`);
    },
}

export const attendeeClientPublic = {
    findByShortId: async (eventId: IdParam, attendeeShortId: string) => {
        const response = await publicApi.get<GenericDataResponse<Partial<Attendee>>>(`events/${eventId}/attendees/${attendeeShortId}`);
        return response.data;
    },
}