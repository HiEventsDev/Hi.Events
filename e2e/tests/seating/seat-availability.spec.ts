import type {APIRequestContext, Locator, Page} from '@playwright/test';
import {test, expect} from '../../fixtures';
import {CheckoutPage} from '../../pages/checkout.page';
import {SeatPickerPage} from '../../pages/seat-picker.page';
import {PublicOccurrenceSelector} from '../../pages/occurrence.page';
import {createSeatedEvent, createSeatedOrder, enableOfflinePayments, type SeatedEvent} from '../../api/factory';
import type {ApiClient} from '../../api/api-client';
import type {Occurrence} from '../../api/types';
import {uniqueEmail} from '../../utils/unique';

const A_10 = 'e2.0.9';
const A_11 = 'e2.0.10';

const seat = (page: Page, uid: string): Locator => new SeatPickerPage(page).seat(uid);

async function chooseDate(page: Page, occurrence: Occurrence): Promise<void> {
  const selector = new PublicOccurrenceSelector(page);
  await selector.selectDay(occurrence.start_date);
  await expect(selector.productsLoadingOverlay()).toHaveCount(0);
}

const priceOf = (text: string): number => Number(/\$([\d,]+\.\d{2})/.exec(text)![1].replace(',', ''));

async function bandSeatUids(api: ApiClient, eventId: number, bandKey: string): Promise<string[]> {
  const {layout} = await api.getEventSeatMap(eventId);
  return layout.areas
    .flatMap((area) => area.elements)
    .flatMap((element) => (element.seats as { uid: string; band: string }[] | undefined) ?? [])
    .filter((candidate) => candidate.band === bandKey)
    .map((candidate) => candidate.uid);
}

async function bestAvailable(publicApi: APIRequestContext, event: SeatedEvent, quantity: number): Promise<string[]> {
  const response = await publicApi.get(
    `public/events/${event.eventId}/occurrences/${event.occurrenceId}/best-available-seats`,
    {params: {product_id: event.productId, quantity, accessible: 0}},
  );
  expect(response.status()).toBe(200);
  return (await response.json()).data.seat_uids;
}

async function addWaitlistedSeatedProduct(api: ApiClient, event: SeatedEvent): Promise<void> {
  const [category] = await api.listProductCategories(event.eventId);
  const created = await api.createProduct(event.eventId, {
    title: 'Waitlisted Seat',
    product_type: 'TICKET',
    type: 'FREE',
    product_category_id: category.id,
    prices: [{price: 0}],
    waitlist_enabled: true,
  });
  await api.linkSeatMapBands(event.eventId, [{ band_key: 'b_premium', products: [{ product_id: event.productId }, { product_id: created.id }] }]);
}

async function createSoldOutSeatedEvent(api: ApiClient, publicApi: APIRequestContext, organizerId: number): Promise<SeatedEvent> {
  const event = await createSeatedEvent(api, organizerId);
  await addWaitlistedSeatedProduct(api, event);
  const premiumSeats = await bandSeatUids(api, event.eventId, 'b_premium');
  expect(premiumSeats.length).toBeGreaterThan(2);
  await createSeatedOrder(publicApi, event, premiumSeats.slice(0, 2));
  await api.blockSeats(event.eventId, event.occurrenceId, premiumSeats.slice(2));
  return event;
}

async function attendeesByOccurrence(api: ApiClient, eventId: number): Promise<Map<number, string[]>> {
  const occurrenceByOrder = new Map(
    (await api.listOrders(eventId)).map((order) => [order.id, order.order_items?.[0]?.event_occurrence_id ?? null]),
  );
  const result = new Map<number, string[]>();
  for (const attendee of await api.listAttendees(eventId)) {
    const occurrenceId = occurrenceByOrder.get(attendee.order_id)!;
    result.set(occurrenceId, [...(result.get(occurrenceId) ?? []), attendee.seat_label!]);
  }
  return result;
}

