import {api} from "./client";
import {EmailTemplateToken, GenericDataResponse, GenericPaginatedResponse, IdParam, User} from "../types";

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

export interface AccountMessagingTier {
    id: number;
    name: string;
    max_messages_per_24h: number;
    max_recipients_per_message: number;
    links_allowed: boolean;
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
    messaging_tier?: {
        id: number;
        name: string;
    };
}

export interface AccountConfiguration {
    id: number;
    name: string;
    is_system_default: boolean;
    default_for_currency: string | null;
    application_fees: {
        fixed: number;
        percentage: number;
        currency: string;
    };
    bypass_application_fees: boolean;
}

export const isDefaultConfiguration = (config: AccountConfiguration): boolean =>
    config.is_system_default || Boolean(config.default_for_currency);

export interface CreateConfigurationData {
    name: string;
    application_fees: {
        fixed: number;
        percentage: number;
        currency: string;
    };
    bypass_application_fees?: boolean;
}

export interface UpdateConfigurationData {
    name: string;
    application_fees: {
        fixed: number;
        percentage: number;
        currency: string;
    };
    bypass_application_fees?: boolean;
}

export interface AdminOrganizerVatSetting {
    id: number;
    vat_registered: boolean;
    vat_number: string | null;
    vat_validated: boolean;
    vat_validation_status: 'PENDING' | 'VALIDATING' | 'VALID' | 'INVALID' | 'FAILED' | null;
    vat_validation_date: string | null;
    business_name: string | null;
    business_address: string | null;
    vat_country_code: string | null;
}

export interface AdminOrganizerSummary {
    id: IdParam;
    name: string;
    configuration: AccountConfiguration | null;
    vat_setting: AdminOrganizerVatSetting | null;
}

export interface AdminLifecycleNudge {
    nudge_type: string;
    sent_at: string;
    event_id: number | null;
}

export interface AdminAccountDetail extends AdminAccount {
    messaging_tier?: AccountMessagingTier;
    organizers: AdminOrganizerSummary[];
    is_manually_verified: boolean;
    active_deletion_request: {
        id: IdParam;
        scheduled_deletion_at: string;
        expected_outcome: 'HARD_DELETE' | 'ANONYMIZE';
    } | null;
    lifecycle_nudges?: AdminLifecycleNudge[];
}

export interface AdminRequestAccountDeletionPayload {
    reason?: string;
    execute_immediately: boolean;
}

export type LifecycleNudgeType = 'NO_EVENT_CREATED' | 'STRIPE_ONBOARDING_INCOMPLETE' | 'EVENT_DRAFT_STALE' | 'LIVE_EVENT_NO_SALES';

export interface LifecycleEmailContent {
    subject: string;
    body: string;
    cta_label: string;
}

export interface AdminLifecycleEmail extends LifecycleEmailContent {
    nudge_type: LifecycleNudgeType;
    enabled_at: string | null;
    is_customised: boolean;
    sent_total: number;
    sent_last_30_days: number;
    delivered_total: number;
    bounced_total: number;
    opened_total: number;
    clicked_total: number;
    tokens: EmailTemplateToken[];
}

export interface AdminLifecycleEmailsResponse {
    data: AdminLifecycleEmail[];
    meta: {
        sending_configured: boolean;
    };
}

export interface LifecycleEmailPreview {
    subject: string;
    html: string;
}

export interface AdminFeatureFlag {
    key: string;
    enabled_by_default: boolean;
    enabled_override_count: number;
    disabled_override_count: number;
}

export interface AdminFeatureFlagOverride {
    account_id: number;
    account_name: string;
    enabled: boolean;
    updated_at: string;
}

export interface AdminAccountFeatureFlag {
    key: string;
    enabled_by_default: boolean;
    override: boolean | null;
    enabled: boolean;
}

export interface UpdateAdminOrganizerVatSettingData {
    vat_registered?: boolean;
    vat_number?: string | null;
    vat_validated?: boolean;
    business_name?: string | null;
    business_address?: string | null;
    vat_country_code?: string | null;
}

export interface UpdateOrganizerConfigurationOverrideData {
    application_fees?: {
        fixed: number;
        percentage: number;
        currency: string;
    };
    bypass_application_fees?: boolean;
}


