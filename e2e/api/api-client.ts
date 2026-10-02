import type { APIRequestContext, APIResponse } from '@playwright/test';
import type {
  Affiliate,
  AttendeeRecord,
  BoxOffice,
  CapacityAssignment,
  CheckInList,
  CreateAffiliatePayload,
  CreateAttendeePayload,
  CreateBoxOfficePayload,
  CreateCapacityAssignmentPayload,
  CreateCheckInListPayload,
  CreateEmailTemplatePayload,
  CreateEventPayload,
  CreateOrganizerLocationPayload,
  CreateProductCategoryPayload,
  CreatePromoCodePayload,
  CreateProductPayload,
  CreateQuestionPayload,
  CreateTaxOrFeePayload,
  CreateWebhookPayload,
  EmailTemplate,
  EventImageType,
  EventRecord,
  EventSettings,
  EventStatus,
  ImageRecord,
  InviteUserPayload,
  Me,
  Occurrence,
  OccurrencePriceOverridePayload,
  OrderRecord,
  Organizer,
  ProductCategory,
  ProductRecord,
  PromoCode,
  QuestionRecord,
  RecurrenceRule,
  RegisterPayload,
  TaxOrFee,
  UpdateOccurrencePayload,
  Webhook,
} from './types';

const jsonHeaders = { 'Content-Type': 'application/json', Accept: 'application/json' };

const unwrap = async <T>(promise: Promise<APIResponse>): Promise<T> => {
  const response = await promise;
  if (!response.ok()) {
    throw new Error(`API ${response.url()} → ${response.status()}: ${await response.text()}`);
  }
  const body = (await response.json()) as { data: T };
  return body.data;
};

export type SeatMapLayout = { schema: number; bands: unknown[]; areas: { id: string; name: string; elements: Record<string, unknown>[] }[] } & Record<string, unknown>;

export interface OccupiedSeat {
  seat_uid: string;
  seat_label: string;
  is_zone: boolean;
  band_key: string;
  status: 'HELD' | 'SOLD' | 'BLOCKED';
  block_reason: string | null;
  attendee_public_id: string | null;
  attendee_name: string | null;
}

const check = async (promise: Promise<APIResponse>): Promise<void> => {
  const response = await promise;
  if (!response.ok()) {
    throw new Error(`API ${response.url()} → ${response.status()}: ${await response.text()}`);
  }
};

export async function registerAccount(request: APIRequestContext, payload: RegisterPayload): Promise<void> {
  const response = await request.post('auth/register', { headers: jsonHeaders, data: payload });
  if (!response.ok()) {
    throw new Error(`register → ${response.status()}: ${await response.text()}`);
  }
}

export interface LoginResult {
  token: string;
  user: Me;
}

export async function login(request: APIRequestContext, email: string, password: string): Promise<LoginResult> {
  const response = await request.post('auth/login', { headers: jsonHeaders, data: { email, password } });
  if (!response.ok()) {
    throw new Error(`login → ${response.status()}: ${await response.text()}`);
  }
  const body = (await response.json()) as { token?: string; user?: Me };
  if (!body.token || !body.user) {
    throw new Error('login succeeded but response was missing token/user');
  }
  return { token: body.token, user: body.user };
}

export async function confirmEmailWithCode(
  request: APIRequestContext,
  token: string,
  userId: number,
  code: string,
): Promise<void> {
  const response = await request.post(`users/${userId}/confirm-email-with-code`, {
    headers: { ...jsonHeaders, Authorization: `Bearer ${token}` },
    data: { code },
  });
  if (!response.ok()) {
    throw new Error(`confirm-email-with-code → ${response.status()}: ${await response.text()}`);
  }
}

interface CurrentUser {
  id: number;
  feature_flags: Record<string, boolean>;
  licence: { status: string; invalid_reason: string | null; features_in_use: string[] };
}

export class ApiClient {
  constructor(private readonly request: APIRequestContext) {}

  getAccount(): Promise<{ id: number; name: string }> {
    return unwrap<{ id: number; name: string }>(this.request.get('accounts', { headers: jsonHeaders }));
  }

  getMe(): Promise<CurrentUser> {
    return unwrap<CurrentUser>(this.request.get('users/me', { headers: jsonHeaders }));
  }

  requestAccountDeletion(confirmation: string): Promise<{ id: number; status: string }> {
    return unwrap<{ id: number; status: string }>(
      this.request.post('accounts/deletion-request', { headers: jsonHeaders, data: { confirmation } }),
    );
  }

