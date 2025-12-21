/**
 * @todo - This file needs to be organized better. Split into multiple files.
 */
import {SupportedLocales} from "./locales.ts";

export type ConfigKeys = 
    | 'VITE_API_URL_SERVER'
    | 'VITE_API_URL_CLIENT'
    | 'VITE_FRONTEND_URL'
    | 'VITE_APP_PRIMARY_COLOR'
    | 'VITE_APP_SECONDARY_COLOR'
    | 'VITE_APP_NAME'
    | 'VITE_APP_FAVICON'
    | 'VITE_APP_LOGO_DARK'
    | 'VITE_APP_LOGO_LIGHT'
    | 'VITE_CHATWOOT_BASE_URL'
    | 'VITE_CHATWOOT_WEBSITE_TOKEN'
    | 'VITE_HIDE_ABOUT_LINK'
    | 'VITE_TOS_URL'
    | 'VITE_PRIVACY_URL'
    | 'VITE_PLATFORM_SUPPORT_EMAIL'
    | 'VITE_STRIPE_PUBLISHABLE_KEY'
    | 'VITE_I_HAVE_PURCHASED_A_LICENCE'
    | 'VITE_DEFAULT_IMAGE_URL';

export enum StripePlatform {
    Canada = 'ca',
    Ireland = 'ie',
}

export type IdParam = string | undefined | number;

export interface AcceptInvitationRequest {
    first_name: string;
    last_name: string;
    email: string;
    password: string;
    password_confirmation: string;
    marketing_opt_in?: boolean;
}

export interface RegisterAccountRequest extends AcceptInvitationRequest {
    locale: SupportedLocales;
    utm_source?: string | null;
    utm_medium?: string | null;
    utm_campaign?: string | null;
    utm_term?: string | null;
    utm_content?: string | null;
    referrer_url?: string | null;
    landing_page?: string | null;
    gclid?: string | null;
    fbclid?: string | null;
    utm_raw?: Record<string, string> | null;
}

export interface ResetPasswordRequest {
    password: string;
    password_confirmation: string;
}

export interface ColorTheme {
    name: string;
    homepage_background_color: string;
    homepage_content_background_color: string;
    homepage_primary_color: string;
    homepage_primary_text_color: string;
    homepage_secondary_color: string;
    homepage_secondary_text_color: string;
}

export interface HomepageThemeSettings {
    accent: string;
    background: string;
    mode: 'light' | 'dark';
    background_type: 'COLOR' | 'MIRROR_COVER_IMAGE';
}

export interface LoginResponse {
    token?: string;
    token_type: string;
    expires_in: number;
    user: User;
    accounts: Account[];
}

export interface User {
    id?: IdParam;
    account_id?: IdParam;
    first_name: string;
    last_name: string;
    full_name: string;
    email: string;
    timezone?: string;
    password?: string;
    is_email_verified?: boolean;
    has_pending_email_change?: boolean;
    is_impersonating?: boolean;
    impersonator_id?: IdParam;
    enforce_email_confirmation_during_registration?: boolean;
    pending_email?: string;
    last_login_at?: string;
    status?: 'ACTIVE' | 'INACTIVE' | 'INVITED';
    role?: 'ADMIN' | 'ORGANIZER' | 'SUPERADMIN';
    is_account_owner?: boolean;
    locale?: SupportedLocales;
    marketing_opted_in_at?: string | null;
}

export interface Account {
    id?: IdParam;
    name: string;
    email: string;
    timezone?: string;
    currency_code?: string;
    password?: string;
    stripe_connect_setup_complete?: boolean;
    stripe_account_id?: string;
    is_account_email_confirmed?: boolean;
    is_saas_mode_enabled?: boolean;
    configuration?: AccountConfiguration;
    requires_manual_verification?: boolean;
    stripe_platform: string;
    stripe_hi_events_primary_platform?: string;
}

export interface AccountConfiguration {
    id: IdParam;
    name: string;
    application_fees: {
        percentage: number;
        fixed: number;
    },
    is_system_default: boolean;
}

export interface StripeConnectDetails {
    account: Account;
    stripe_account_id: string;
    is_connect_setup_complete: boolean;
    connect_url: string | null;
}

export interface StripeConnectAccount {
    stripe_account_id: string;
    connect_url: string | null;
    is_setup_complete: boolean;
    platform: string | null;
    account_type: string | null;
    is_primary: boolean;
    country?: string;
}

