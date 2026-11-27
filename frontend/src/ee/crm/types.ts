import {IdParam} from "../../types.ts";

export type CrmEmailMarketingStatus = 'NOT_SUBSCRIBED' | 'SUBSCRIBED' | 'UNSUBSCRIBED' | 'BOUNCED' | 'COMPLAINED';
export type CrmSmsMarketingStatus = 'NOT_SUBSCRIBED' | 'SUBSCRIBED' | 'UNSUBSCRIBED';
export type CrmMarketingChannel = 'EMAIL' | 'SMS';
export type CrmContactSource = 'ORDER' | 'ATTENDEE' | 'WAITLIST' | 'IMPORT' | 'MANUAL' | 'FORM';
type CrmSyncState = 'NOT_STARTED' | 'SYNCING' | 'READY' | 'FAILED';

export type CrmActivityType =
    | 'ORDER_COMPLETED'
    | 'ORDER_AWAITING_PAYMENT'
    | 'ORDER_CANCELLED'
    | 'ORDER_REFUNDED'
    | 'CHECKOUT_ABANDONED'
    | 'TICKET_HELD'
    | 'CHECKED_IN'
    | 'WAITLIST_JOINED'
    | 'CAMPAIGN_SENT'
    | 'IMPORTED';

export interface CrmSyncStatus {
    status: CrmSyncState;
    synced_at: string | null;
    contacts_count: number;
    mailable_count: number;
    progress_percent: number;
}

export interface CrmTag {
    id: number;
    name: string;
    color: string | null;
    contacts_count?: number;
}

interface CrmContactSpend {
    currency: string;
    total_gross: number;
    total_refunded: number;
    net: number;
}

export type CrmConsentAction = 'OPT_IN' | 'OPT_OUT' | 'BOUNCED' | 'COMPLAINED' | 'BOUNCE_CLEARED';
type CrmConsentSource = 'ORDER' | 'IMPORT' | 'MANUAL' | 'UNSUBSCRIBE_LINK' | 'CAMPAIGN' | 'CONTACT' | 'FORM' | 'SMS_REPLY';

export interface CrmContactEmail {
    id: number;
    email: string;
    is_primary: boolean;
    consent_action: CrmConsentAction | null;
    preference_action: 'OPT_IN' | 'OPT_OUT' | null;
    preference_at: string | null;
}

export type CrmSuppressionReason = 'UNSUBSCRIBED' | 'BOUNCED' | 'COMPLAINED';

export interface CrmSuppressionDetail {
    reason: CrmSuppressionReason;
    since: string;
    source_type: CrmConsentSource;
    email: string;
    campaign_name: string | null;
    import_file_name: string | null;
    user_name: string | null;
    provider_rejected: boolean;
    reactivation_available_at: string | null;
}

export interface CrmSuppressionSummary {
    unsubscribed: number;
    bounced: number;
    complained: number;
    deleted: number;
}

export interface CrmSuppressionLookupResult {
    email: string;
    result: 'contact' | 'deleted' | 'not_found' | 'invalid';
    contact: {
        id: number;
        full_name: string;
        email_marketing_status: CrmEmailMarketingStatus;
        email_marketing_status_changed_at: string | null;
    } | null;
    suppression: CrmSuppressionDetail | null;
    deleted_suppression: CrmSuppressionReason | null;
}

export interface CrmSuppressionImportPreview {
    total_rows: number;
    valid_rows: number;
    invalid_rows: number;
    duplicate_rows: number;
    new_contacts: number;
    existing_contacts: number;
    subscribed_contacts: number;
    deleted_contacts: number;
    reasons: Record<CrmSuppressionReason, number>;
    sample: {email: string, reason: CrmSuppressionReason}[];
}

export interface CrmConsentEvent {
    id: number;
    channel: CrmMarketingChannel;
    action: CrmConsentAction;
    source: CrmConsentSource;
    email: string;
    phone: string | null;
    occurred_at: string;
    order_id: number | null;
    event_id: number | null;
    campaign_name: string | null;
    import_file_name: string | null;
    user_name: string | null;
    form_name: string | null;
}

interface CrmObservedName {
    first_name: string | null;
    last_name: string | null;
    role: 'SELF' | 'GUEST';
    seen_count: number;
}

