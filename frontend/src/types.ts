/**
 * @todo - This file needs to be organized better. Split into multiple files.
 */

export type IdParam = string | undefined | number;

export interface AcceptInvitationRequest {
    first_name: string;
    last_name: string;
    email: string;
    password: string;
    password_confirmation: string;
}

export interface RegisterAccountRequest {
    first_name: string;
    last_name: string;
    email: string;
    password: string;
    password_confirmation: string;
}

export interface ResetPasswordRequest {
    password: string;
    password_confirmation: string;
    current_password: string;
}

export interface LoginResponse {
    token?: string
    token_type: string
    expires_in: number
    user: User,
    accounts: Account[],
}

export interface User {
    id?: IdParam;
    account_id?: IdParam;
    first_name: string;
    last_name: string;
    email: string;
    timezone?: string;
    password?: string;
    has_pending_email_change?: boolean;
    pending_email?: string;
    last_login_at?: string;
    status?: 'ACTIVE' | 'INACTIVE' | 'INVITED';
    role?: 'ADMIN' | 'ORGANIZER';
    is_account_owner?: boolean;
}

export interface Account {
    id?: IdParam;
    name: string;
    email: string;
    timezone?: string;
    currency_code?: string;
    password?: string;
    stripe_connect_setup_complete?: boolean;
    is_account_email_confirmed?: boolean;
}

export interface StripeConnectDetails {
    account: Account,
    stripe_account_id: string;
    is_connect_setup_complete: boolean;
    connect_url: string;
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
    type: string;
}

export interface EventSettings {
    event_id?: IdParam;
    id?: IdParam;
    continue_button_text: string;
    email_footer_message: string;
    pre_checkout_message: string;
    ticket_page_message: string;
    post_checkout_message: string;
    reply_to_email?: string;
    order_timeout_in_minutes?: number;
    homepage_background_color: string;
    homepage_primary_color: string;
    homepage_primary_text_color: string;
    homepage_secondary_color: string;
    homepage_secondary_text_color: string;
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

export interface Event {
    id?: IdParam;
    title: string;
    slug: string;
    status?: string;
    start_date: string;
    end_date?: string;
    description?: string;
    description_preview?: string;

    settings?: EventSettings;
    tickets?: Ticket[],
    images?: Image[],
    organizer?: Organizer,
    currency: string,
    timezone: string,
    organizer_id?: IdParam,

    location_details?: {
        venue_name?: string,
        address_line_1?: string,
        address_line_2?: string,
        city?: string,
        state_or_region?: string,
        zip_or_postal_code?: string,
        country?: string,
    },
}

export interface EventDailyStats {
    date: string;
    total_fees: number;
    total_tax: number;
    total_sales_gross: number;
    tickets_sold: number;
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
    total_tickets_sold: number;
    total_ticket_sold_percentage_change: number;
    total_orders: number;
    total_orders_percentage_change: number;
    total_gross_sales: number;
    total_gross_sales_percentage_change: number;
    total_tax: number;
    total_fees: number;
    total_views: number;
}

export interface Organizer {
    id?: number;
    name: string;
    email: string;
    description?: string;
    website?: string;
    phone?: string;
    images?: Image[];
    events?: Event[];
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
    data: T
}

export interface GenericPaginatedResponse<T> {
    data: T[];
    meta: PaginationData;
}

export enum TicketType {
    Paid = 'PAID',
    Donation = 'DONATION',
    Free = 'FREE',
    Tiered = 'TIERED',
}

export enum TicketStatus {
    Active = 'ACTIVE',
    Inactive = 'INACTIVE',
}

export interface TicketPrice {
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
}

export interface Ticket {
    id?: number;
    order?: number;
    title: string;
    event_id?: IdParam,
    type: TicketType,
    description?: string;
    price?: number;
    prices?: TicketPrice[];
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
    show_quantity_remaining?: boolean;
    status?: TicketStatus;
    is_sold_out?: boolean;
    is_available?: boolean;
    is_hidden_without_promo_code?: boolean,
    is_before_sale_start_date?: boolean;
    is_after_sale_end_date?: boolean;
    taxes?: TaxAndFee[];
    tax_total?: number;
    service_fee_total?: number;
    price_including_taxes_and_fees?: number;
    tax_and_fee_ids?: IdParam[];
    taxes_and_fees?: TaxAndFee[];
    is_hidden?: boolean;
}

export interface Attendee {
    id?: number;
    ticket_id: number;
    ticket?: Ticket;
    ticket_price_id: number;
    order_id: number;
    status: string;
    first_name: string;
    last_name: string;
    email: string;
    order?: Order;
    public_id: string;
    short_id: string;
    checked_in_at?: string;
    checked_out_by?: number;
    checked_in_by?: number;
    question_answers?: QuestionAnswer[];
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
    id: number;
    short_id: string;
    first_name: string;
    last_name: string;
    company_name: string;
    address: Address;
    email: string;
    reserved_until: string;
    total_before_additions: number;
    total_tax: number;
    total_fee: number;
    total_gross: number;
    total_gross_after_refund: number;
    total_refunded: number;
    is_expired: boolean,
    order_items?: OrderItem[];
    attendees?: Attendee[];
    created_at: string;
    currency: string;
    status: string;
    refund_status?: string;
    payment_status?: string;
    public_id: string;
    is_payment_required: boolean;
    is_manually_created: boolean;
    is_free_order: boolean;
    promo_code?: string;
    promo_code_id?: number;
    taxes_and_fees_rollup?: TaxesAndFeesRollup;
    question_answers?: QuestionAnswer[];
}

export interface OrderItem {
    id: number;
    ticket_id: number;
    ticket_price_id: number;
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
    id?: number,
    title: string,
    required: boolean,
    type: string,
    options: string[],
    event_id?: number,
    tickets?: Ticket[], // remove
    ticket_ids?: number[],
    belongs_to: string;
    is_hidden: boolean;
}

export interface QuestionRequestData {
    title: string,
    required: boolean,
    is_hidden: boolean,
    type: string,
    options: string[],
    ticket_ids?: string[],
    belongs_to: string;
}

export interface Message {
    id?: IdParam;
    subject: string;
    message: string;
    message_preview: string;
    type: 'TICKET' | 'EVENT';
    is_test: boolean;
    order_id?: number;
    attendee_ids?: IdParam[];
    ticket_ids?: IdParam[];
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
}

export enum QuestionBelongsToType {
    TICKET = 'TICKET',
    ORDER = 'ORDER',
}

export type QueryFilterFields = {
    [key: string]: string | string[] | number | number[] | boolean | boolean[] | undefined
}

export interface QueryFilters {
    pageNumber: number;
    perPage?: number;
    query?: string;
    sortBy?: string;
    sortDirection?: string;
    filterFields?: QueryFilterFields;
}

export interface GenericModalProps {
    onClose: () => void,
    isOpen?: boolean,
}

export interface MessageOrderRequest {
    subject: string;
    message: string;
    sendCopy: boolean;
}

export enum MessageType {
    Attendee = 'ATTENDEE',
    Order = 'ORDER',
    Ticket = 'TICKET',
    Event = 'EVENT',
}

export interface PromoCode {
    id?: number;
    code: string;
    discount?: number;
    applicable_ticket_ids?: number[];
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
    rate: number | undefined
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
    question_id: number;
    title: string;
    answer: string[] | string;
    text_answer: string;
    order_id: number;
    belongs_to: string; // Assuming this is a string, adjust based on actual data type
    question_type: string;

    // Optional properties, assuming they can be null based on your PHP code
    attendee_id?: number;
    first_name?: string;
    last_name?: string;
}
