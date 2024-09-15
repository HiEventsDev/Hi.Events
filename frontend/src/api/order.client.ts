import {publicApi} from "./public-client.ts";
import {
    GenericDataResponse,
    GenericPaginatedResponse,
    IdParam,
    Order,
    QueryFilters,
    StripePaymentIntent
} from "../types.ts";
import {api} from "./client.ts";
import {queryParamsHelper} from "../utilites/queryParamsHelper.ts";

export interface OrderDetails {
    first_name: string,
    last_name: string,
    email: string,
}

export interface AttendeeDetails extends OrderDetails {
    ticket_id: number,
}

export interface FinaliseOrderPayload {
    order: OrderDetails,
    attendees: AttendeeDetails[],
}


export interface TicketPriceQuantityFormValue {
    price?: number,
    quantity: number,
    price_id: number,
}

export interface TicketFormValue {
    ticket_id: number,
    quantities: TicketPriceQuantityFormValue[],
}

export interface TicketFormPayload {
    tickets?: TicketFormValue[],
    promo_code: string | null,
    session_identifier?: string,
}


export interface RefundOrderPayload {
    amount: number;
    notify_buyer: boolean;
    cancel_order: boolean;
}

export const orderClient = {
    all: async (eventId: IdParam, pagination: QueryFilters) => {
        const response = await api.get<GenericPaginatedResponse<Order>>(
            `events/${eventId}/orders` + queryParamsHelper.buildQueryString(pagination),
        );
        return response.data;
    },

    findByID: async (eventId: IdParam, orderId: IdParam) => {
        const response = await api.get<GenericDataResponse<Order>>(`events/${eventId}/orders/${orderId}`);
        return response.data;
    },

    refund: async (eventId: IdParam, orderId: IdParam, refundPayload: RefundOrderPayload) => {
        const response = await api.post<GenericDataResponse<Order>>('events/' + eventId + '/orders/' + orderId + '/refund', refundPayload);
        return response.data;
    },

    resendConfirmation: async (eventId: IdParam, orderId: IdParam) => {
        const response = await api.post<GenericDataResponse<Order>>('events/' + eventId + '/orders/' + orderId + '/resend_confirmation');
        return response.data;
    },

    cancel: async (eventId: IdParam, orderId: IdParam) => {
        const response = await api.post<GenericDataResponse<Order>>('events/' + eventId + '/orders/' + orderId + '/cancel');
        return response.data;
    },

    exportOrders: async (eventId: IdParam): Promise<Blob> => {
        const response = await api.post(`events/${eventId}/orders/export`, {}, {
            responseType: 'blob',
        });

        return new Blob([response.data]);
    },
}

export const orderClientPublic = {
    create: async (eventId: number, createOrderPayload: TicketFormPayload) => {
        const response = await publicApi.post<GenericDataResponse<Order>>('events/' + eventId + '/order', createOrderPayload);
        return response.data;
    },

    findByShortId: async (eventId: number, orderShortId: string, includes: string[] = []) => {
        const response = await publicApi.get<GenericDataResponse<Order>>(`events/${eventId}/order/${orderShortId}?include=${includes.join(',')}`);
        return response.data;
    },

    findOrderStripePaymentIntent: async (eventId: number, orderShortId: string) => {
        return await publicApi.get<StripePaymentIntent>(`events/${eventId}/order/${orderShortId}/stripe/payment_intent`);
    },

    createStripePaymentIntent: async (eventId: number, orderShortId: string, sessionIdentifier: string) => {
        const response = await publicApi.post<{
            client_secret: string,
            account_id?: string,
        }>(`events/${eventId}/order/${orderShortId}/stripe/payment_intent?session_identifier=${sessionIdentifier}`);
        return response.data;
    },

    finaliseOrder: async (
        eventId: number,
        orderShortId: string,
        payload: FinaliseOrderPayload
    ) => {
        const response = await publicApi.put<GenericDataResponse<Order>>(`events/${eventId}/order/${orderShortId}`, payload);
        return response.data;
    },
}
