import type { APIRequestContext, Locator, Page } from '@playwright/test';
import { test, expect } from '../../fixtures';
import { CheckoutPage } from '../../pages/checkout.page';
import { SeatPickerPage } from '../../pages/seat-picker.page';
import { OccurrencePage } from '../../pages/occurrence.page';
import { AttendeePage } from '../../pages/attendee.page';
import { createSeatedEvent, createSeatedOrder, type SeatedEvent } from '../../api/factory';
import type { ApiClient } from '../../api/api-client';
import { uniqueEmail } from '../../utils/unique';

const A8 = 'e2.0.7';
const A9 = 'e2.0.8';
const A10 = 'e2.0.9';
const A11 = 'e2.0.10';
const A12 = 'e2.0.11';

const NO_RULES = { prevent_orphan_seats: false, max_seats_per_order: null, allow_seat_change: false };

const salesSeat = (page: Page, uid: string): Locator =>
  page.getByTestId('seating-sales-map').locator(`[data-uid="${uid}"]`);

const chooserSeat = (page: Page, uid: string): Locator =>
  page.getByTestId('seat-chooser-map').locator(`[data-uid="${uid}"]`);

async function gotoSalesMap(page: Page, eventId: number): Promise<void> {
  await page.goto(`/manage/event/${eventId}/seating/sales`);
  await expect(page.getByTestId('seating-sales-map')).toBeVisible();
}

async function occupiedUids(api: ApiClient, eventId: number, occurrenceId: number): Promise<string[]> {
  return (await api.occupiedSeats(eventId, occurrenceId)).map((seat) => seat.seat_uid);
}

async function soldUids(api: ApiClient, eventId: number, occurrenceId: number): Promise<string[]> {
  return (await api.occupiedSeats(eventId, occurrenceId))
    .filter((seat) => seat.status === 'SOLD')
    .map((seat) => seat.seat_uid);
}

async function attendeeByEmail(api: ApiClient, eventId: number, email: string) {
  const attendee = (await api.listAttendees(eventId)).find((candidate) => candidate.email === email);
  expect(attendee, `no attendee found for ${email}`).toBeDefined();
  return attendee!;
}

async function premiumSeatUids(api: ApiClient, eventId: number): Promise<string[]> {
  const { layout } = await api.getEventSeatMap(eventId);
  return layout.areas
    .flatMap((area) => area.elements)
    .flatMap((element) => (element.seats as { uid: string; band: string }[] | undefined) ?? [])
    .filter((seat) => seat.band === 'b_premium')
    .map((seat) => seat.uid);
}

async function leaveOnlyTheseSeatsFree(api: ApiClient, event: SeatedEvent, freeUids: string[]): Promise<void> {
  const blocked = (await premiumSeatUids(api, event.eventId)).filter((uid) => !freeUids.includes(uid));
  await api.blockSeats(event.eventId, event.occurrenceId, blocked, 'Fragmenting the row');
}

async function bestAvailableResponse(publicApi: APIRequestContext, event: SeatedEvent, quantity: number) {
  return publicApi.get(
    `public/events/${event.eventId}/occurrences/${event.occurrenceId}/best-available-seats`,
    { headers: { Accept: 'application/json' }, params: { product_id: event.productId, quantity } },
  );
}

