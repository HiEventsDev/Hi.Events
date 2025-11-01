import {api} from "./client";
import {GenericDataResponse, GenericPaginatedResponse, IdParam, User} from "../types";

export interface AdminUser extends User {
    accounts?: AccountWithRole[];
    created_at?: string;
}

export interface AccountWithRole {
    id: IdParam;
    name: string;
    role: string;
}

export interface AdminAccount {
    id: IdParam;
    name: string;
    email: string;
    timezone?: string;
    currency_code?: string;
    created_at: string;
    events_count: number;
    users_count: number;
}

export interface AdminStats {
    total_users: number;
    total_accounts: number;
    total_live_events: number;
    total_tickets_sold: number;
}

export interface StartImpersonationRequest {
    account_id: IdParam;
}

export interface StartImpersonationResponse {
    message: string;
    redirect_url: string;
    token: string;
}

export interface StopImpersonationResponse {
    message: string;
    redirect_url: string;
    token: string;
}

export interface GetAllUsersParams {
    page?: number;
    per_page?: number;
    search?: string;
}

export interface GetAllAccountsParams {
    page?: number;
    per_page?: number;
    search?: string;
}

export const adminClient = {
    getStats: async () => {
        const response = await api.get<AdminStats>('admin/stats');
        return response.data;
    },

    getAllUsers: async (params: GetAllUsersParams = {}) => {
        const response = await api.get<GenericPaginatedResponse<AdminUser>>('admin/users', {
            params: {
                page: params.page || 1,
                per_page: params.per_page || 20,
                search: params.search || undefined,
            }
        });
        return response.data;
    },

    getAllAccounts: async (params: GetAllAccountsParams = {}) => {
        const response = await api.get<GenericPaginatedResponse<AdminAccount>>('admin/accounts', {
            params: {
                page: params.page || 1,
                per_page: params.per_page || 20,
                search: params.search || undefined,
            }
        });
        return response.data;
    },

    startImpersonation: async (userId: IdParam, accountId: IdParam) => {
        const response = await api.post<StartImpersonationResponse>(
            `admin/impersonate/${userId}`,
            { account_id: accountId }
        );
        return response.data;
    },

    stopImpersonation: async () => {
        const response = await api.post<StopImpersonationResponse>(
            'admin/stop-impersonation'
        );
        return response.data;
    },
};
