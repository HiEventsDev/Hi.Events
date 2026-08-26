import type { APIRequestContext, APIResponse } from '@playwright/test';
import type {
  Affiliate,
  AttendeeRecord,
  CapacityAssignment,
  CheckInList,
  CreateAffiliatePayload,
  CreateAttendeePayload,
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
  EventRecord,
  EventSettings,
  EventStatus,
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

export class ApiClient {
  constructor(private readonly request: APIRequestContext) {}

  getAccount(): Promise<{ id: number; name: string }> {
    return unwrap<{ id: number; name: string }>(this.request.get('accounts', { headers: jsonHeaders }));
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

  setEventStatus(eventId: number, status: EventStatus): Promise<void> {
    return check(this.request.put(`events/${eventId}/status`, { headers: jsonHeaders, data: { status } }));
  }

  publishEvent(eventId: number): Promise<void> {
    return this.setEventStatus(eventId, 'LIVE');
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

  createAnnouncement(payload: UpsertAnnouncementPayload): Promise<{ id: number }> {
    return unwrap(this.request.post('admin/announcements', { headers: jsonHeaders, data: payload }));
  }

  deleteAnnouncement(announcementId: number): Promise<void> {
    return check(this.request.delete(`admin/announcements/${announcementId}`, { headers: jsonHeaders }));
  }
}
