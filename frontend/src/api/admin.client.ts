import {api} from "./client";
import {GenericPaginatedResponse, IdParam, User} from "../types";

export interface AdminUser extends User {
    accounts?: AccountWithRole[];
    created_at?: string;
}

export interface AccountWithRole {
    id: IdParam;
    name: string;
    role: string;
}

export interface AdminAccountUser {
    id: IdParam;
    first_name: string;
    last_name: string;
    email: string;
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
    users: AdminAccountUser[];
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

export interface GetAllEventsParams {
    page?: number;
    per_page?: number;
    search?: string;
    sort_by?: string;
    sort_direction?: 'asc' | 'desc';
}

export interface AdminEventStatistics {
    total_gross_sales: number;
    products_sold: number;
    attendees_registered: number;
    orders_created: number;
    orders_cancelled: number;
}

export interface AdminEvent {
    id: IdParam;
    title: string;
    start_date: string;
    end_date: string | null;
    status: string;
    organizer_name: string;
    organizer_id: IdParam;
    account_name: string;
    account_id: IdParam;
    user_id: IdParam;
    attendees_count: number;
    slug: string;
    statistics: AdminEventStatistics | null;
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

    getUpcomingEvents: async (perPage: number = 10) => {
        const response = await api.get<GenericPaginatedResponse<any>>('admin/events/upcoming', {
            params: {
                per_page: perPage,
            }
        });
        return response.data;
    },

    getAllEvents: async (params: GetAllEventsParams = {}) => {
        const response = await api.get<GenericPaginatedResponse<AdminEvent>>('admin/events', {
            params: {
                page: params.page || 1,
                per_page: params.per_page || 20,
                search: params.search || undefined,
                sort_by: params.sort_by || 'start_date',
                sort_direction: params.sort_direction || 'desc',
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