export interface CrmContact {
    id: number;
    email: string;
    first_name: string | null;
    last_name: string | null;
    full_name: string;
    locale: string | null;
    source: CrmContactSource;
    email_marketing_status: CrmEmailMarketingStatus;
    email_marketing_status_changed_at: string | null;
    phone: string | null;
    sms_marketing_status: CrmSmsMarketingStatus;
    orders_count: number;
    tickets_count: number;
    events_count: number;
    attended_count: number;
    first_seen_at: string | null;
    last_order_at: string | null;
    last_attended_at: string | null;
    last_activity_at: string | null;
    created_at: string;
    tags: CrmTag[];
    spend?: CrmContactSpend[];
    emails?: CrmContactEmail[];
    names?: CrmObservedName[];
    no_show_count?: number;
    suppression?: CrmSuppressionDetail;
}

export interface CrmContactActivity {
    id: number;
    type: CrmActivityType;
    role: 'SELF' | 'GUEST' | null;
    event_id: number | null;
    event_title: string | null;
    event_timezone: string | null;
    occurrence_start_date: string | null;
    order_id: number | null;
    product_id: number | null;
    product_title: string | null;
    label: string | null;
    campaign_channel: CrmMarketingChannel | null;
    email: string | null;
    first_name: string | null;
    last_name: string | null;
    occurred_at: string;
    amount?: number | null;
    currency?: string | null;
}

export interface CrmContactNote {
    id: number;
    note: string;
    author_name: string | null;
    created_at: string;
}

export type CrmSegmentFieldKey =
    | 'orders_count'
    | 'tickets_count'
    | 'events_count'
    | 'attended_count'
    | 'no_show_count'
    | 'total_spent'
    | 'last_order_at'
    | 'last_attended_at'
    | 'first_seen_at'
    | 'ticket_event'
    | 'checked_in_event'
    | 'ticket_product'
    | 'ticket_location'
    | 'tag'
    | 'email_marketing_status'
    | 'sms_marketing_status'
    | 'has_phone'
    | 'source'
    | 'used_promo_code'
    | 'waitlist_missed_out'
    | 'abandoned_checkout';

export type CrmSegmentOperator =
    | 'gte' | 'lte' | 'eq'
    | 'within_days' | 'not_within_days' | 'before' | 'after' | 'is_empty'
    | 'in' | 'not_in'
    | 'is_true' | 'is_false';

export interface CrmSegmentRule {
    field: CrmSegmentFieldKey;
    operator: CrmSegmentOperator;
    value: number | string | number[] | string[] | null;
    currency?: string;
}

export interface CrmSegmentDefinition {
    match: 'all' | 'any';
    rules: CrmSegmentRule[];
}

export type CrmSegmentPresetKey =
    | 'subscribers'
    | 'sms_subscribers'
    | 'first_timers'
    | 'repeat_buyers'
    | 'top_fans'
    | 'lapsed'
    | 'no_shows'
    | 'missed_out'
    | 'abandoned_checkout';

interface CrmPresetSegment {
    key: CrmSegmentPresetKey;
    definition: CrmSegmentDefinition;
}

export interface CrmSegmentUsage {
    type: 'CAMPAIGN' | 'AUTOMATION';
    id: number;
    name: string | null;
    trigger: CrmAutomationTrigger | null;
    status: 'DRAFT' | 'REJECTED' | 'SCHEDULED' | 'ENABLED' | 'DISABLED';
    blocks_deletion: boolean;
}

export interface CrmSegment {
    id: number;
    name: string;
    description: string | null;
    definition: CrmSegmentDefinition;
    has_restricted_rules: boolean;
    usages: CrmSegmentUsage[];
    created_at: string;
    updated_at: string;
}

export interface CrmSegmentList {
    data: CrmSegment[];
    presets: CrmPresetSegment[];
}

export interface CrmSegmentCount {
    contacts_count: number;
    mailable_count: number;
    textable_count: number;
    textable_in_enabled_countries_count: number;
}

export interface CrmSegmentEventOption {
    id: number;
    title: string;
    status: string;
    timezone: string;
    starts_at: string | null;
}