test.describe('reserved seating availability', () => {
  test.beforeEach(async ({adminApi, account}) => {
    await adminApi.setAccountFeatureFlag(await adminApi.findAccountIdByEmail(account.email), 'seating', true);
  });

  test('a seat sold on one date is free on another date and can be bought there', async ({page, api, account, publicApi}) => {
    const event = await createSeatedEvent(api, account.organizerId, {recurringCount: 2});
    const [firstDate, secondDate] = event.occurrences;
    await createSeatedOrder(publicApi, event, [A_10], {eventOccurrenceId: firstDate.id});

    const checkout = new CheckoutPage(page);
    await checkout.gotoPublicEventAfterAvailabilityCacheExpires(event.eventId, event.slug);
    await chooseDate(page, firstDate);
    await expect(seat(page, A_10)).toHaveAttribute('data-state', 'unavailable');
    await expect(seat(page, A_11)).toHaveAttribute('data-state', 'free');

    await chooseDate(page, secondDate);
    await expect(seat(page, A_10)).toHaveAttribute('data-state', 'free');
    const picker = new SeatPickerPage(page);
    await picker.selectSeat(A_10);
    await expect(picker.seat(A_10)).toHaveAttribute('data-state', 'selected');
    await expect(picker.basketLine('Stalls · A-10')).toBeVisible();

    await picker.continueToDetails();
    await expect(page.getByText('Premium Seat · Stalls · A-10')).toBeVisible();
    await checkout.fillOrderAndAttendees({firstName: 'Second', lastName: 'Night', email: uniqueEmail()}, 1);
    await checkout.completeFreeOrder();
    await expect(page.getByText('Stalls · A-10').filter({visible: true}).first()).toBeVisible();

    const seatsByDate = await attendeesByOccurrence(api, event.eventId);
    expect(seatsByDate.get(firstDate.id)).toEqual(['Stalls · A-10']);
    expect(seatsByDate.get(secondDate.id)).toEqual(['Stalls · A-10']);
  });

  test('switching date clears the seats already chosen', async ({page, api, account}) => {
    const event = await createSeatedEvent(api, account.organizerId, {recurringCount: 2});
    const [firstDate, secondDate] = event.occurrences;
    await new CheckoutPage(page).gotoPublicEvent(event.eventId, event.slug);

    const picker = new SeatPickerPage(page);
    await chooseDate(page, firstDate);
    await picker.selectSeats([A_10, A_11]);
    await expect(picker.basketLine('Stalls · A-10')).toBeVisible();
    await expect(picker.basketLine('Stalls · A-11')).toBeVisible();
    await expect(picker.continueButton()).toBeEnabled();

    await chooseDate(page, secondDate);
    await expect(picker.basketLine('Stalls · A-10')).toHaveCount(0);
    await expect(picker.basketLine('Stalls · A-11')).toHaveCount(0);
    await expect(picker.root().getByText('Choose a seat on the map to add it to your order')).toBeVisible();
    await expect(picker.seat(A_10)).toHaveAttribute('data-state', 'free');
    await expect(picker.seat(A_11)).toHaveAttribute('data-state', 'free');
    await expect(picker.continueButton()).toBeDisabled();
  });

  test('a held-back seat cannot be chosen, found or ordered by the public', async ({page, api, account, publicApi}) => {
    const event = await createSeatedEvent(api, account.organizerId);
    await api.blockSeats(event.eventId, event.occurrenceId, [A_10, A_11], 'House seats');

    await new CheckoutPage(page).gotoPublicEvent(event.eventId, event.slug);
    const picker = new SeatPickerPage(page);
    await expect(picker.seat(A_10)).toHaveAttribute('data-state', 'unavailable');
    await expect(picker.seat(A_11)).toHaveAttribute('data-state', 'unavailable');

    await picker.selectSeat(A_10);
    await expect(picker.seat(A_10)).toHaveAttribute('data-state', 'unavailable');
    await expect(picker.basketLine('Stalls · A-10')).toHaveCount(0);
    await expect(picker.continueButton()).toBeDisabled();

    await picker.bestAvailable();
    await expect(picker.removeButtons()).toHaveCount(2);
    await expect(picker.basketLine('Stalls · A-10')).toHaveCount(0);
    await expect(picker.basketLine('Stalls · A-11')).toHaveCount(0);

    const found = await bestAvailable(publicApi, event, 20);
    expect(found).toHaveLength(20);
    expect(found).not.toContain(A_10);
    expect(found).not.toContain(A_11);

    const response = await publicApi.post(`public/events/${event.eventId}/order`, {
      headers: {Accept: 'application/json'},
      data: {
        products: [{
          product_id: event.productId,
          event_occurrence_id: event.occurrenceId,
          quantities: [{price_id: event.priceId, quantity: 1, seat_uids: [A_10]}],
        }],
      },
    });
    expect(response.status()).toBe(409);
    expect((await response.json()).errors.unavailable_seat_uids).toEqual([A_10]);
  });

  test('a band with every seat taken offers nothing to choose and no waitlist', async ({page, api, account, publicApi}) => {
    const event = await createSoldOutSeatedEvent(api, publicApi, account.organizerId);

    await new CheckoutPage(page).gotoPublicEventAfterAvailabilityCacheExpires(event.eventId, event.slug);
    const picker = new SeatPickerPage(page);
    await expect(picker.seat(A_10)).toHaveAttribute('data-state', 'unavailable');
    await expect(picker.seatsInState('free')).toHaveCount(0);

    await picker.selectSeat(A_10);
    await expect(picker.seatsInState('selected')).toHaveCount(0);
    await expect(picker.basketLine('Stalls · A-10')).toHaveCount(0);
    await expect(picker.continueButton()).toBeDisabled();
    await expect(page.getByTestId('join-waitlist-button')).toHaveCount(0);
    await expect(page.getByRole('button', {name: /waitlist/i})).toHaveCount(0);

    expect(await bestAvailable(publicApi, event, 1)).toEqual([]);
  });

  test('a band with every seat taken is labelled sold out', async ({page, api, account, publicApi}) => {
    const event = await createSoldOutSeatedEvent(api, publicApi, account.organizerId);

    await new CheckoutPage(page).gotoPublicEventAfterAvailabilityCacheExpires(event.eventId, event.slug);
    const picker = new SeatPickerPage(page);
    await expect(picker.root().getByText('Premium', {exact: true}).locator('..')).toContainText('Sold out');
    await expect(picker.root().getByText('Choose a seat on the map to add it to your order')).toHaveCount(0);
    await expect(page.getByTestId('seat-prices-toggle')).toHaveCount(0);
    await expect(page.getByTestId('best-available-button')).toHaveCount(0);
  });

  test('a paid seated order paid offline keeps its seats while awaiting payment and sells them once paid', async ({page, api, account}) => {
    const event = await createSeatedEvent(api, account.organizerId, {price: 25});
    await enableOfflinePayments(api, event.eventId);
    const checkout = new CheckoutPage(page);
    await checkout.gotoPublicEvent(event.eventId, event.slug);

    const picker = new SeatPickerPage(page);
    await picker.selectSeats([A_10, A_11]);
    const lineA10 = picker.basketLine('Stalls · A-10').locator('../..');
    const lineA11 = picker.basketLine('Stalls · A-11').locator('../..');
    await expect(lineA10).toContainText(/Premium Seat · \$\d+\.\d{2}/);
    const seatPrice = priceOf(await lineA10.innerText());
    expect(seatPrice).toBeGreaterThanOrEqual(25);
    expect(priceOf(await lineA11.innerText())).toBe(seatPrice);
    const footer = picker.seatTotal(2).locator('..');
    await expect(footer).toBeVisible();
    expect(priceOf(await footer.innerText())).toBeCloseTo(seatPrice * 2, 2);

    await picker.continueToDetails();
    await expect(page.getByTestId('inline-order-summary')).toContainText('Stalls · A-10, Stalls · A-11');
    await checkout.fillOrderAndAttendees({firstName: 'Paid', lastName: 'Seated', email: uniqueEmail('offlineseats')}, 2);
    await checkout.continueToPayment();
    await checkout.chooseOfflinePayment();

    await expect(page.getByText('Your order is awaiting payment')).toBeVisible();
    await expect(page.getByText('Stalls · A-10').filter({visible: true}).first()).toBeVisible();
    await expect(page.getByText('Stalls · A-11').filter({visible: true}).first()).toBeVisible();

    const orderShortId = page.url().match(/\/checkout\/\d+\/([^/?]+)\/summary/)![1];
    const order = (await api.listOrders(event.eventId)).find((candidate) => candidate.short_id === orderShortId)!;
    expect(order.status).toBe('AWAITING_OFFLINE_PAYMENT');
    const occupied = await api.occupiedSeats(event.eventId, event.occurrenceId);
    expect(occupied.map((claim) => claim.seat_uid).sort()).toEqual([A_11, A_10].sort());

    const otherBuyer = await page.context().newPage();
    await new CheckoutPage(otherBuyer).gotoPublicEvent(event.eventId, event.slug);
    await expect(seat(otherBuyer, A_10)).toHaveAttribute('data-state', 'unavailable');
    await expect(seat(otherBuyer, A_11)).toHaveAttribute('data-state', 'unavailable');
    await otherBuyer.close();

    await api.markOrderAsPaid(event.eventId, order.id);

    const sold = await api.occupiedSeats(event.eventId, event.occurrenceId);
    expect(sold.map((claim) => [claim.seat_uid, claim.status]).sort()).toEqual([[A_10, 'SOLD'], [A_11, 'SOLD']].sort());
    const attendees = await api.listAttendees(event.eventId);
    expect(attendees.map((attendee) => [attendee.status, attendee.seat_label]).sort()).toEqual([
      ['ACTIVE', 'Stalls · A-10'],
      ['ACTIVE', 'Stalls · A-11'],
    ]);
  });
});
