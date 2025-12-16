import {publicApi} from "./public-client.ts";
import {IdParam} from "../types.ts";

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
    resend_email?: boolean;
}

export interface EditOrderData {
    first_name?: string;
    last_name?: string;
    email?: string;
    resend_email?: boolean;
}

export const selfServiceClient = {
    editAttendee: async (
        eventId: IdParam,
        orderShortId: string,
        attendeeShortId: string,
        data: EditAttendeeData
    ): Promise<SelfServiceUpdateResult> => {
        const response = await publicApi.patch(
            `/events/${eventId}/order/${orderShortId}/attendees/${attendeeShortId}`,
            data
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
            data
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