export interface CrmSegmentOptionsParams {
    event_query?: string;
    event_ids?: number[];
    with_products?: boolean;
    product_query?: string;
    product_ids?: number[];
}

export interface CrmSegmentOptions {
    events: CrmSegmentEventOption[];
    products: { id: number; title: string; event_id: number; event_title: string }[];
    locations: { id: number; name: string }[];
    currencies: string[];
}

export interface CrmSegmentReference {
    segmentId?: IdParam;
    preset?: CrmSegmentPresetKey;
    definition?: CrmSegmentDefinition;
}

interface CrmContactSummary {
    id: number;
    email: string;
    full_name: string;
    events_count: number;
    orders_count: number;
    attended_count: number;
    last_order_at: string | null;
}

export interface CrmAudienceOverview {
    contacts_count: number;
    mailable_count: number;
    buyers_count: number;
    repeat_buyers_count: number;
    repeat_rate: number;
    new_contacts_count: number;
    orders_count: number;
    tickets_count: number;
    average_events_per_buyer: number;
    marketing_opt_in_enabled: boolean;
    live_events_count: number;
    live_events_collecting_opt_in: number;
    live_events_not_collecting_opt_in: {id: number, title: string}[];
    monthly_buyers: { month: string; new_buyers: number; returning_buyers: number }[];
    revenue_concentration: {
        currency: string;
        total_net: number;
        top_decile_net: number;
        top_decile_share: number;
    }[] | null;
}

export interface CrmRetention {
    cohorts: {
        cohort: string;
        size: number;
        months: { offset: number; contacts: number; percent: number }[];
    }[];
    max_offset: number;
}

export interface CrmLeadTime {
    buckets: { bucket: number; tickets: number; percent: number }[];
    tickets_count: number;
}

export interface CrmEventAudience {
    event_id: number;
    contacts_count: number;
    tickets_count: number;
    mailable_count: number;
    checked_in_count: number;
    new_count: number;
    returning_count: number;
    baseline_show_rate: number | null;
    expected_tickets: number | null;
    likely_no_show_contacts: number;
    top_contacts: CrmContactSummary[];
    overlaps: { event_id: number; title: string; shared_contacts: number }[];
}

export type CrmCampaignStatus = 'DRAFT' | 'SCHEDULED' | 'PENDING_REVIEW' | 'SENDING' | 'SENT' | 'PAUSED' | 'CANCELLED' | 'REJECTED';

interface CrmCampaignRevenue {
    currency: string;
    attributed_orders: number;
    attributed_revenue: number;
    influenced_orders: number;
    influenced_revenue: number;
}

export interface CrmCampaign {
    id: number;
    name: string;
    channel: CrmMarketingChannel;
    subject: string | null;
    preheader: string | null;
    body: string | null;
    status: CrmCampaignStatus;
    status_reason: string | null;
    status_reason_params?: Record<string, string | number> | null;
    review_note: string | null;
    is_editable: boolean;
    segment_id: number | null;
    preset: CrmSegmentPresetKey | null;
    definition: CrmSegmentDefinition | null;
    has_restricted_rules: boolean;
    segment_name: string | null;
    automation_id: number | null;
    event_id: number | null;
    event_title: string | null;
    early_access_product_ids: number[];
    early_access_releasable_product_ids: number[];
    promo_code: string | null;
    attribution_window_days: number;
    recipients_count: number;
    scheduled_at: string | null;
    sent_at: string | null;
    created_at: string;
    updated_at: string;
    stats: {
        sent: number;
        pending: number;
        failed: number;
        skipped: number;
        delivered: number;
        opened: number;
        clicked: number;
        bounced: number;
        complained: number;
        undelivered: number;
    };
    revenue?: CrmCampaignRevenue[];
    delivery_reports_available?: boolean;
    sms_waiting: CrmSmsWaitingCountry[];
}

interface CrmSmsWaitingCountry {
    country: CrmSmsCountry;
    pending_count: number;
    next_sending_at: string;
}

export interface CrmSmsPreviewVariant {
    countries: CrmSmsCountry[];
    text: string;
    characters: number;
    parts: number;
    is_unicode: boolean;
}

