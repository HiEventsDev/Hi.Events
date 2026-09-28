import {publicApi} from "./public-client";
import {
    CashlessQuote,
    CashlessSalesPointPublic,
    CashlessStaffPaymentMethod,
    CashlessTransaction,
    CashlessWalletPublic,
    GenericDataResponse,
    IdParam,
    Order,
} from "../types";

export interface CashlessPurchaseItem {
    product_id: number;
    product_price_id: number;
    quantity: number;
}

const sessionHeaders = (sessionToken?: string | null) =>
    sessionToken ? {headers: {'X-Cashless-Session': sessionToken}} : undefined;

export const publicCashlessClient = {
    getWallet: async (eventId: IdParam, ticketReference: IdParam) => {
        const response = await publicApi.get<GenericDataResponse<CashlessWalletPublic>>(
            `/events/${eventId}/cashless/${ticketReference}`,
        );
        return response.data;
    },
    createTopup: async (eventId: IdParam, ticketReference: IdParam, amount: number) => {
        const response = await publicApi.post<GenericDataResponse<Order>>(
            `/events/${eventId}/cashless/${ticketReference}/topup`, {amount},
        );
        return response.data;
    },

    getSalesPoint: async (salesPointShortId: IdParam, sessionToken?: string | null) => {
        const response = await publicApi.get<GenericDataResponse<CashlessSalesPointPublic>>(
            `/cashless/sales-points/${salesPointShortId}`, sessionHeaders(sessionToken),
        );
        return response.data;
    },
    createSession: async (salesPointShortId: IdParam, pin?: string) => {
        const response = await publicApi.post<{ token: string }>(
            `/cashless/sales-points/${salesPointShortId}/session`, {pin},
        );
        return response.data;
    },
    getWalletAtSalesPoint: async (salesPointShortId: IdParam, attendeePublicId: IdParam, sessionToken?: string | null) => {
        const response = await publicApi.get<GenericDataResponse<CashlessWalletPublic>>(
            `/cashless/sales-points/${salesPointShortId}/wallets/${attendeePublicId}`, sessionHeaders(sessionToken),
        );
        return response.data;
    },
    getQuote: async (
        salesPointShortId: IdParam,
        payload: { items?: CashlessPurchaseItem[]; topup_amount?: number },
        sessionToken?: string | null,
    ) => {
        const response = await publicApi.post<GenericDataResponse<CashlessQuote>>(
            `/cashless/sales-points/${salesPointShortId}/quote`, payload, sessionHeaders(sessionToken),
        );
        return response.data;
    },
    createPurchase: async (
        salesPointShortId: IdParam,
        payload: { attendee_public_id: string; client_reference_id: string; items: CashlessPurchaseItem[] },
        sessionToken?: string | null,
    ) => {
        const response = await publicApi.post<GenericDataResponse<CashlessTransaction>>(
            `/cashless/sales-points/${salesPointShortId}/purchases`, payload, sessionHeaders(sessionToken),
        );
        return response.data;
    },
    createStaffTopup: async (
        salesPointShortId: IdParam,
        payload: {
            attendee_public_id: string;
            client_reference_id: string;
            amount: number;
            payment_method: CashlessStaffPaymentMethod;
            notes?: string;
        },
        sessionToken?: string | null,
    ) => {
        const response = await publicApi.post<GenericDataResponse<CashlessTransaction>>(
            `/cashless/sales-points/${salesPointShortId}/topups`, payload, sessionHeaders(sessionToken),
        );
        return response.data;
    },
    getSalesPointTransactions: async (salesPointShortId: IdParam, sessionToken?: string | null) => {
        const response = await publicApi.get<GenericDataResponse<CashlessTransaction[]>>(
            `/cashless/sales-points/${salesPointShortId}/transactions`,
            sessionHeaders(sessionToken),
        );
        return response.data;
    },
    reverseTransaction: async (salesPointShortId: IdParam, transactionShortId: IdParam, sessionToken?: string | null) => {
        const response = await publicApi.post<GenericDataResponse<CashlessTransaction>>(
            `/cashless/sales-points/${salesPointShortId}/transactions/${transactionShortId}/reverse`,
            {},
            sessionHeaders(sessionToken),
        );
        return response.data;
    },
};
