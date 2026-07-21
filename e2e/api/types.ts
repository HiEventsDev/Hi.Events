export type EventType = 'SINGLE' | 'RECURRING';
export type ProductKind = 'TICKET' | 'GENERAL';
export type ProductPriceType = 'FREE' | 'PAID' | 'DONATION' | 'TIERED';
export type EventStatus = 'DRAFT' | 'LIVE';

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

export interface ProductRecord {
  id: number;
  title: string;
  type: ProductPriceType;
  product_type: ProductKind;
  price: number;
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

export interface CreateProductPayload {
  title: string;
  product_type: ProductKind;
  type: ProductPriceType;
  product_category_id: number;
  prices: { price: number }[];
}