export interface CrmSmsCountryEstimate {
    country: CrmSmsCountry;
    recipients: number;
    parts: number;
    is_sending_now: boolean;
    next_sending_at: string;
}

export interface CrmSmsPreview {
    variants: CrmSmsPreviewVariant[];
    uses_placeholders: boolean;
    estimate: {countries: CrmSmsCountryEstimate[], recipients: number, message_parts: number} | null;
}

interface CrmCampaignSendGate {
    blocked_reason: string | null;
    blocked_reason_code: string | null;
    blocked_reason_params: Record<string, string | number> | null;
}

export interface CrmUnsubscribeStatus {
    organizer_name: string;
    email: string | null;
    is_unsubscribed: boolean;
    can_resubscribe: boolean;
    channel: CrmMarketingChannel;
    phone: string | null;
}

export type CrmSmsCountry = 'US' | 'GB' | 'IE' | 'AU';

export interface CrmSmsConnection {
    provider: string;
    account_sid: string;
    messaging_service_sid: string;
    enabled_countries: CrmSmsCountry[];
    updated_at: string;
}

export interface CrmSmsSender {
    sid: string;
    name: string;
}

interface CrmCampaignAiReview {
    decision: 'APPROVED' | 'NEEDS_PERSON';
    confidence: number;
    reasons: string[];
    reviewed_at: string;
}

export interface CrmAdminCampaign {
    id: number;
    name: string;
    subject: string | null;
    channel: CrmMarketingChannel;
    preview_html: string | null;
    preview_text: string | null;
    status_reason: string | null;
    ai_review: CrmCampaignAiReview | null;
    awaiting_automatic_check: boolean;
    account_id: number;
    account_name: string | null;
    organizer_id: number;
    organizer_name: string | null;
    recipients_count: number;
    import_only_recipients_count: number | null;
    account_created_at: string | null;
    scheduled_at: string | null;
    updated_at: string;
    audience_label?: string | null;
    preset?: CrmSegmentPresetKey | null;
    reply_to?: string | null;
    event_title?: string | null;
    early_access_tickets?: string[];
}

export interface CrmContactImport {
    id: number;
    file_name: string;
    kind: 'CONTACTS' | 'SUPPRESSIONS';
    status: 'PROCESSING' | 'COMPLETED' | 'FAILED';
    consent_attested: boolean;
    total_rows: number;
    created_count: number;
    updated_count: number;
    skipped_count: number;
    user_name: string | null;
    skipped_reasons: {
        invalid_email: number;
        duplicate_in_file: number;
        previously_deleted: number;
        already_suppressed: number;
        bounced_secondary_address: number;
    } | null;
    created_at: string;
}

export type CrmCampaignFolder = 'drafts' | 'scheduled' | 'sent';

export interface CrmCampaignListFilters {
    folder?: CrmCampaignFolder;
    source?: 'automation';
    query?: string;
}

export interface CrmCampaignList {
    data: CrmCampaign[];
    meta: {
        current_page: number;
        last_page: number;
        total: number;
        folders: Record<CrmCampaignFolder | 'automated', number>;
        send_gate?: CrmCampaignSendGate;
        scheduled_send_window_hours: number;
    };
}

export interface CrmCampaignSendCheck {
    mailable_count: number;
    requires_review: boolean;
    review_reason: string | null;
    blocked_reason: string | null;
    blocked_reason_code: string | null;
    blocked_reason_params: Record<string, string | number> | null;
    warnings: string[];
    early_access_tickets: {id: number, title: string, is_hidden: boolean}[];
}

export interface CrmCampaignPreview {
    html: string;
    subject: string;
    preheader: string | null;
    from_name: string;
    reply_to: string | null;
}

export interface CrmCampaignTemplate {
    id: number;
    name: string;
    subject: string | null;
    preheader: string | null;
    body: string;
    last_used_at: string | null;
    created_at: string;
    updated_at: string;
}

type CrmCampaignStarterKey = 'event_announcement' | 'last_chance' | 'thank_you' | 'newsletter';

export interface CrmCampaignStarter {
    key: CrmCampaignStarterKey;
    name: string;
    description: string;
    subject: string;
    preheader: string;
    body: string;
}

