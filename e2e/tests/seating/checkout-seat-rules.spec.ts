import type { APIRequestContext, Page } from '@playwright/test';
import { test, expect } from '../../fixtures';
import { CheckoutPage } from '../../pages/checkout.page';
import { SeatPickerPage } from '../../pages/seat-picker.page';
import { createSeatedEvent, createSeatedOrder, type SeatedEvent } from '../../api/factory';
import type { ApiClient } from '../../api/api-client';
import { uniqueCode, uniqueEmail } from '../../utils/unique';

const NO_RULES = { prevent_orphan_seats: false, max_seats_per_order: null, allow_seat_change: false };

async function addBandProduct(
  api: ApiClient,
  event: SeatedEvent,
  title: string,
  opts: { productType?: 'TICKET' | 'GENERAL'; linkToBand?: string; hiddenWithoutPromoCode?: boolean } = {},
): Promise<{ productId: number; priceId: number }> {
  const [category] = await api.listProductCategories(event.eventId);
  const created = await api.createProduct(event.eventId, {
    title,
    product_type: opts.productType ?? 'TICKET',
    type: 'FREE',
    product_category_id: category.id,
    prices: [{ price: 0 }],
    ...(opts.hiddenWithoutPromoCode ? { is_hidden_without_promo_code: true } : {}),
  });
  if (opts.linkToBand) {
    await api.linkSeatMapBands(event.eventId, [{ band_key: opts.linkToBand, products: [{ product_id: event.productId }, { product_id: created.id }] }]);
  }
  return { productId: created.id, priceId: created.prices![0].id! };
}

async function checkoutAndComplete(page: Page, checkout: CheckoutPage, attendeeCount = 1): Promise<void> {
  await page.waitForURL(/\/checkout\/\d+\/[^/]+\/details/);
  await checkout.fillOrderAndAttendees({ firstName: 'Guest', lastName: 'Seated', email: uniqueEmail('seat-rules') }, attendeeCount);
  await checkout.completeFreeOrder();
}

async function orderSeatsOverApi(publicApi: APIRequestContext, event: SeatedEvent, seatUids: string[]) {
  return publicApi.post(`public/events/${event.eventId}/order`, {
    headers: { Accept: 'application/json' },
    data: {
      products: [{
        product_id: event.productId,
        event_occurrence_id: event.occurrenceId,
        quantities: [{ price_id: event.priceId, quantity: seatUids.length, seat_uids: seatUids }],
      }],
    },
  });
}