export interface StripeConnectAccountsResponse {
    account: {
        id: IdParam;
        stripe_platform: string | null;
    };
    stripe_connect_accounts: StripeConnectAccount[];
    primary_stripe_account_id: string | null;
    has_completed_setup: boolean;
}

export interface LoginData {
    email: string;
    password: string;
}

export interface Image {
    id: IdParam;
    file_name: string;
    url: string;
    size: number;
    mime_type: string;
    type: ImageType;
    width?: number | null;
    height?: number | null;
    avg_colour?: string | null;
    lqip_base64?: string | null;
}

export type ImageType = 'EVENT_COVER' | 'EDITOR_IMAGE' | 'ORGANIZER_LOGO' | 'ORGANIZER_COVER' | 'ORGANIZER_IMAGE' | 'TICKET_LOGO';

export type PaymentProvider = 'STRIPE' | 'OFFLINE';

export type AttendeeDetailsCollectionMethod = 'PER_TICKET' | 'PER_ORDER';

export interface EventSettings {
    event_id?: IdParam;
    id?: IdParam;
    continue_button_text: string;
    email_footer_message: string;
    pre_checkout_message: string;
    product_page_message: string;
    post_checkout_message: string;
    support_email?: string;
    order_timeout_in_minutes?: number;
    homepage_background_color: string;
    homepage_primary_color: string;
    homepage_primary_text_color: string;
    homepage_secondary_color: string;
    homepage_secondary_text_color: string;
    homepage_body_background_color: string;
    homepage_background_type: 'COLOR' | 'MIRROR_COVER_IMAGE';
    location_details?: VenueAddress;
    is_online_event?: boolean;
    online_event_connection_details?: string;
    maps_url?: string;
    seo_title?: string;
    seo_description?: string;
    seo_keywords?: string;
    allow_search_engine_indexing?: boolean;
    price_display_mode?: 'INCLUSIVE' | 'EXCLUSIVE';
    hide_getting_started_page: boolean;
    attendee_details_collection_method?: AttendeeDetailsCollectionMethod;

    // Payment settings
    offline_payment_instructions: string;
    payment_providers: PaymentProvider[];
    allow_orders_awaiting_offline_payment_to_check_in: boolean;

    // Invoice settings
    enable_invoicing: boolean;
    invoice_label?: string;
    invoice_prefix?: string;
    invoice_start_number?: number;
    require_billing_address: boolean;
    organization_name?: string;
    organization_address?: string;
    invoice_tax_details?: string;
    invoice_notes?: string;
    invoice_payment_terms_days?: number;
    // Ticket design settings
    ticket_design_settings?: {
        accent_color?: string;
        logo_image_id?: IdParam;
        footer_text?: string;
        layout_type?: 'default' | 'modern';
        enabled?: boolean;
    };

    // Marketing settings
    show_marketing_opt_in?: boolean;

    // Platform fee settings
    pass_platform_fee_to_buyer?: boolean;

    // Self-service settings
    allow_attendee_self_edit?: boolean;

    // Simplified homepage theme settings (new 2-color + mode system)
    homepage_theme_settings?: HomepageThemeSettings;
}

export interface VenueAddress {
    venue_name?: string;
    address_line_1?: string;
    address_line_2?: string;
    city?: string;
    state_or_region?: string;
    zip_or_postal_code?: string;
    country?: string;
}

export interface EventBase {
    title: string;
    description?: string;
    category?: string;
    start_date: string;
    end_date?: string;
}

export interface EventDuplicatePayload extends EventBase {
    duplicate_products: boolean;
    duplicate_questions: boolean;
    duplicate_settings: boolean;
    duplicate_promo_codes: boolean;
    duplicate_capacity_assignments: boolean;
    duplicate_check_in_lists: boolean;
    duplicate_event_cover_image: boolean;
    duplicate_ticket_logo: boolean;
    duplicate_webhooks: boolean;
    duplicate_affiliates: boolean;
}

export enum EventStatus {
    DRAFT = 'DRAFT',
    LIVE = 'LIVE',
    PAUSED = 'PAUSED',
    ARCHIVED = 'ARCHIVED'
}

export enum OrganizerStatus {
    DRAFT = 'DRAFT',
    LIVE = 'LIVE',
    ARCHIVED = 'ARCHIVED'
}

export enum EventLifecycleStatus {
    ONGOING = 'ONGOING',
    UPCOMING = 'UPCOMING',
    ENDED = 'ENDED'
}