export interface AdminStats {
    total_users: number;
    total_accounts: number;
    total_live_events: number;
    total_tickets_sold: number;
}

export type AdminDashboardPeriod = '24h' | '7d' | '30d';

export interface AdminEmailDeliveryCategoryStats {
    category: string | null;
    sent: number;
    delivered: number;
    permanent_bounces: number;
    transient_bounces: number;
    complaints: number;
    opened: number;
    clicked: number;
}

export interface AdminEmailDeliveryStats {
    delivery_webhook_configured: boolean;
    categories: AdminEmailDeliveryCategoryStats[];
}

export interface AdminCommissionAmount {
    gross: number;
    net: number;
}

export interface AdminCommissionByCurrency extends Record<AdminDashboardPeriod, AdminCommissionAmount> {
    currency: string;
}

export interface AdminSalesByCurrency {
    currency: string;
    orders_count: number;
    total: number;
}

interface AdminDashboardEventBase {
    id: IdParam;
    title: string;
    slug: string;
    account_id: IdParam;
    account_name: string | null;
    organizer_name: string | null;
}

export interface AdminTopSellingEvent extends AdminDashboardEventBase {
    status: string;
    currency: string;
    tickets_sold: number;
    orders_count: number;
    sales_total: number;
}

export interface AdminMostViewedEvent extends AdminDashboardEventBase {
    status: string;
    views: number;
    orders_count: number;
}

export interface AdminUpcomingEvent extends AdminDashboardEventBase {
    timezone: string;
    occurrence_id: IdParam;
    start_date: string;
    capacity: number | null;
    attendees_count: number;
    orders_last_24h: number;
}

export interface RecentAccount {
    id: IdParam;
    name: string;
    email: string;
    created_at: string;
    stripe_connect_setup_complete: boolean;
    account_verified_at: string | null;
    events_count: number;
}

export interface AdminDashboardAttention {
    flagged_events: number;
    messages_pending_review: number;
    failed_jobs: number;
    pending_deletion_requests: number;
}

export interface AdminTopCommissionAccount {
    id: IdParam;
    name: string;
    email: string;
    currency: string;
    orders_count: number;
    gross: number;
    net: number;
}