  createOrganizer(name: string, opts: { email?: string; currency?: string; timezone?: string } = {}): Promise<Organizer> {
    return unwrap<Organizer>(
      this.request.post('organizers', {
        headers: jsonHeaders,
        data: {
          name,
          email: opts.email ?? 'organizer@hievents.test',
          currency: opts.currency ?? 'USD',
          timezone: opts.timezone ?? 'UTC',
        },
      }),
    );
  }

  updateOrganizerStatus(organizerId: number, status: 'LIVE' | 'DRAFT'): Promise<void> {
    return check(this.request.put(`organizers/${organizerId}/status`, { headers: jsonHeaders, data: { status } }));
  }

  createEvent(payload: CreateEventPayload): Promise<EventRecord> {
    return unwrap<EventRecord>(this.request.post('events', { headers: jsonHeaders, data: payload }));
  }

  uploadEventImage(
    eventId: number,
    image: { name: string; mimeType: string; buffer: Buffer },
    type: EventImageType = 'EVENT_COVER',
  ): Promise<ImageRecord> {
    return unwrap<ImageRecord>(this.request.post(`events/${eventId}/images`, { multipart: { image, type } }));
  }

  listProductCategories(eventId: number): Promise<ProductCategory[]> {
    return unwrap<ProductCategory[]>(this.request.get(`events/${eventId}/product-categories`, { headers: jsonHeaders }));
  }

    createProductCategory(eventId: number, payload: CreateProductCategoryPayload): Promise<ProductCategory> {
    return unwrap<ProductCategory>(
      this.request.post(`events/${eventId}/product-categories`, { headers: jsonHeaders, data: payload }),
    );
  }

  createProduct(eventId: number, payload: CreateProductPayload): Promise<ProductRecord> {
    return unwrap<ProductRecord>(
      this.request.post(`events/${eventId}/products`, { headers: jsonHeaders, data: payload }),
    );
  }

  getProduct(eventId: number, productId: number): Promise<ProductRecord> {
    return unwrap<ProductRecord>(
      this.request.get(`events/${eventId}/products/${productId}`, { headers: jsonHeaders }),
    );
  }

  listProducts(eventId: number): Promise<ProductRecord[]> {
    return unwrap<ProductRecord[]>(this.request.get(`events/${eventId}/products`, { headers: jsonHeaders }));
  }

  setEventStatus(eventId: number, status: EventStatus): Promise<void> {
    return check(this.request.put(`events/${eventId}/status`, { headers: jsonHeaders, data: { status } }));
  }

  publishEvent(eventId: number): Promise<void> {
    return this.setEventStatus(eventId, 'LIVE');
  }

  getEvent(eventId: number): Promise<EventRecord> {
    return unwrap<EventRecord>(this.request.get(`events/${eventId}`, { headers: jsonHeaders }));
  }

  getEventSettings(eventId: number): Promise<EventSettings> {
    return unwrap<EventSettings>(this.request.get(`events/${eventId}/settings`, { headers: jsonHeaders }));
  }

  updateEventSettings(eventId: number, settings: Partial<EventSettings>): Promise<void> {
    return check(this.request.patch(`events/${eventId}/settings`, { headers: jsonHeaders, data: settings }));
  }

  createPromoCode(eventId: number, payload: CreatePromoCodePayload): Promise<PromoCode> {
    return unwrap<PromoCode>(
      this.request.post(`events/${eventId}/promo-codes`, {
        headers: jsonHeaders,
        data: { applicable_product_ids: [], ...payload },
      }),
    );
  }

  createQuestion(eventId: number, payload: CreateQuestionPayload): Promise<QuestionRecord> {
    return unwrap<QuestionRecord>(
      this.request.post(`events/${eventId}/questions`, { headers: jsonHeaders, data: payload }),
    );
  }

  createTaxOrFee(accountId: number, payload: CreateTaxOrFeePayload): Promise<TaxOrFee> {
    return unwrap<TaxOrFee>(
      this.request.post(`accounts/${accountId}/taxes-and-fees`, {
        headers: jsonHeaders,
        data: { description: null, ...payload },
      }),
    );
  }

  createCheckInList(eventId: number, payload: CreateCheckInListPayload): Promise<CheckInList> {
    return unwrap<CheckInList>(
      this.request.post(`events/${eventId}/check-in-lists`, { headers: jsonHeaders, data: payload }),
    );
  }

  listBoxOffices(eventId: number): Promise<BoxOffice[]> {
    return unwrap<BoxOffice[]>(this.request.get(`events/${eventId}/box-offices?per_page=100`));
  }

  createBoxOffice(eventId: number, payload: CreateBoxOfficePayload): Promise<BoxOffice & { pin: string }> {
    return unwrap<BoxOffice & { pin: string }>(
      this.request.post(`events/${eventId}/box-offices`, { headers: jsonHeaders, data: payload }),
    );
  }