export interface Event extends EventBase {
    id?: IdParam;
    slug: string;
    status?: EventStatus;
    description_preview?: string;
    lifecycle_status?: EventLifecycleStatus;
    settings?: EventSettings;
    products?: Product[];
    product_categories?: ProductCategory[];
    images?: Image[];
    organizer?: Organizer;
    currency: string;
    timezone: string;
    organizer_id?: IdParam;
    location_details?: VenueAddress;
    statistics?: EventStatistics;
}

export interface EventStatistics {
    unique_views: number;
    total_views: number;
    sales_total_gross: number;
    total_tax: number;
    sales_total_before_additions: number;
    total_fee: number;
    products_sold: number;
    attendees_registered: number;
    total_refunded: number;
}

export interface EventDailyStats {
    date: string;
    total_fees: number;
    total_tax: number;
    total_sales_gross: number;
    products_sold: number;
    attendees_registered: number;
    total_refunded: number;
    orders_created: number;
}

export interface CheckInStats {
    total_checked_in_attendees: number;
    total_attendees: number;
}

export interface EventStats {
    daily_stats: EventDailyStats[];
    start_date: string;
    end_date: string;
    check_in_stats: CheckInStats;
    total_products_sold: number;
    total_attendees_registered: number;
    total_product_sold_percentage_change: number;
    total_orders: number;
    total_orders_percentage_change: number;
    total_gross_sales: number;
    total_gross_sales_percentage_change: number;
    total_tax: number;
    total_fees: number;
    total_views: number;
    total_refunded: number;
}

export interface OrganizerStats {
    total_products_sold: number;
    total_attendees_registered: number;
    total_orders: number;
    total_gross_sales: number;
    total_tax: number;
    total_fees: number;
    total_views: number;
    total_refunded: number;
    all_organizers_currencies: string[];
}

export interface Organizer {
    id?: IdParam;
    name: string;
    email: string;
    description?: string;
    website?: string;
    timezone?: string;
    currency?: string;
    slug?: string;
    phone?: string;
    images?: Image[];
    events?: Event[];
    settings?: OrganizerSettings;
    location_details?: VenueAddress;
    status?: 'LIVE' | 'DRAFT';
}

export interface OrganizerSettings {
    id: IdParam;
    organizer_id: IdParam;
    default_attendee_details_collection_method?: AttendeeDetailsCollectionMethod;
    default_show_marketing_opt_in?: boolean;
    default_pass_platform_fee_to_buyer?: boolean;
    default_allow_attendee_self_edit?: boolean;
    homepage_visibility: 'PUBLIC' | 'PRIVATE' | 'PASSWORD_PROTECTED';
    homepage_theme_settings: HomepageThemeSettings;
    website_url?: string;
    location_details?: VenueAddress;
    social_media_handles?: {
        facebook?: string;
        instagram?: string;
        twitter?: string;
        linkedin?: string;
        youtube?: string;
        tiktok?: string;
        snapchat?: string;
        twitch?: string;
        discord?: string;
        github?: string;
        reddit?: string;
        pinterest?: string;
        whatsapp?: string;
        telegram?: string;
        wechat?: string;
        weibo?: string;
    },
    seo_keywords?: string;
    seo_description?: string;
    seo_title?: string;
    allow_search_engine_indexing?: boolean;
}

export interface SortDirectionLabel {
    asc: string;
    desc: string;
}

export interface PaginationData {
    total: number;
    per_page: number;
    current_page: number;
    links: string[];
    last_page: number;
    from: number;
    to: number;
    path: number;
    allowed_sorts: Record<string, SortDirectionLabel>;
    default_sort: string;
    default_sort_direction: string;
}

export interface GenericDataResponse<T> {
    data: T;
    errors?: Record<string, string>;
}

export interface GenericPaginatedResponse<T> {
    data: T[];
    meta: PaginationData;
}

export enum ProductPriceType {
    Paid = 'PAID',
    Donation = 'DONATION',
    Free = 'FREE',
    Tiered = 'TIERED',
}

export enum ProductType {
    Ticket = 'TICKET',
    General = 'GENERAL',
}

export enum ProductStatus {
    Active = 'ACTIVE',
    Inactive = 'INACTIVE',
}

