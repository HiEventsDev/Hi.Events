export type EventType = 'SINGLE' | 'RECURRING';
export type ProductKind = 'TICKET' | 'GENERAL';
export type ProductPriceType = 'FREE' | 'PAID' | 'DONATION' | 'TIERED';
export type EventStatus = 'DRAFT' | 'LIVE' | 'ARCHIVED';
export type QuestionType =
  | 'ADDRESS'
  | 'PHONE'
  | 'SINGLE_LINE_TEXT'
  | 'MULTI_LINE_TEXT'
  | 'CHECKBOX'
  | 'RADIO'
  | 'DROPDOWN'
  | 'MULTI_SELECT_DROPDOWN'
  | 'DATE';

export interface RegisterPayload {
  first_name: string;
  last_name?: string;
  email: string;
  password: string;
  password_confirmation: string;
  timezone?: string;
  currency_code?: string;
  locale?: string;
}

export interface Me {
  id: number;
  email: string;
  is_email_verified: boolean;
}

export interface Organizer {
  id: number;
  name: string;
  slug: string;
  email?: string;
}

export interface EventRecord {
  id: number;
  title: string;
  slug: string;
  status: EventStatus;
}

export interface ProductCategory {
  id: number;
  name: string;
}

export interface ProductPrice {
  id: number;
  price: number;
  label?: string | null;
  quantity_sold?: number;
}

export interface ProductRecord {
  id: number;
  title: string;
  type: ProductPriceType;
  product_type: ProductKind;
  price: number;
  prices?: ProductPrice[];
}

export interface CreateEventPayload {
  title: string;
  type: EventType;
  organizer_id: number;
  start_date: string;
  end_date?: string;
  category: string;
  currency?: string;
  timezone?: string;
}

export interface CreateProductPricePayload {
  price: number;
  label?: string;
  initial_quantity_available?: number;
}

export interface CreateProductPayload {
  title: string;
  description?: string;
  product_type: ProductKind;
  type: ProductPriceType;
  product_category_id: number;
  prices: CreateProductPricePayload[];
  tax_and_fee_ids?: number[];
  addon_product_ids?: number[];
  is_addon_only?: boolean;
  max_per_order?: number;
  min_per_order?: number;
  is_hidden?: boolean;
  is_hidden_without_promo_code?: boolean;
  sale_start_date?: string;
  sale_end_date?: string;
}

export interface CreateProductCategoryPayload {
  name: string;
  description?: string;
  is_hidden: boolean;
  no_products_message?: string;
}

export interface PublicOrderAttendee {
  short_id: string;
  public_id: string;
  first_name: string;
  last_name: string;
}

export interface PublicOrder {
  short_id: string;
  session_identifier?: string;
  status: string;
  payment_status?: string | null;
  total_gross: number;
  attendees?: PublicOrderAttendee[];
}

export interface OrderRecord {
  id: number;
  short_id: string;
  status: string;
  payment_status?: string | null;
  email: string;
  total_gross: number;
}

export interface AttendeeRecord {
  id: number;
  short_id: string;
  public_id: string;
  first_name: string;
  last_name: string;
  email: string;
  status: string;
}

export interface PromoCode {
  id: number;
  code: string;
}

export interface CheckInList {
  id: number;
  short_id: string;
  name: string;
}

export interface Occurrence {
  id: number;
  start_date: string;
  end_date: string | null;
  status: string;
}

export interface CreateOrganizerLocationPayload {
  name?: string;
  structured_address: {
    venue_name?: string;
    address_line_1?: string;
    city?: string;
    state_or_region?: string;
    zip_or_postal_code?: string;
    country?: string;
  };
}

export interface UpdateOccurrencePayload {
  start_date: string;
  end_date?: string | null;
  label?: string;
  capacity?: number | null;
  event_location?:
    | { type: 'IN_PERSON'; location_id: number }
    | { type: 'ONLINE'; online_event_connection_details: string };
}

export interface OccurrencePriceOverridePayload {
  product_price_id: number;
  price: number;
}

export interface QuestionRecord {
  id: number;
  title: string;
}

export interface TaxOrFee {
  id: number;
  name: string;
}

export interface CapacityAssignment {
  id: number;
  name: string;
}

export interface Affiliate {
  id: number;
  code: string;
}

export interface Webhook {
  id: number;
  url: string;
}

export interface EmailTemplate {
  id: number;
  subject: string;
}

export type AttendeeDetailsCollection = 'PER_TICKET' | 'PER_ORDER';

export interface EventSettings {
  payment_providers?: string[];
  offline_payment_instructions?: string | null;
  waitlist_enabled?: boolean;
  attendee_details_collection_method?: AttendeeDetailsCollection;
  [key: string]: unknown;
}

export interface CreatePromoCodePayload {
  code: string;
  discount_type: 'NONE' | 'FIXED' | 'PERCENTAGE';
  discount?: number;
  discount_applies_to?: 'ORDER' | 'EACH_PRODUCT';
  applicable_product_ids?: number[];
  expiry_date?: string;
  max_allowed_usages?: number;
}

export interface CreateQuestionPayload {
  title: string;
  type: QuestionType;
  belongs_to: 'PRODUCT' | 'ORDER';
  product_ids?: number[];
  options?: string[];
  required: boolean;
  is_hidden: boolean;
  description?: string;
}

export interface CreateTaxOrFeePayload {
  name: string;
  calculation_type: 'PERCENTAGE' | 'FIXED';
  type: 'TAX' | 'FEE';
  rate: number;
  is_active: boolean;
  is_default: boolean;
  description?: string;
}

export interface CreateCheckInListPayload {
  name: string;
  description?: string;
  expires_at?: string;
  activates_at?: string;
  product_ids?: number[];
  event_occurrence_id?: number;
}

export interface CreateAttendeePayload {
  product_id: number;
  product_price_id?: number;
  event_occurrence_id?: number;
  email: string;
  first_name: string;
  last_name?: string;
  amount_paid: number;
  send_confirmation_email: boolean;
  locale: string;
}

export interface CreateCapacityAssignmentPayload {
  name: string;
  capacity?: number;
  status: 'ACTIVE' | 'INACTIVE';
  product_ids: number[];
}

export interface CreateAffiliatePayload {
  name: string;
  code: string;
  email?: string;
  status?: 'ACTIVE' | 'INACTIVE';
}

export interface CreateWebhookPayload {
  url: string;
  event_types: string[];
  status: 'ENABLED' | 'PAUSED';
}

export interface CreateEmailTemplatePayload {
  template_type: 'order_confirmation' | 'attendee_ticket' | 'occurrence_cancellation';
  subject: string;
  body: string;
  ctaLabel: string;
  isActive?: boolean;
}

export interface InviteUserPayload {
  first_name: string;
  last_name?: string;
  email: string;
  role: 'ADMIN' | 'ORGANIZER';
}

export interface RecurrenceRule {
  frequency: 'daily' | 'weekly' | 'monthly' | 'yearly';
  interval?: number;
  range: { type: 'count' | 'until'; count?: number; until?: string; start?: string };
  days_of_week?: string[];
  times_of_day?: (string | { time: string; label?: string; duration_minutes?: number })[];
  duration_minutes?: number;
  default_capacity?: number;
}