export interface CrmCampaignTemplates {
    data: CrmCampaignTemplate[];
    starters: CrmCampaignStarter[];
}

export type CrmRecipientFilter = 'all' | 'delivered' | 'opened' | 'clicked' | 'bounced' | 'not_sent';

export interface CrmCampaignRecipient {
    id: number;
    contact_id: number | null;
    email: string | null;
    full_name: string;
    status: 'PENDING' | 'SENDING' | 'SENT' | 'FAILED' | 'SKIPPED';
    sent_at: string | null;
    delivered_at: string | null;
    opened_at: string | null;
    clicked_at: string | null;
    bounced_at: string | null;
    complained_at: string | null;
    phone: string | null;
    failure_reason: string | null;
}

export type CrmAutomationTrigger =
    'EVENT_PUBLISHED' | 'AFTER_EVENT' | 'BEFORE_EVENT' | 'ABANDONED_CHECKOUT' | 'SEGMENT_ENTERED' | 'WIN_BACK' | 'WELCOME';

export type CrmOccurrenceAudience = 'not_purchased' | 'purchased';

export interface CrmAutomationSettings {
    delay_days: number;
    wait_minutes: number;
    days_before: number;
    occurrence_audience: CrmOccurrenceAudience;
}

export interface CrmAutomationPreview {
    mailable_count: number;
    first_send: {
        kind: 'NEXT_PUBLISH' | 'AFTER_DATE' | 'BEFORE_DATE' | 'AFTER_ABANDON' | 'ON_ENTRY' | 'DAILY';
        at: string | null;
        event_title: string | null;
        event_date: string | null;
    };
    subject: string;
    html: string;
}

export interface CrmAutomationTemplate extends CrmAutomationSettings {
    trigger: CrmAutomationTrigger;
    name: string;
    subject: string;
    body: string;
    preset: CrmSegmentPresetKey | null;
}

export interface CrmAutomation extends CrmAutomationSettings {
    id: number;
    name: string;
    trigger: CrmAutomationTrigger;
    is_enabled: boolean;
    enabled_at: string | null;
    subject: string;
    preheader: string | null;
    body: string;
    segment_id: number | null;
    preset: CrmSegmentPresetKey | null;
    sent_count: number;
    last_sent_at: string | null;
    last_failure_reason: string | null;
    last_failure_reason_params: Record<string, string | number> | null;
}

type CrmBulkAction = 'ADD_TAGS' | 'REMOVE_TAGS' | 'UNSUBSCRIBE' | 'DELETE';

type CrmBulkOperationStatus = 'RUNNING' | 'COMPLETED' | 'FAILED' | 'UNDONE';

export interface CrmBulkOperation {
    id: number;
    action: CrmBulkAction;
    tag_ids: number[];
    status: CrmBulkOperationStatus;
    total: number;
    processed: number;
    changed: number;
    created_at: string;
}

export type CrmBulkSelection =
    | {mode: 'ids'; contactIds: number[]}
    | {mode: 'filter'; query: string; segment: CrmSegmentReference};

export type CrmSignupFormStatus = 'ACTIVE' | 'PAUSED';

export interface CrmSignupFormContent {
    title: string;
    description: string | null;
    button_label: string;
    success_message: string | null;
    asks_for_name: boolean;
    privacy_url: string | null;
}

export interface CrmSignupForm extends CrmSignupFormContent {
    id: number;
    public_id: string;
    name: string;
    status: CrmSignupFormStatus;
    tag_ids: number[];
    show_on_organizer_page: boolean;
    waiting_count: number | null;
    submissions_count: number;
    confirmations_count: number;
    last_confirmed_at: string | null;
    created_at: string;
    updated_at: string;
}

export interface CrmSignupFormPublic extends CrmSignupFormContent {
    public_id: string;
    organizer_id: number;
    organizer_name: string | null;
    organizer_slug: string | null;
}

export interface CrmSubscriptionConfirmation {
    status: 'pending' | 'confirmed' | 'expired' | 'invalid';
    organizer_id: number | null;
    organizer_name: string | null;
    masked_email: string | null;
    success_message: string | null;
    form_public_id: string | null;
    link_lifetime_days: number;
}