export interface ProductPrice {
    id?: number;
    label?: string;
    price: number;
    sale_start_date?: string | Date;
    sale_end_date?: string | Date;
    price_including_taxes_and_fees?: number;
    price_before_discount?: number;
    is_discounted?: boolean;
    tax_total?: number;
    fee_total?: number;
    is_available?: boolean;
    is_before_sale_start_date?: boolean;
    is_after_sale_end_date?: boolean;
    is_sold_out?: boolean;
    initial_quantity_available?: number;
    quantity_sold?: number;
    is_hidden?: boolean;
    quantity_remaining?: number;
}

export interface Product {
    id?: number;
    order?: number;
    title: string;
    event_id?: IdParam;
    // todo - rename to price_type
    type: ProductPriceType;
    product_type: ProductType;
    description?: string;
    price?: number;
    prices?: ProductPrice[];
    price_before_discount?: number;
    is_discounted?: boolean;
    initial_quantity_available?: number | undefined;
    quantity_sold?: number;
    sale_start_date?: string | Date;
    sale_end_date?: string | Date;
    max_per_order?: number;
    min_per_order?: number;
    hide_before_sale_start_date?: boolean;
    hide_after_sale_end_date?: boolean;
    hide_when_sold_out?: boolean;
    start_collapsed?: boolean;
    show_quantity_remaining?: boolean;
    quantity_available?: number;
    status?: ProductStatus;
    is_sold_out?: boolean;
    is_available?: boolean;
    is_hidden_without_promo_code?: boolean;
    is_before_sale_start_date?: boolean;
    is_after_sale_end_date?: boolean;
    taxes?: TaxAndFee[];
    tax_total?: number;
    service_fee_total?: number;
    price_including_taxes_and_fees?: number;
    tax_and_fee_ids?: IdParam[];
    taxes_and_fees?: TaxAndFee[];
    is_hidden?: boolean;
    product_category_id?: IdParam;
    is_highlighted?: boolean;
    highlight_message?: string;
}

export interface ProductCategory {
    id?: number;
    name: string;
    description?: string;
    products?: Product[];
    event_id?: number;
    is_hidden?: boolean;
    no_products_message?: string;
}

export interface Attendee {
    id?: number;
    product_id: number;
    product?: Product;
    product_price_id: number;
    order_id: number;
    status: 'ACTIVE' | 'CANCELLED' | 'AWAITING_PAYMENT';
    first_name: string;
    last_name: string;
    email: string;
    notes?: string;
    order?: Order;
    public_id: string;
    short_id: string;
    checked_in_at?: string;
    checked_out_by?: number;
    checked_in_by?: number;
    question_answers?: QuestionAnswer[];
    locale?: SupportedLocales;
    check_in?: AttendeeCheckIn; // Use in contexts where a single check is expected, like dealing with a check-in list
    check_ins?: AttendeeCheckIn[];
}

export type PublicCheckIn = Pick<AttendeeCheckIn, 'id' | 'order_id' | 'attendee_id' | 'check_in_list_id' | 'product_id' | 'event_id'>;

export interface AttendeeCheckIn {
    id: IdParam;
    attendee_id: IdParam;
    check_in_list_id: IdParam;
    product_id: IdParam;
    event_id: IdParam;
    short_id: IdParam;
    order_id: IdParam;
    created_at: string;
    check_in_list?: CheckInList;
}

export interface Address {
    address_line_1: string;
    address_line_2: string;
    city: string;
    state_or_region: string;
    country: string;
    zip_or_postal_code: string;
}

interface TaxOrFee {
    name: string;
    value: number;
}

interface TaxesAndFeesRollup {
    fees: TaxOrFee[];
    taxes: TaxOrFee[];
}

export interface Order {
    id: IdParam;
    short_id: string;
    event_id: IdParam;
    first_name: string;
    last_name: string;
    company_name: string;
    address: Address;
    payment_provider: PaymentProvider;
    notes?: string;
    email: string;
    reserved_until: string;
    total_before_additions: number;
    total_tax: number;
    total_fee: number;
    total_gross: number;
    total_gross_after_refund: number;
    total_refunded: number;
    is_expired: boolean;
    order_items?: OrderItem[];
    attendees?: Attendee[];
    created_at: string;
    currency: string;
    status: 'RESERVED' | 'CANCELLED' | 'COMPLETED' | 'AWAITING_OFFLINE_PAYMENT' | 'ABANDONED';
    refund_status?: 'REFUND_PENDING' | 'REFUND_FAILED' | 'REFUNDED' | 'PARTIALLY_REFUNDED';
    payment_status?: 'NO_PAYMENT_REQUIRED' | 'AWAITING_PAYMENT' | 'PAYMENT_FAILED' | 'PAYMENT_RECEIVED' | 'AWAITING_OFFLINE_PAYMENT';
    public_id: string;
    is_payment_required: boolean;
    is_manually_created: boolean;
    is_free_order: boolean;
    promo_code?: string;
    promo_code_id?: number;
    taxes_and_fees_rollup?: TaxesAndFeesRollup;
    question_answers?: QuestionAnswer[];
    event?: Event;
    latest_invoice?: Invoice;
    session_identifier?: string;
}