  updateBoxOffice(eventId: number, boxOfficeId: number, payload: CreateBoxOfficePayload): Promise<BoxOffice> {
    return unwrap<BoxOffice>(
      this.request.put(`events/${eventId}/box-offices/${boxOfficeId}`, { headers: jsonHeaders, data: payload }),
    );
  }

  resetBoxOfficePin(eventId: number, boxOfficeId: number): Promise<BoxOffice & { pin: string }> {
    return unwrap<BoxOffice & { pin: string }>(
      this.request.post(`events/${eventId}/box-offices/${boxOfficeId}/reset-pin`, { headers: jsonHeaders }),
    );
  }

  registerTerminalReader(organizerId: number, payload: { registration_code: string; label: string }): Promise<{ id: number; label: string }> {
    return unwrap<{ id: number; label: string }>(
      this.request.post(`organizers/${organizerId}/stripe/terminal/readers`, { headers: jsonHeaders, data: payload }),
    );
  }

  createAttendee(eventId: number, payload: CreateAttendeePayload): Promise<AttendeeRecord> {
    return unwrap<AttendeeRecord>(
      this.request.post(`events/${eventId}/attendees`, { headers: jsonHeaders, data: payload }),
    );
  }

  createCapacityAssignment(eventId: number, payload: CreateCapacityAssignmentPayload): Promise<CapacityAssignment> {
    return unwrap<CapacityAssignment>(
      this.request.post(`events/${eventId}/capacity-assignments`, { headers: jsonHeaders, data: payload }),
    );
  }

  createAffiliate(eventId: number, payload: CreateAffiliatePayload): Promise<Affiliate> {
    return unwrap<Affiliate>(
      this.request.post(`events/${eventId}/affiliates`, { headers: jsonHeaders, data: payload }),
    );
  }

  createWebhook(eventId: number, payload: CreateWebhookPayload): Promise<Webhook> {
    return unwrap<Webhook>(
      this.request.post(`events/${eventId}/webhooks`, { headers: jsonHeaders, data: payload }),
    );
  }

  createEventEmailTemplate(eventId: number, payload: CreateEmailTemplatePayload): Promise<EmailTemplate> {
    return unwrap<EmailTemplate>(
      this.request.post(`events/${eventId}/email-templates`, { headers: jsonHeaders, data: payload }),
    );
  }

  inviteUser(payload: InviteUserPayload): Promise<{ id: number }> {
    return unwrap<{ id: number }>(this.request.post('users', { headers: jsonHeaders, data: payload }));
  }

  listOrders(eventId: number): Promise<OrderRecord[]> {
    return unwrap<OrderRecord[]>(this.request.get(`events/${eventId}/orders`, { headers: jsonHeaders }));
  }

  createSeatMap(organizerId: number, name: string, layout: unknown): Promise<{ id: number }> {
    return unwrap<{ id: number }>(
      this.request.post(`organizers/${organizerId}/seat-maps`, { headers: jsonHeaders, data: { name, layout } }),
    );
  }

  getSeatMap(organizerId: number, seatMapId: number): Promise<{ layout: { areas: { elements: { id: string; aisles?: number[]; seats?: { acc: boolean }[] }[] }[] } }> {
    return unwrap(this.request.get(`organizers/${organizerId}/seat-maps/${seatMapId}`, { headers: jsonHeaders }));
  }

  attachSeatMap(eventId: number, seatMapId: number): Promise<void> {
    return check(this.request.post(`events/${eventId}/seat-map`, { headers: jsonHeaders, data: { seat_map_id: seatMapId } }));
  }

  linkSeatMapBands(eventId: number, bandProducts: { band_key: string; products: { product_id: number; price_adjustment?: number }[] }[]): Promise<void> {
    return check(
      this.request.put(`events/${eventId}/seat-map/band-products`, {
        headers: jsonHeaders,
        data: { band_products: bandProducts },
      }),
    );
  }

  updateSeatMapRules(eventId: number, rules: { prevent_orphan_seats: boolean; max_seats_per_order: number | null; allow_seat_change: boolean }): Promise<void> {
    return check(this.request.put(`events/${eventId}/seat-map/rules`, { headers: jsonHeaders, data: rules }));
  }

  blockSeats(eventId: number, occurrenceId: number, seatUids: string[], reason: string | null = null): Promise<void> {
    return check(
      this.request.post(`events/${eventId}/seat-blocks`, {
        headers: jsonHeaders,
        data: { event_occurrence_ids: [occurrenceId], seat_uids: seatUids, reason },
      }),
    );
  }