test.describe('reserved seating lifecycle', () => {
  test.beforeEach(async ({ adminApi, account }) => {
    await adminApi.setAccountFeatureFlag(await adminApi.findAccountIdByEmail(account.email), 'seating', true);
  });

  test('cancelling a date frees its seats and the cancelled ticket can take one back', async ({ authedPage, api, publicApi, account }) => {
    const event = await createSeatedEvent(api, account.organizerId, { recurringCount: 2 });
    const [firstDate, secondDate] = event.occurrences;
    const firstDateBuyer = uniqueEmail('first-date');
    await createSeatedOrder(publicApi, event, [A10], { eventOccurrenceId: firstDate.id, buyerEmail: firstDateBuyer });
    await createSeatedOrder(publicApi, event, [A8], { eventOccurrenceId: secondDate.id, buyerEmail: uniqueEmail('second-date') });

    await gotoSalesMap(authedPage, event.eventId);
    await expect(salesSeat(authedPage, A10)).toHaveAttribute('data-state', 'sold');
    await expect(salesSeat(authedPage, A8)).toHaveAttribute('data-state', 'free');
    await expect(authedPage.getByText('Sold · 1')).toBeVisible();

    const occurrences = new OccurrencePage(authedPage);
    await occurrences.goto(event.eventId);
    await occurrences.chooseRowAction(occurrences.occurrenceRows().first(), 'Cancel');
    await occurrences.confirmModalAction('Cancel Date');
    await expect(authedPage.getByText('Date cancelled')).toBeVisible();
    await expect(occurrences.statusBadges('CANCELLED')).toHaveCount(1);

    expect((await attendeeByEmail(api, event.eventId, firstDateBuyer)).status).toBe('CANCELLED');
    expect(await occupiedUids(api, event.eventId, firstDate.id)).toEqual([]);
    expect(await occupiedUids(api, event.eventId, secondDate.id)).toEqual([A8]);

    await gotoSalesMap(authedPage, event.eventId);
    await expect(salesSeat(authedPage, A8)).toHaveAttribute('data-state', 'sold');
    await expect(salesSeat(authedPage, A10)).toHaveAttribute('data-state', 'free');
    await expect(authedPage.getByText('Sold · 1')).toBeVisible();

    await occurrences.goto(event.eventId);
    await occurrences.chooseRowAction(occurrences.rowWithStatus('CANCELLED'), 'Reopen for new sales');
    await occurrences.confirmModalAction('Confirm');
    await expect(authedPage.getByText(/had ticket sales that were cancelled with it/)).toBeVisible();
    await expect(occurrences.statusBadges('CANCELLED')).toHaveCount(1);

    const attendees = new AttendeePage(authedPage);
    await attendees.goto(event.eventId);
    await attendees.openRowAction(firstDateBuyer, 'Activate');
    await authedPage.getByRole('dialog').getByRole('button', { name: 'Confirm' }).click();
    await expect(authedPage.getByText('Successfully activated attendee')).toBeVisible();
    await expect(authedPage.getByText(/given to someone else/)).toHaveCount(0);

    expect((await attendeeByEmail(api, event.eventId, firstDateBuyer)).status).toBe('ACTIVE');
    expect(await occupiedUids(api, event.eventId, firstDate.id)).toEqual([A10]);
  });

  test('best available offers a pair the orphan rule accepts and the buyer checks out', async ({ page, api, publicApi, account }) => {
    const event = await createSeatedEvent(api, account.organizerId);
    await api.updateSeatMapRules(event.eventId, { ...NO_RULES, prevent_orphan_seats: true });
    await leaveOnlyTheseSeatsFree(api, event, [A9, A10, A11, A12]);

    const response = await bestAvailableResponse(publicApi, event, 2);
    expect(response.status()).toBe(200);
    const suggested: string[] = (await response.json()).data.seat_uids;
    expect([[A9, A10], [A11, A12]]).toContainEqual(suggested);

    const checkout = new CheckoutPage(page);
    await checkout.gotoPublicEventAfterAvailabilityCacheExpires(event.eventId, event.slug);
    const picker = new SeatPickerPage(page);
    await picker.bestAvailable();

    await expect(picker.removeButtons()).toHaveCount(2);
    await expect(page.getByText(/single empty seat/)).toHaveCount(0);
    await expect(picker.seatsInState('selected')).toHaveCount(2);

    await picker.continueToDetails();
    await checkout.fillOrderAndAttendees({ firstName: 'Guest', lastName: 'Seated', email: uniqueEmail('best-available') }, 2);
    await checkout.completeFreeOrder();
    await expect(page.getByText('Stalls · ').filter({ visible: true }).first()).toBeVisible();

    expect(await api.listAttendees(event.eventId)).toHaveLength(2);
    expect(new Set(await soldUids(api, event.eventId, event.occurrenceId))).toEqual(new Set(suggested));
  });

  test('best available refuses a quantity above the maximum seats per order', async ({ page, api, publicApi, account }) => {
    const event = await createSeatedEvent(api, account.organizerId);
    await api.updateSeatMapRules(event.eventId, { ...NO_RULES, max_seats_per_order: 2 });

    const refused = await bestAvailableResponse(publicApi, event, 3);
    expect(refused.status()).toBe(422);
    expect(await refused.text()).toContain('You can choose at most 2 seats per order');

    await new CheckoutPage(page).gotoPublicEvent(event.eventId, event.slug);
    const picker = new SeatPickerPage(page);
    await picker.bestAvailableQuantity().fill('3');
    await picker.bestAvailableQuantity().blur();
    await expect(picker.bestAvailableQuantity()).toHaveValue('2');

    await picker.bestAvailable();
    await expect(picker.removeButtons()).toHaveCount(2);
    await expect(picker.seatsInState('selected')).toHaveCount(2);
  });

  test('an organizer is told when a seat is taken mid-move and then moves the attendee elsewhere', async ({ authedPage, api, publicApi, account }) => {
    const event = await createSeatedEvent(api, account.organizerId);
    const movedEmail = uniqueEmail('mover');
    await api.createAttendee(event.eventId, {
      product_id: event.productId,
      product_price_id: event.priceId,
      event_occurrence_id: event.occurrenceId,
      seat_uid: A10,
      email: movedEmail,
      first_name: 'Ada',
      last_name: 'Mover',
      amount_paid: 0,
      send_confirmation_email: false,
      locale: 'en',
    });

    const attendees = new AttendeePage(authedPage);
    await attendees.goto(event.eventId);
    await authedPage.getByText('Ada Mover').first().click();
    await authedPage.getByTestId('attendee-change-seat-button').click();
    await chooserSeat(authedPage, A11).click();
    await expect(authedPage.getByText('Stalls · A-11', { exact: true })).toBeVisible();

    await createSeatedOrder(publicApi, event, [A11], { buyerEmail: uniqueEmail('faster') });

    await authedPage.getByTestId('seat-chooser-confirm-button').click();
    await expect(authedPage.getByText('That seat has just been taken')).toBeVisible();
    expect((await attendeeByEmail(api, event.eventId, movedEmail)).seat_label).toBe('Stalls · A-10');

    await chooserSeat(authedPage, A8).click();
    await authedPage.getByTestId('seat-chooser-confirm-button').click();
    await expect(authedPage.getByText('Attendee moved')).toBeVisible();

    expect((await attendeeByEmail(api, event.eventId, movedEmail)).seat_label).toBe('Stalls · A-8');
    await expect(attendees.rowByText(movedEmail)).toContainText('Stalls · A-8');

    await gotoSalesMap(authedPage, event.eventId);
    await expect(salesSeat(authedPage, A8)).toHaveAttribute('data-state', 'sold');
    await expect(salesSeat(authedPage, A10)).toHaveAttribute('data-state', 'free');
  });

  test('an order cancelled elsewhere frees its seats on the open sales map', async ({ authedPage, api, publicApi, account }) => {
    const event = await createSeatedEvent(api, account.organizerId);
    const order = await createSeatedOrder(publicApi, event, [A10, A11], { buyerEmail: uniqueEmail('walkaway') });
    const orderId = await api.findOrderIdByShortId(event.eventId, order.orderShortId);

    await gotoSalesMap(authedPage, event.eventId);
    await expect(salesSeat(authedPage, A10)).toHaveAttribute('data-state', 'sold');
    await expect(salesSeat(authedPage, A11)).toHaveAttribute('data-state', 'sold');
    await expect(authedPage.getByText('Sold · 2')).toBeVisible();

    await api.cancelOrder(event.eventId, orderId);

    await expect(salesSeat(authedPage, A10)).toHaveAttribute('data-state', 'free', { timeout: 30_000 });
    await expect(salesSeat(authedPage, A11)).toHaveAttribute('data-state', 'free');
    await expect(authedPage.getByText('Sold · 0')).toBeVisible();
    expect(await occupiedUids(api, event.eventId, event.occurrenceId)).toEqual([]);
  });
});