export interface Invoice {
    download_url: string;
    invoice_number: string;
    id: IdParam,
    order_id: IdParam,
    status: 'PAID' | 'UNPAID' | 'VOID',
}

export interface OrderItem {
    id: number;
    product_id: number;
    product_price_id: number;
    item_name: string;
    total_before_additions: number;
    total_before_discount?: number;
    price_before_discount?: number;
    price: number;
    quantity: number;
}

export interface StripePaymentIntent {
    status: string;
    paymentIntentId: string;
    amount: number;
}

export interface Question {
    id?: number;
    title: string;
    description?: string;
    required: boolean;
    type: string;
    options: string[];
    event_id?: number;
    products?: Product[];
    product_ids?: number[];
    belongs_to: string;
    is_hidden: boolean;
}

export interface CapacityAssignment {
    id?: number;
    event_id: number;
    name: string;
    used_capacity: number;
    status: 'ACTIVE' | 'INACTIVE';
    capacity: number | undefined;
    products: {
        id: number;
        title: string;
    }[];
}

export type CapacityAssignmentRequest = Omit<CapacityAssignment, 'id' | 'event_id' | 'used_capacity' | 'products'> & {
    product_ids: IdParam[];
};

export interface CheckInList {
    id?: number;
    short_id: string;
    name: string;
    description?: string | null;
    expires_at?: string;  // ISO 8601 string
    activates_at?: string;  // ISO 8601 string
    total_attendees: number;
    checked_in_attendees: number;
    is_expired: boolean;
    is_active: boolean;
    event_id: number;
    event?: Event;
    products: {
        id: number;
        title: string;
    }[];
}

export type CheckInListRequest =
    Omit<CheckInList, 'event_id' | 'short_id' | 'id' | 'products' | 'total_attendees' | 'checked_in_attendees' | 'is_expired' | 'is_active'>
    & {
    product_ids: IdParam[];
};

export interface QuestionRequestData {
    title: string;
    description?: string;
    required: boolean;
    is_hidden: boolean;
    type: string;
    options: string[];
    product_ids?: string[];
    belongs_to: string;
}

export interface Message {
    id?: IdParam;
    subject: string;
    message: string;
    message_preview: string;
    type: MessageType;
    is_test: boolean;
    order_id?: number;
    attendee_ids?: IdParam[];
    product_ids?: IdParam[];
    created_at?: string;
    updated_at?: string;
    sent_at?: string;
    sent_by_user?: User;
    status?: 'SENT' | 'PROCESSING' | 'FAILED';
}

export enum QuestionType {
    ADDRESS = 'ADDRESS',
    SINGLE_LINE_TEXT = 'SINGLE_LINE_TEXT',
    MULTI_LINE_TEXT = 'MULTI_LINE_TEXT',
    CHECKBOX = 'CHECKBOX',
    RADIO = 'RADIO',
    DROPDOWN = 'DROPDOWN',
    DATE = 'DATE',
}

export enum QuestionBelongsToType {
    PRODUCT = 'PRODUCT',
    ORDER = 'ORDER',
}

export enum QueryFilterOperator {
    Equals = 'eq',
    NotEquals = 'ne',
    GreaterThan = 'gt',
    GreaterThanOrEquals = 'gte',
    LessThan = 'lt',
    LessThanOrEquals = 'lte',
    Like = 'like',
    NotLike = 'not_like',
    In = 'in',
}

export type QueryFilterValue = string | number | boolean;

export type QueryFilterCondition = {
    operator: QueryFilterOperator;
    value: QueryFilterValue;
};

export type QueryFilterFields = {
    [key: string]: QueryFilterCondition | QueryFilterCondition[] | undefined;
}

export interface QueryFilters {
    pageNumber?: number;
    perPage?: number;
    query?: string;
    sortBy?: string;
    sortDirection?: string;
    filterFields?: QueryFilterFields;
    additionalParams?: Record<string, any>;
}

export interface GenericModalProps {
    onClose: () => void;
    isOpen?: boolean;
}