  duplicateEvent(eventId: number, title: string, startDate: string): Promise<EventRecord> {
    return unwrap<EventRecord>(
      this.request.post(`events/${eventId}/duplicate`, {
        headers: jsonHeaders,
        data: {
          title,
          start_date: startDate,
          duplicate_products: true,
          duplicate_questions: false,
          duplicate_settings: true,
          duplicate_promo_codes: false,
          duplicate_capacity_assignments: false,
          duplicate_check_in_lists: false,
          duplicate_event_cover_image: false,
          duplicate_webhooks: false,
          duplicate_affiliates: false,
          duplicate_ticket_logo: false,
        },
      }),
    );
  }

  occupiedSeats(eventId: number, occurrenceId: number): Promise<OccupiedSeat[]> {
    return unwrap<OccupiedSeat[]>(
      this.request.get(`events/${eventId}/occurrences/${occurrenceId}/occupied-seats`, { headers: jsonHeaders }),
    );
  }

  getEventSeatMap(eventId: number): Promise<{
    version: number;
    layout: SeatMapLayout;
    source_seat_map: { id: number; name: string } | null;
    band_products: { band_key: string; products: { product_id: number; price_adjustment: number }[] }[];
    band_prices?: { band_key: string; product_price_id: number; price: number; price_including_taxes_and_fees: number }[];
  }> {
    return unwrap(this.request.get(`events/${eventId}/seat-map`, { headers: jsonHeaders }));
  }

  updateEventSeatMapLayout(
    eventId: number,
    layout: SeatMapLayout,
    opts: { version?: number; confirmRelabel?: boolean } = {},
  ): Promise<APIResponse> {
    return this.request.put(`events/${eventId}/seat-map/layout`, {
      headers: jsonHeaders,
      data: { layout, version: opts.version, confirm_relabel: opts.confirmRelabel },
    });
  }

  updateSeatMap(organizerId: number, seatMapId: number, name: string, layout: SeatMapLayout): Promise<void> {
    return check(
      this.request.put(`organizers/${organizerId}/seat-maps/${seatMapId}`, { headers: jsonHeaders, data: { name, layout } }),
    );
  }

  async findOrderIdByShortId(eventId: number, orderShortId: string): Promise<number> {
    const orders = await this.listOrders(eventId);
    const order = orders.find((candidate) => candidate.short_id === orderShortId);
    if (!order) {
      throw new Error(`Order ${orderShortId} not found among ${orders.length} orders for event ${eventId}`);
    }
    return order.id;
  }

  markOrderAsPaid(eventId: number, orderId: number): Promise<void> {
    return check(this.request.post(`events/${eventId}/orders/${orderId}/mark-as-paid`, { headers: jsonHeaders }));
  }

  cancelOrder(eventId: number, orderId: number): Promise<void> {
    return check(this.request.post(`events/${eventId}/orders/${orderId}/cancel`, { headers: jsonHeaders }));
  }

  listAttendees(eventId: number): Promise<AttendeeRecord[]> {
    return unwrap<AttendeeRecord[]>(this.request.get(`events/${eventId}/attendees`, { headers: jsonHeaders }));
  }

  async findAttendeeIdByPublicId(eventId: number, publicId: string): Promise<number> {
    const attendees = await this.listAttendees(eventId);
    const attendee = attendees.find((candidate) => candidate.public_id === publicId);
    if (!attendee) {
      throw new Error(`Attendee ${publicId} not found among ${attendees.length} attendees for event ${eventId}`);
    }
    return attendee.id;
  }

  updateAttendeeStatus(eventId: number, attendeeId: number, status: 'ACTIVE' | 'CANCELLED'): Promise<void> {
    return check(
      this.request.patch(`events/${eventId}/attendees/${attendeeId}`, { headers: jsonHeaders, data: { status } }),
    );
  }

  async generateOccurrences(eventId: number, recurrenceRule: RecurrenceRule): Promise<void> {
    const response = await this.request.post(`events/${eventId}/occurrences/generate`, {
      headers: jsonHeaders,
      data: { recurrence_rule: recurrenceRule },
    });
    if (!response.ok()) {
      throw new Error(`API ${response.url()} → ${response.status()}: ${await response.text()}`);
    }
    const { status, job_uuid: jobUuid } = (await response.json()) as { status: string; job_uuid: string };
    let currentStatus = status;
    const deadline = Date.now() + 60_000;
    while (currentStatus === 'IN_PROGRESS') {
      if (Date.now() > deadline) {
        throw new Error(`Occurrence generation for event ${eventId} timed out`);
      }
      await new Promise((resolve) => setTimeout(resolve, 500));
      const pollResponse = await this.request.get(
        `events/${eventId}/occurrences/generate/status?job_uuid=${jobUuid}`,
        { headers: jsonHeaders },
      );
      if (!pollResponse.ok()) {
        throw new Error(`API ${pollResponse.url()} → ${pollResponse.status()}: ${await pollResponse.text()}`);
      }
      const poll = (await pollResponse.json()) as { status: string };
      currentStatus = poll.status;
    }
    if (currentStatus !== 'FINISHED') {
      throw new Error(`Occurrence generation for event ${eventId} ended with status ${currentStatus}`);
    }
  }