export interface AdminDashboardData {
    period: AdminDashboardPeriod;
    attention: AdminDashboardAttention;
    commission_by_currency: AdminCommissionByCurrency[];
    views_since: string;
    sales_by_currency: AdminSalesByCurrency[];
    orders_count: number;
    tickets_sold: number;
    signups_count: number;
    events_created_count: number;
    top_selling_events: AdminTopSellingEvent[];
    top_accounts_by_commission: AdminTopCommissionAccount[];
    most_viewed_events: AdminMostViewedEvent[];
    upcoming_events: AdminUpcomingEvent[];
    recent_accounts: RecentAccount[];
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

export interface GetAllDeletionRequestsParams {
    page?: number;
    per_page?: number;
    search?: string;
    status?: string;
}

export interface AdminDeletionRequest {
    id: IdParam;
    status: 'REQUESTED' | 'CANCELLED' | 'COMPLETED';
    initiated_by: 'ACCOUNT_OWNER' | 'ADMIN';
    reason: string | null;
    expected_outcome: 'HARD_DELETE' | 'ANONYMIZE' | null;
    outcome: 'HARD_DELETE' | 'ANONYMIZE' | null;
    scheduled_deletion_at: string;
    reminder_sent_at: string | null;
    cancelled_at: string | null;
    completed_at: string | null;
    requested_at: string;
    deletion_manifest: Record<string, unknown>[] | Record<string, number> | null;
    account: { id: IdParam; name: string; email: string } | null;
    requested_by_user: { id: IdParam; full_name: string; email: string } | null;
    cancelled_by_user: { id: IdParam; full_name: string } | null;
}

export interface GetAllEventsParams {
    page?: number;
    per_page?: number;
    search?: string;
    sort_by?: string;
    sort_direction?: 'asc' | 'desc';
}

export interface GetAllOrdersParams {
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

export interface AdminOrder {
    id: number;
    short_id: string;
    public_id: string;
    first_name: string;
    last_name: string;
    email: string | null;
    total_gross: number;
    total_tax: number;
    total_fee: number;
    currency: string;
    status: string;
    payment_status: string;
    created_at: string;
    account_id: number;
    account_name: string;
    event_id: number;
    event_title: string;
}

export interface UtmAttributionStats {
    attribution_value: string;
    total_accounts: number;
    total_events: number;
    live_events: number;
    stripe_connected: number;
    verified_accounts: number;
    revenue_by_currency: Record<string, number>;
    total_orders: number;
}

export interface UtmAttributionSummary {
    paid_accounts: number;
    organic_accounts: number;
    referral_accounts: number;
    attributed_accounts: number;
    unattributed_accounts: number;
    total_accounts: number;
}

export type AttributionGroupBy = 'source' | 'medium' | 'campaign' | 'content' | 'term' | 'cta' | 'source_type';

export interface GetUtmAttributionStatsParams {
    group_by?: AttributionGroupBy;
    date_from?: string;
    date_to?: string;
    page?: number;
    per_page?: number;
}

export interface AdminFailedJob {
    id: IdParam;
    uuid: string;
    connection: string;
    queue: string;
    job_name: string;
    job_name_full: string;
    payload: string;
    exception_summary: string;
    exception: string;
    failed_at: string;
}

export interface GetAllFailedJobsParams {
    page?: number;
    per_page?: number;
    search?: string;
    queue?: string;
}

export interface AdminMessage {
    id: IdParam;
    event_id: IdParam;
    event_title: string;
    account_name: string;
    subject: string;
    message: string;
    type: string;
    status: string;
    recipients_count: number;
    sent_by: string;
    sent_at: string | null;
    created_at: string;
    eligibility_failures?: string[];
}

export interface SpamCheckVerdict {
    confidence: number;
    reasons: string[];
}

export interface AdminSpamEvent {
    id: IdParam;
    event_id: IdParam;
    event_title: string;
    event_slug: string;
    event_description: string;
    organizer_name: string | null;
    account_name: string | null;
    account_email: string | null;
    account_id: IdParam;
    verdict: SpamCheckVerdict;
    checked_at: string;
}

export interface GetAllAdminSpamEventsParams {
    page?: number;
    per_page?: number;
    search?: string;
}

export interface GetAllAdminMessagesParams {
    page?: number;
    per_page?: number;
    search?: string;
    status?: string;
    type?: string;
    sort_by?: string;
    sort_direction?: 'asc' | 'desc';
}

export interface LaravelPaginatedData<T> {
    current_page: number;
    data: T[];
    first_page_url: string;
    from: number | null;
    last_page: number;
    last_page_url: string;
    next_page_url: string | null;
    path: string;
    per_page: number;
    prev_page_url: string | null;
    to: number | null;
    total: number;
}

export interface UtmAttributionStatsResponse {
    data: LaravelPaginatedData<UtmAttributionStats>;
    summary: UtmAttributionSummary;
}

export const adminClient = {
    getStats: async () => {
        const response = await api.get<AdminStats>('admin/stats');
        return response.data;
    },

    getEmailDeliveryStats: async (period: AdminDashboardPeriod) => {
        const response = await api.get<{ data: AdminEmailDeliveryStats }>('admin/email-deliveries/stats', {
            params: {period},
        });
        return response.data.data;
    },

    getDashboardData: async (period: AdminDashboardPeriod) => {
        const response = await api.get<AdminDashboardData>('admin/dashboard', {
            params: {period},
        });
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

    getAllOrders: async (params: GetAllOrdersParams = {}) => {
        const response = await api.get<GenericPaginatedResponse<AdminOrder>>('admin/orders', {
            params: {
                page: params.page || 1,
                per_page: params.per_page || 20,
                search: params.search || undefined,
                sort_by: params.sort_by || 'created_at',
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

    resetUserTwoFactor: async (userId: IdParam) => {
        await api.post(`admin/users/${userId}/two-factor/reset`);
    },

    stopImpersonation: async () => {
        const response = await api.post<StopImpersonationResponse>(
            'admin/stop-impersonation'
        );
        return response.data;
    },

    getAccount: async (accountId: IdParam) => {
        const response = await api.get<GenericDataResponse<AdminAccountDetail>>(
            `admin/accounts/${accountId}`
        );
        return response.data;
    },

    getAllConfigurations: async () => {
        const response = await api.get<GenericDataResponse<AccountConfiguration[]>>(
            'admin/configurations'
        );
        return response.data;
    },

    createConfiguration: async (data: CreateConfigurationData) => {
        const response = await api.post<GenericDataResponse<AccountConfiguration>>(
            'admin/configurations',
            data
        );
        return response.data;
    },

    updateConfiguration: async (configurationId: IdParam, data: UpdateConfigurationData) => {
        const response = await api.put<GenericDataResponse<AccountConfiguration>>(
            `admin/configurations/${configurationId}`,
            data
        );
        return response.data;
    },

    deleteConfiguration: async (configurationId: IdParam) => {
        const response = await api.delete(`admin/configurations/${configurationId}`);
        return response.data;
    },

    assignOrganizerConfiguration: async (organizerId: IdParam, configurationId: IdParam) => {
        const response = await api.put(
            `admin/organizers/${organizerId}/configuration`,
            { configuration_id: configurationId },
        );
        return response.data;
    },

    updateOrganizerConfigurationOverride: async (organizerId: IdParam, data: UpdateOrganizerConfigurationOverrideData) => {
        const response = await api.patch<GenericDataResponse<AccountConfiguration>>(
            `admin/organizers/${organizerId}/configuration`,
            data,
        );
        return response.data;
    },

    updateOrganizerVatSetting: async (organizerId: IdParam, data: UpdateAdminOrganizerVatSettingData) => {
        const response = await api.put<GenericDataResponse<AdminOrganizerVatSetting>>(
            `admin/organizers/${organizerId}/vat-settings`,
            data,
        );
        return response.data;
    },

    getUtmAttributionStats: async (params: GetUtmAttributionStatsParams = {}) => {
        const response = await api.get<UtmAttributionStatsResponse>(
            'admin/attribution/stats',
            { params }
        );
        return response.data;
    },

    getAllFailedJobs: async (params: GetAllFailedJobsParams = {}) => {
        const response = await api.get<GenericPaginatedResponse<AdminFailedJob>>('admin/failed-jobs', {
            params: {
                page: params.page || 1,
                per_page: params.per_page || 20,
                search: params.search || undefined,
                queue: params.queue || undefined,
            }
        });
        return response.data;
    },

    deleteFailedJob: async (jobId: IdParam) => {
        const response = await api.delete(`admin/failed-jobs/${jobId}`);
        return response.data;
    },

    deleteAllFailedJobs: async () => {
        const response = await api.delete('admin/failed-jobs');
        return response.data;
    },

    retryFailedJob: async (jobId: IdParam) => {
        const response = await api.post(`admin/failed-jobs/${jobId}/retry`);
        return response.data;
    },

    retryAllFailedJobs: async () => {
        const response = await api.post('admin/failed-jobs/retry-all');
        return response.data;
    },

    getAllAdminMessages: async (params: GetAllAdminMessagesParams = {}) => {
        const response = await api.get<GenericPaginatedResponse<AdminMessage>>('admin/messages', {
            params: {
                page: params.page || 1,
                per_page: params.per_page || 20,
                search: params.search || undefined,
                status: params.status || undefined,
                type: params.type || undefined,
                sort_by: params.sort_by || 'created_at',
                sort_direction: params.sort_direction || 'desc',
            }
        });
        return response.data;
    },

    approveMessage: async (messageId: IdParam) => {
        const response = await api.post(`admin/messages/${messageId}/approve`);
        return response.data;
    },

    getAllAdminSpamEvents: async (params: GetAllAdminSpamEventsParams = {}) => {
        const response = await api.get<GenericPaginatedResponse<AdminSpamEvent>>('admin/spam-events', {
            params: {
                page: params.page || 1,
                per_page: params.per_page || 20,
                search: params.search || undefined,
            }
        });
        return response.data;
    },

    approveSpamEvent: async (eventId: IdParam) => {
        const response = await api.post(`admin/spam-events/${eventId}/approve`);
        return response.data;
    },

    confirmSpamEvent: async (eventId: IdParam) => {
        const response = await api.post(`admin/spam-events/${eventId}/confirm-spam`);
        return response.data;
    },

    deleteSpamEvent: async (eventId: IdParam, deleteAccount: boolean) => {
        const response = await api.post(`admin/spam-events/${eventId}/delete`, {delete_account: deleteAccount});
        return response.data;
    },

    updateAccountMessagingTier: async (accountId: IdParam, tierId: number) => {
        const response = await api.put(`admin/accounts/${accountId}/messaging-tier`, {
            messaging_tier_id: tierId
        });
        return response.data;
    },

    updateAccountVerification: async (accountId: IdParam, isManuallyVerified: boolean) => {
        const response = await api.put(`admin/accounts/${accountId}/verification`, {
            is_manually_verified: isManuallyVerified
        });
        return response.data;
    },

    getFeatureFlags: async () => {
        const response = await api.get<GenericDataResponse<AdminFeatureFlag[]>>('admin/feature-flags');
        return response.data;
    },

    updateFeatureFlag: async (key: string, enabledByDefault: boolean) => {
        const response = await api.put<GenericDataResponse<AdminFeatureFlag>>(`admin/feature-flags/${key}`, {
            enabled_by_default: enabledByDefault
        });
        return response.data;
    },

    getFeatureFlagOverrides: async (key: string) => {
        const response = await api.get<GenericDataResponse<AdminFeatureFlagOverride[]>>(`admin/feature-flags/${key}/overrides`);
        return response.data;
    },

    getAccountFeatureFlags: async (accountId: IdParam) => {
        const response = await api.get<GenericDataResponse<AdminAccountFeatureFlag[]>>(`admin/accounts/${accountId}/feature-flags`);
        return response.data;
    },

    setAccountFeatureFlag: async (accountId: IdParam, key: string, enabled: boolean | null) => {
        const response = await api.put<GenericDataResponse<AdminAccountFeatureFlag[]>>(`admin/accounts/${accountId}/feature-flags/${key}`, {
            enabled
        });
        return response.data;
    },

    getLifecycleEmails: async () => {
        const response = await api.get<AdminLifecycleEmailsResponse>('admin/lifecycle-emails');
        return response.data;
    },

    updateLifecycleEmail: async (nudgeType: LifecycleNudgeType, data: LifecycleEmailContent & { enabled: boolean }) => {
        const response = await api.put<GenericDataResponse<AdminLifecycleEmail>>(`admin/lifecycle-emails/${nudgeType}`, data);
        return response.data;
    },

    previewLifecycleEmail: async (nudgeType: LifecycleNudgeType, data: LifecycleEmailContent) => {
        const response = await api.post<GenericDataResponse<LifecycleEmailPreview>>(`admin/lifecycle-emails/${nudgeType}/preview`, data);
        return response.data;
    },

    sendTestLifecycleEmail: async (nudgeType: LifecycleNudgeType, data: LifecycleEmailContent) => {
        await api.post(`admin/lifecycle-emails/${nudgeType}/test`, data);
    },

    getMessagingTiers: async (): Promise<GenericDataResponse<AccountMessagingTier[]>> => {
        const response = await api.get<GenericDataResponse<AccountMessagingTier[]>>('admin/messaging-tiers');
        return response.data;
    },

    getDeletionRequests: async (params: GetAllDeletionRequestsParams = {}) => {
        const response = await api.get<GenericPaginatedResponse<AdminDeletionRequest>>('admin/deletion-requests', {
            params: {
                page: params.page || 1,
                per_page: params.per_page || 20,
                search: params.search || undefined,
                status: params.status || undefined,
            }
        });
        return response.data;
    },

    requestAccountDeletion: async (accountId: IdParam, payload: AdminRequestAccountDeletionPayload) => {
        const response = await api.post(`admin/accounts/${accountId}/deletion-request`, payload);
        return response.data;
    },

    cancelDeletionRequest: async (deletionRequestId: IdParam) => {
        const response = await api.delete(`admin/deletion-requests/${deletionRequestId}`);
        return response.data;
    },

    executeDeletionRequest: async (deletionRequestId: IdParam) => {
        const response = await api.post(`admin/deletion-requests/${deletionRequestId}/execute`);
        return response.data;
    },
};