export interface MessageOrderRequest {
    subject: string;
    message: string;
    sendCopy: boolean;
}

export enum MessageType {
    IndividualAttendees = 'INDIVIDUAL_ATTENDEES',
    OrderOwner = 'ORDER_OWNER',
    TicketHolders = 'TICKET_HOLDERS',
    AllAttendees = 'ALL_ATTENDEES',
    OrderOwnersWithProduct = 'ORDER_OWNERS_WITH_PRODUCT',
}

export interface PromoCode {
    id?: number;
    code: string;
    discount?: number;
    applicable_product_ids?: number[] | string[];
    expiry_date?: string;
    event_id?: number;
    discount_type?: PromoCodeDiscountType | null;
    attendee_usage_count?: number;
    order_usage_count?: number;
    max_allowed_usages?: number | undefined;
}

export enum PromoCodeDiscountType {
    Percentage = 'PERCENTAGE',
    Fixed = 'FIXED',
    None = 'NONE',
}

export enum TaxAndFeeType {
    Tax = 'TAX',
    Fee = 'FEE',
}

export enum TaxAndFeeCalculationType {
    Percentage = 'PERCENTAGE',
    Fixed = 'FIXED'
}

export interface TaxAndFee {
    id?: number;
    name: string;
    rate: number | undefined;
    type: TaxAndFeeType;
    calculation_type: TaxAndFeeCalculationType;
    is_default: boolean;
    is_active: boolean;
    description?: string;
    account_id?: IdParam;
}

export interface InviteUserRequest {
    email: string;
    first_name: string;
    last_name: string;
    role: string;
}

export interface SortableItem {
    id: IdParam;
    order: number;
}

export interface QuestionAnswer {
    product_id?: number;
    product_title?: string;
    question_id: number;
    title: string;
    answer: string[] | string;
    text_answer: string;
    order_id: number;
    belongs_to: string;
    question_type: string;
    attendee_id?: number;
    attendee_public_id?: IdParam;
    first_name?: string;
    last_name?: string;
    question_answer_id?: IdParam;
    question_description?: string;
    question_required?: boolean;
    question_options?: string[];
}

export enum ReportTypes {
    ProductSales = 'product_sales',
    DailySales = 'daily_sales_report',
    PromoCodes = 'promo_codes_report',
}

export enum OrganizerReportTypes {
    RevenueSummary = 'revenue_summary',
    EventsPerformance = 'events_performance',
    TaxSummary = 'tax_summary',
    CheckInSummary = 'check_in_summary',
}

export interface Webhook {
    id: IdParam;
    event_id: IdParam;
    url: string;
    secret: string;
    status: 'ENABLED' | 'PAUSED';
    event?: Event;
    event_types?: string[];
    last_response_code?: number;
    last_response_body?: string;
    last_triggered_at?: string | Date;
    logs?: WebhookLog[];
}

export interface WebhookLog {
    id: IdParam;
    webhook_id: IdParam;
    payload?: string;
    response_code?: number; // 0 = no response
    response_body?: string;
    event_type: string;
    created_at: string;
}

// Email Template Types
export type EmailTemplateType = 'order_confirmation' | 'attendee_ticket';
export type EmailTemplateEngine = 'liquid' | 'blade';

export interface EmailTemplate {
    id: number;
    account_id: number;
    organizer_id?: number;
    event_id?: number;
    template_type: EmailTemplateType;
    subject: string;
    body: string;
    cta?: {
        label: string;
        url_token: string;
    };
    engine: EmailTemplateEngine;
    is_active: boolean;
    created_at: string;
    updated_at: string;
}

export interface EmailTemplateToken {
    token: string;
    description: string;
    example: string;
}

export interface CreateEmailTemplateRequest {
    template_type: EmailTemplateType;
    subject: string;
    body: string;
    cta?: {
        label: string;
        url_token: string;
    };
}

export interface UpdateEmailTemplateRequest {
    subject: string;
    body: string;
    cta?: {
        label: string;
        url_token: string;
    };
    is_active?: boolean;
}

export interface PreviewEmailTemplateRequest {
    template_type: EmailTemplateType;
    subject: string;
    body: string;
    cta?: {
        label: string;
        url_token: string;
    };
}

export interface EmailTemplatePreview {
    subject: string;
    body: string;
    context: Record<string, any>;
}

export interface DefaultEmailTemplate {
    subject: string;
    body: string;
    cta?: {
        label: string;
        url_token: string;
    };
}