test.describe('reserved seating checkout rules', () => {
  test.beforeEach(async ({ adminApi, account }) => {
    await adminApi.setAccountFeatureFlag(await adminApi.findAccountIdByEmail(account.email), 'seating', true);
  });

  test('a seat in a band with two ticket types is added as the first type, switched in the basket, and the next seat follows it', async ({ page, api, account }) => {
    const event = await createSeatedEvent(api, account.organizerId);
    const child = await addBandProduct(api, event, 'Child Seat', { linkToBand: 'b_premium' });
    const checkout = new CheckoutPage(page);
    await checkout.gotoPublicEvent(event.eventId, event.slug);

    const picker = new SeatPickerPage(page);
    await picker.selectSeat('e2.0.9');
    await expect(picker.ticketTypeOption(event.priceId)).toHaveCount(0);
    await expect(picker.checkedTicketType('e2.0.9')).toHaveText(/^Premium Seat/);
    await picker.changeTicketType('e2.0.9', /^Child Seat/);
    await expect(picker.checkedTicketType('e2.0.9')).toHaveText(/^Child Seat/);

    await picker.selectSeat('e2.0.10');
    await expect(picker.checkedTicketType('e2.0.10')).toHaveText(/^Child Seat/);
    await picker.selectSeat('e2.0.10');

    await picker.continueToDetails();
    await expect(page.getByText('Child Seat · Stalls · A-10')).toBeVisible();
    await checkoutAndComplete(page, checkout);

    await expect(page.getByText('Child Seat').filter({ visible: true }).first()).toBeVisible();
    const attendees = await api.listAttendees(event.eventId);
    expect(attendees).toEqual([expect.objectContaining({ product_id: child.productId, seat_label: 'Stalls · A-10' })]);
  });

  test('the max seats per order stops a third seat on the map and over the API', async ({ page, api, account, publicApi }) => {
    const event = await createSeatedEvent(api, account.organizerId);
    await api.updateSeatMapRules(event.eventId, { ...NO_RULES, max_seats_per_order: 2 });
    await new CheckoutPage(page).gotoPublicEvent(event.eventId, event.slug);

    const picker = new SeatPickerPage(page);
    await picker.selectSeats(['e2.0.9', 'e2.0.10', 'e2.0.11']);

    await expect(page.getByText('You can choose up to 2 seats per order')).toBeVisible();
    await expect(picker.seat('e2.0.11')).not.toHaveAttribute('data-state', 'selected');
    await expect(picker.basketLine('Stalls · A-12')).toHaveCount(0);
    await expect(picker.seatTotal(2)).toBeVisible();

    const response = await orderSeatsOverApi(publicApi, event, ['e2.0.0', 'e2.0.1', 'e2.0.2']);
    expect(response.status()).toBeGreaterThanOrEqual(400);
    expect(response.status()).toBeLessThan(500);
    expect(await response.text()).toContain('You can choose at most 2 seats per order');
  });

  test('the full-screen picker will not continue while a single empty seat is left', async ({ page, api, account }) => {
    const event = await createSeatedEvent(api, account.organizerId);
    await api.updateSeatMapRules(event.eventId, { ...NO_RULES, prevent_orphan_seats: true });
    await new CheckoutPage(page).gotoPublicEvent(event.eventId, event.slug);

    const picker = await new SeatPickerPage(page).openFullScreen();
    await picker.selectSeat('e2.0.1');

    await expect(picker.root().getByText("Please don't leave a single empty seat: Stalls · A-1")).toBeVisible();
    await expect(picker.continueButton()).toBeDisabled();

    await picker.selectSeat('e2.0.0');
    await expect(picker.root().getByText(/single empty seat/)).toHaveCount(0);
    await expect(picker.continueButton()).toBeEnabled();
  });

  test('the inline picker will not continue while a single empty seat is left', async ({ page, api, account }) => {
    const event = await createSeatedEvent(api, account.organizerId);
    await api.updateSeatMapRules(event.eventId, { ...NO_RULES, prevent_orphan_seats: true });
    await new CheckoutPage(page).gotoPublicEvent(event.eventId, event.slug);

    const picker = new SeatPickerPage(page);
    await picker.selectSeat('e2.0.1');
    await expect(picker.root().getByText("Please don't leave a single empty seat: Stalls · A-1")).toBeVisible();
    await expect(picker.continueButton()).toBeDisabled();
  });

  test('a seated ticket and an unseated product are bought in one order', async ({ page, api, account }) => {
    const event = await createSeatedEvent(api, account.organizerId);
    const programme = await addBandProduct(api, event, 'Programme', { productType: 'GENERAL' });
    const checkout = new CheckoutPage(page);
    await checkout.gotoPublicEvent(event.eventId, event.slug);

    await expect(page.locator('.hi-product-row').filter({ hasText: 'Programme' })).toBeVisible();
    await checkout.setQuantityForProduct('Programme', 1);
    const picker = new SeatPickerPage(page);
    await picker.selectSeat('e2.0.9');

    await picker.continueToDetails();
    await expect(page.getByText('Premium Seat · Stalls · A-10')).toBeVisible();
    await expect(page.getByTestId('inline-order-summary')).toContainText('Programme');
    await checkoutAndComplete(page, checkout);

    const orders = await api.listOrders(event.eventId);
    expect(orders).toHaveLength(1);
    expect(orders[0].order_items).toHaveLength(2);
    const attendees = await api.listAttendees(event.eventId);
    const ticketHolder = attendees.find((attendee) => attendee.product_id === event.productId)!;
    expect(ticketHolder.seat_label).toBe('Stalls · A-10');
    for (const attendee of attendees.filter((candidate) => candidate.product_id === programme.productId)) {
      expect(attendee.seat_label ?? null).toBeNull();
    }
  });

  test('a buyer takes three places in a standing area', async ({ page, api, account }) => {
    const event = await createSeatedEvent(api, account.organizerId, { layout: 'club', bandKey: 'b_standard' });
    const checkout = new CheckoutPage(page);
    await checkout.gotoPublicEvent(event.eventId, event.slug);

    const picker = new SeatPickerPage(page);
    await picker.selectZone('z3', 3);
    await expect(picker.seatTotal(3)).toBeVisible();

    await picker.continueToDetails();
    await checkoutAndComplete(page, checkout, 3);

    const labels = (await api.listAttendees(event.eventId)).map((attendee) => attendee.seat_label);
    expect(labels).toEqual(['Main floor · Main floor', 'Main floor · Main floor', 'Main floor · Main floor']);
  });

  test('a wheelchair space tells the buyer it is reserved before they book it', async ({ page, api, account }) => {
    const event = await createSeatedEvent(api, account.organizerId, { bandKey: 'b_standard' });
    const checkout = new CheckoutPage(page);
    await checkout.gotoPublicEvent(event.eventId, event.slug);

    const picker = new SeatPickerPage(page);
    await picker.selectSeat('e6.0.0');
    await expect(page.getByText('This space is reserved for wheelchair users and guests with access needs.')).toBeVisible();
    await picker.confirmSeatSheet();

    await picker.continueToDetails();
    await checkoutAndComplete(page, checkout);

    expect(await api.listAttendees(event.eventId)).toEqual([expect.objectContaining({ seat_label: 'Stalls · W-1' })]);
  });

  test('a promo-code-only ticket becomes a seat option once the code is applied', async ({ page, api, account }) => {
    const event = await createSeatedEvent(api, account.organizerId);
    const member = await addBandProduct(api, event, 'Member Seat', { linkToBand: 'b_premium', hiddenWithoutPromoCode: true });
    const code = uniqueCode('SEAT');
    await api.createPromoCode(event.eventId, { code, discount_type: 'NONE' });
    const checkout = new CheckoutPage(page);
    await checkout.gotoPublicEvent(event.eventId, event.slug);

    const picker = new SeatPickerPage(page);
    const seat = picker.seat('e2.0.9');
    await picker.selectSeat('e2.0.9');
    await expect(seat).toHaveAttribute('data-state', 'selected');
    await expect(picker.ticketTypeOption(member.priceId)).toHaveCount(0);
    await picker.selectSeat('e2.0.9');
    await expect(seat).toHaveAttribute('data-state', 'free');

    await checkout.applyPromoCode(code);
    await expect(page.getByText(`Promo ${code} code applied`)).toBeVisible();

    await picker.selectSeat('e2.0.9');
    await picker.changeTicketType('e2.0.9', /^Member Seat/);

    await picker.continueToDetails();
    await expect(page.getByText('Member Seat · Stalls · A-10')).toBeVisible();
  });

  test('a buyer cannot change their seat when the event does not allow it', async ({ page, api, account, publicApi }) => {
    const event = await createSeatedEvent(api, account.organizerId);
    await api.updateSeatMapRules(event.eventId, NO_RULES);
    const order = await createSeatedOrder(publicApi, event, ['e2.0.9']);

    await page.goto(`/checkout/${event.eventId}/${order.orderShortId}/summary`);
    await expect(page.getByText('Premium Seat · Stalls · A-10')).toBeVisible();
    await expect(page.getByTestId('order-change-seat-button')).toHaveCount(0);

    const response = await publicApi.put(
      `public/events/${event.eventId}/order/${order.orderShortId}/attendees/${order.attendees[0].shortId}/seat`,
      { headers: { Accept: 'application/json' }, data: { seat_uid: 'e2.0.12' } },
    );
    expect(response.status()).toBeGreaterThanOrEqual(400);
    expect(response.status()).toBeLessThan(500);
    expect(await response.text()).toContain('Seats can no longer be changed for this ticket');

    expect(await api.listAttendees(event.eventId)).toEqual([expect.objectContaining({ seat_label: 'Stalls · A-10' })]);
  });
});

test.describe('reserved seating checkout on a phone', () => {
  test.use({ viewport: { width: 390, height: 844 }, hasTouch: true, isMobile: true });

  test.beforeEach(async ({ adminApi, account }) => {
    await adminApi.setAccountFeatureFlag(await adminApi.findAccountIdByEmail(account.email), 'seating', true);
  });

  test('a buyer picks a seat in the full-screen picker and continues to details', async ({ page, api, account }) => {
    const event = await createSeatedEvent(api, account.organizerId);
    await new CheckoutPage(page).gotoPublicEvent(event.eventId, event.slug);

    const picker = await new SeatPickerPage(page, { touch: true }).openFullScreen();
    await picker.selectSeat('e2.0.9');
    await expect(picker.basketLine('Stalls · A-10')).toBeVisible();

    await picker.continueToDetails();
    await expect(page.getByText('Premium Seat · Stalls · A-10')).toBeVisible();
  });
});