  listOccurrences(eventId: number): Promise<Occurrence[]> {
    return unwrap<Occurrence[]>(this.request.get(`events/${eventId}/occurrences`, { headers: jsonHeaders }));
  }

  createOrganizerLocation(organizerId: number, payload: CreateOrganizerLocationPayload): Promise<{ id: number }> {
    return unwrap<{ id: number }>(
      this.request.post(`organizers/${organizerId}/locations`, { headers: jsonHeaders, data: payload }),
    );
  }

  setOrganizerLocation(organizerId: number, locationId: number | null): Promise<void> {
    return check(
      this.request.patch(`organizers/${organizerId}/location`, { headers: jsonHeaders, data: { location_id: locationId } }),
    );
  }

  createOccurrence(eventId: number, payload: UpdateOccurrencePayload): Promise<Occurrence> {
    return unwrap<Occurrence>(
      this.request.post(`events/${eventId}/occurrences`, { headers: jsonHeaders, data: payload }),
    );
  }

  updateOccurrence(eventId: number, occurrenceId: number, payload: UpdateOccurrencePayload): Promise<void> {
    return check(
      this.request.put(`events/${eventId}/occurrences/${occurrenceId}`, { headers: jsonHeaders, data: payload }),
    );
  }

  setOccurrencePriceOverride(eventId: number, occurrenceId: number, payload: OccurrencePriceOverridePayload): Promise<void> {
    return check(
      this.request.put(`events/${eventId}/occurrences/${occurrenceId}/price-overrides`, {
        headers: jsonHeaders,
        data: payload,
      }),
    );
  }
}

export interface UpsertAnnouncementPayload {
  title: string;
  content: string;
  status: 'DRAFT' | 'PUBLISHED';
  display_type: 'BANNER' | 'MODAL';
  emoji?: string;
  target_type: 'ALL' | 'ACCOUNTS' | 'USERS';
  target_account_ids?: number[];
  target_user_ids?: number[];
  cta_label?: string;
  cta_url?: string;
}

export class AdminApiClient {
  constructor(private readonly request: APIRequestContext) {}

  setMessagingTier(accountId: number, messagingTierId: number): Promise<void> {
    return check(
      this.request.put(`admin/accounts/${accountId}/messaging-tier`, {
        headers: jsonHeaders,
        data: { messaging_tier_id: messagingTierId },
      }),
    );
  }

  setAccountFeatureFlag(accountId: number, key: string, enabled: boolean | null): Promise<void> {
    return check(
      this.request.put(`admin/accounts/${accountId}/feature-flags/${key}`, {
        headers: jsonHeaders,
        data: { enabled },
      }),
    );
  }

  setAccountVerification(accountId: number, isManuallyVerified: boolean): Promise<void> {
    return check(
      this.request.put(`admin/accounts/${accountId}/verification`, {
        headers: jsonHeaders,
        data: { is_manually_verified: isManuallyVerified },
      }),
    );
  }

  async findAccountIdByEmail(email: string): Promise<number> {
    const accounts = await unwrap<{ id: number; email: string }[]>(
      this.request.get('admin/accounts', { headers: jsonHeaders, params: { search: email } }),
    );

    const match = accounts.find((account) => account.email === email);
    if (!match) {
      throw new Error(`No admin account found for ${email}`);
    }

    return match.id;
  }

  listConfigurations(): Promise<{ id: number; name: string; is_system_default: boolean; default_for_currency: string | null }[]> {
    return unwrap(this.request.get('admin/configurations', { headers: jsonHeaders }));
  }

  createAnnouncement(payload: UpsertAnnouncementPayload): Promise<{ id: number }> {
    return unwrap(this.request.post('admin/announcements', { headers: jsonHeaders, data: payload }));
  }

  deleteAnnouncement(announcementId: number): Promise<void> {
    return check(this.request.delete(`admin/announcements/${announcementId}`, { headers: jsonHeaders }));
  }
}
