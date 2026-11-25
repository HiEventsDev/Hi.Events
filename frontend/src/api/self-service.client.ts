import {publicApi} from "./public-client.ts";
import {GenericDataResponse, IdParam} from "../types.ts";
import {selfServiceHeaders, SelfServiceLink} from "../utilites/selfServiceSession.ts";

export interface SelfServiceUpdateResult {
    success: boolean;
    short_id_changed: boolean;
    new_short_id?: string;
    message: string;
    warning?: string;
    email_sent?: boolean;
}

export interface EditAttendeeData {
    first_name?: string;
    last_name?: string;
    email?: string;
}

export interface EditOrderData {
    first_name?: string;
    last_name?: string;
    email?: string;
    phone?: string;
}

export interface SelfServiceSession {
    token: string;
    expires_at: string;
    email: string;
}

const verificationPath = (link: SelfServiceLink): string => link.attendeeShortId
    ? `/events/${link.eventId}/attendees/${link.attendeeShortId}/verification`
    : `/events/${link.eventId}/order/${link.orderShortId}/verification`;

export const selfServiceClient = {
    requestVerification: async (link: SelfServiceLink): Promise<{ email: string }> => {
        const response = await publicApi.post(verificationPath(link));
        return response.data;
    },

    confirmVerification: async (link: SelfServiceLink, code: string): Promise<SelfServiceSession> => {
        const response = await publicApi.post<GenericDataResponse<SelfServiceSession>>(
            `${verificationPath(link)}/confirm`,
            {code},
        );
        return response.data.data;
    },

    startSessionFromLookup: async (lookupToken: string): Promise<SelfServiceSession> => {
        const response = await publicApi.post<GenericDataResponse<SelfServiceSession>>(
            `/ticket-lookup/${lookupToken}/session`,
        );
        return response.data.data;
    },

    editAttendee: async (
        eventId: IdParam,
        orderShortId: string,
        attendeeShortId: string,
        data: EditAttendeeData
    ): Promise<SelfServiceUpdateResult> => {
        const response = await publicApi.patch(
            `/events/${eventId}/order/${orderShortId}/attendees/${attendeeShortId}`,
            data,
            {headers: selfServiceHeaders({eventId: eventId as string | number, orderShortId})},
        );
        return response.data;
    },

    editOrder: async (
        eventId: IdParam,
        orderShortId: string,
        data: EditOrderData
    ): Promise<SelfServiceUpdateResult> => {
        const response = await publicApi.patch(
            `/events/${eventId}/order/${orderShortId}`,
            data,
            {headers: selfServiceHeaders({eventId: eventId as string | number, orderShortId})},
        );
        return response.data;
    },

    resendAttendeeTicket: async (
        eventId: IdParam,
        orderShortId: string,
        attendeeShortId: string
    ): Promise<{ success: boolean; message: string }> => {
        const response = await publicApi.post(
            `/events/${eventId}/order/${orderShortId}/attendees/${attendeeShortId}/resend-ticket`
        );
        return response.data;
    },

    resendOrderConfirmation: async (
        eventId: IdParam,
        orderShortId: string
    ): Promise<{ success: boolean; message: string }> => {
        const response = await publicApi.post(
            `/events/${eventId}/order/${orderShortId}/resend-confirmation`
        );
        return response.data;
    },
};
