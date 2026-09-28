import {api} from "./client";
import {
    CashlessClosureResult,
    CashlessDailyStats,
    CashlessRefundResult,
    CashlessSalesPoint,
    CashlessSettings,
    CashlessSummary,
    CashlessTransaction,
    CashlessWallet,
    CashlessWalletStatus,
    CreateCashlessTopupRequest,
    GenericDataResponse,
    GenericPaginatedResponse,
    IdParam,
    QueryFilters,
    UpsertCashlessSalesPointRequest,
} from "../types";
import {queryParamsHelper} from "../utilites/queryParamsHelper.ts";

export const cashlessClient = {
    getSettings: async (eventId: IdParam) => {
        const response = await api.get<GenericDataResponse<CashlessSettings>>(`events/${eventId}/cashless/settings`);
        return response.data;
    },
    updateSettings: async (eventId: IdParam, settings: Omit<CashlessSettings, 'event_id' | 'cashless_topup_product_id' | 'cashless_closed_at'>) => {
        const response = await api.put<GenericDataResponse<CashlessSettings>>(`events/${eventId}/cashless/settings`, settings);
        return response.data;
    },

    getSummary: async (eventId: IdParam) => {
        const response = await api.get<GenericDataResponse<CashlessSummary>>(`events/${eventId}/cashless/summary`);
        return response.data;
    },

    closeCashless: async (eventId: IdParam) => {
        const response = await api.post<GenericDataResponse<CashlessClosureResult>>(`events/${eventId}/cashless/close`);
        return response.data;
    },

    getDailyStats: async (eventId: IdParam, startDate: string, endDate: string) => {
        const response = await api.get<{ data: CashlessDailyStats[] }>(
            `events/${eventId}/cashless/stats?start_date=${startDate}&end_date=${endDate}`,
        );
        return response.data;
    },

    allSalesPoints: async (eventId: IdParam, pagination: QueryFilters) => {
        const response = await api.get<GenericPaginatedResponse<CashlessSalesPoint>>(
            `events/${eventId}/cashless/sales-points` + queryParamsHelper.buildQueryString(pagination),
        );
        return response.data;
    },
    findSalesPointById: async (eventId: IdParam, salesPointId: IdParam) => {
        const response = await api.get<GenericDataResponse<CashlessSalesPoint>>(
            `events/${eventId}/cashless/sales-points/${salesPointId}`,
        );
        return response.data;
    },
    createSalesPoint: async (eventId: IdParam, salesPoint: UpsertCashlessSalesPointRequest) => {
        const response = await api.post<GenericDataResponse<CashlessSalesPoint>>(
            `events/${eventId}/cashless/sales-points`, salesPoint,
        );
        return response.data;
    },
    updateSalesPoint: async (eventId: IdParam, salesPointId: IdParam, salesPoint: UpsertCashlessSalesPointRequest) => {
        const response = await api.put<GenericDataResponse<CashlessSalesPoint>>(
            `events/${eventId}/cashless/sales-points/${salesPointId}`, salesPoint,
        );
        return response.data;
    },
    deleteSalesPoint: async (eventId: IdParam, salesPointId: IdParam) => {
        const response = await api.delete(`events/${eventId}/cashless/sales-points/${salesPointId}`);
        return response.data;
    },

    allWallets: async (eventId: IdParam, pagination: QueryFilters) => {
        const response = await api.get<GenericPaginatedResponse<CashlessWallet>>(
            `events/${eventId}/cashless/wallets` + queryParamsHelper.buildQueryString(pagination),
        );
        return response.data;
    },
    findWalletById: async (eventId: IdParam, walletId: IdParam) => {
        const response = await api.get<GenericDataResponse<CashlessWallet>>(
            `events/${eventId}/cashless/wallets/${walletId}`,
        );
        return response.data;
    },
    lookupWallet: async (eventId: IdParam, attendeePublicId: string) => {
        const response = await api.post<GenericDataResponse<CashlessWallet>>(
            `events/${eventId}/cashless/wallets/lookup`, {attendee_public_id: attendeePublicId},
        );
        return response.data;
    },
    topUpWallet: async (eventId: IdParam, walletId: IdParam, topup: CreateCashlessTopupRequest) => {
        const response = await api.post<GenericDataResponse<CashlessTransaction>>(
            `events/${eventId}/cashless/wallets/${walletId}/topups`, topup,
        );
        return response.data;
    },
    updateWalletStatus: async (eventId: IdParam, walletId: IdParam, status: CashlessWalletStatus) => {
        const response = await api.patch<GenericDataResponse<CashlessWallet>>(
            `events/${eventId}/cashless/wallets/${walletId}/status`, {status},
        );
        return response.data;
    },
    refundWallet: async (eventId: IdParam, walletId: IdParam, method: 'ORIGINAL_PAYMENT' | 'CASH') => {
        const response = await api.post<GenericDataResponse<CashlessRefundResult>>(
            `events/${eventId}/cashless/wallets/${walletId}/refund`, {method},
        );
        return response.data;
    },

    allTransactions: async (eventId: IdParam, pagination: QueryFilters) => {
        const response = await api.get<GenericPaginatedResponse<CashlessTransaction>>(
            `events/${eventId}/cashless/transactions` + queryParamsHelper.buildQueryString(pagination),
        );
        return response.data;
    },
    reverseTransaction: async (eventId: IdParam, transactionId: IdParam, notes?: string) => {
        const response = await api.post<GenericDataResponse<CashlessTransaction>>(
            `events/${eventId}/cashless/transactions/${transactionId}/reverse`, {notes},
        );
        return response.data;
    },
    exportTransactions: async (eventId: IdParam): Promise<Blob> => {
        const response = await api.post(`events/${eventId}/cashless/transactions/export`, {}, {
            responseType: 'blob',
        });
        return response.data;
    },
};
