import type {APIRequestContext} from '@playwright/test';
import {test, expect} from '../../fixtures';
import {CheckoutPage, setWidgetQuantity} from '../../pages/checkout.page';
import {SeatPickerPage} from '../../pages/seat-picker.page';
import {attachFixtureSeatMap, createDraftEvent, createSeatedEvent, type SeatedEvent} from '../../api/factory';
import type {ApiClient} from '../../api/api-client';
import {uniqueEmail} from '../../utils/unique';

const FRONT_ROW_CENTRE = ['e2.0.9', 'e2.0.10'];

async function takeSeats(publicApi: APIRequestContext, event: SeatedEvent, seatUids: string[]): Promise<void> {
  const eventResponse = await publicApi.get(`public/events/${event.eventId}`);
  const occurrenceId = (await eventResponse.json()).data.occurrences[0].id;

  const response = await publicApi.post(`public/events/${event.eventId}/order`, {
    data: {
      products: [{
        product_id: event.productId,
        event_occurrence_id: occurrenceId,
        quantities: [{price_id: event.priceId, quantity: seatUids.length, seat_uids: seatUids}],
      }],
    },
  });
  expect(response.status()).toBe(201);
}

async function createSeatedEventWithAddon(api: ApiClient, organizerId: number): Promise<{eventId: number; slug: string}> {
  const event = await createDraftEvent(api, organizerId);
  const [category] = await api.listProductCategories(event.eventId);
  const addon = await api.createProduct(event.eventId, {
    title: 'Programme',
    product_type: 'GENERAL',
    type: 'FREE',
    product_category_id: category.id,
    is_addon_only: true,
    prices: [{price: 0}],
  });
  const ticket = await api.createProduct(event.eventId, {
    title: 'Premium Seat',
    product_type: 'TICKET',
    type: 'FREE',
    product_category_id: category.id,
    addon_product_ids: [addon.id],
    prices: [{price: 0}],
  });
  await attachFixtureSeatMap(api, organizerId, event.eventId, [{band_key: 'b_premium', products: [{product_id: ticket.id}]}]);
  await api.publishEvent(event.eventId);
  return event;
}

const WHEELCHAIR_SPACE = 'e6.0.0';
const COMPANION_SEAT = 'e6.0.1';

async function makeCompanionSeat(api: ApiClient, eventId: number, seatUid: string): Promise<void> {
  const {version, layout} = await api.getEventSeatMap(eventId);
  const [elementId, row, index] = seatUid.split('.');
  const element = layout.areas.flatMap(area => area.elements).find(candidate => candidate.id === elementId)!;
  const overrides = element.overrides as Record<string, Record<string, unknown>>;
  overrides[`${row}.${index}`] = {comp: true};
  const seat = (element.seats as {uid: string; acc: boolean; comp: boolean}[]).find(candidate => candidate.uid === seatUid)!;
  seat.acc = false;
  seat.comp = true;
  const response = await api.updateEventSeatMapLayout(eventId, layout, {version});
  expect(response.status()).toBe(200);
}

test.describe('reserved seating checkout', () => {
  test.beforeEach(async ({adminApi, account}) => {
    await adminApi.setAccountFeatureFlag(await adminApi.findAccountIdByEmail(account.email), 'seating', true);
  });

  test('a buyer picks seats on the map and gets them on the order', {tag: '@smoke'}, async ({page, api, account, mailpit}) => {
    const event = await createSeatedEvent(api, account.organizerId);
    const checkout = new CheckoutPage(page);
    await checkout.gotoPublicEvent(event.eventId, event.slug);

    const picker = new SeatPickerPage(page);
    await expect(picker.root()).toBeVisible();
    await expect(page.locator('.hi-product-row h3').filter({hasText: 'Premium Seat'})).toHaveCount(0);

    await picker.selectSeats(FRONT_ROW_CENTRE);
    await expect(picker.basketLine('Stalls · A-10')).toBeVisible();
    await expect(picker.basketLine('Stalls · A-11')).toBeVisible();

    await picker.continueToDetails();

    await expect(page.getByText('Premium Seat · Stalls · A-10')).toBeVisible();
    await expect(page.getByText('Premium Seat · Stalls · A-11')).toBeVisible();
    await expect(page.getByTestId('inline-order-summary')).toContainText('Stalls · A-10, Stalls · A-11');

    const email = uniqueEmail();
    await checkout.fillOrderAndAttendees({firstName: 'Sally', lastName: 'Seated', email}, 2);
    await checkout.completeFreeOrder();

    await expect(page.getByText('Stalls · A-10').filter({visible: true}).first()).toBeVisible();
    await expect(page.getByText('Stalls · A-11').filter({visible: true}).first()).toBeVisible();

    const confirmation = await mailpit.waitForMessage(email, {subjectContains: 'Your Order is Confirmed'});
    expect((await mailpit.getMessage(confirmation.ID)).Text).toContain('Stalls · A-10, Stalls · A-11');
  });

  test('an add-on of a seated ticket is offered beside the chosen seats and bought with them', async ({page, api, account}) => {
    const event = await createSeatedEventWithAddon(api, account.organizerId);
    const checkout = new CheckoutPage(page);
    await checkout.gotoPublicEvent(event.eventId, event.slug);

    const addons = page.getByTestId('seated-product-addons');
    await expect(addons).toHaveCount(0);

    await new SeatPickerPage(page).selectSeat('e2.0.9');
    await expect(page.getByTestId('seated-products-section').getByTestId('seated-product-addons')
      .locator('.hi-product-addon').filter({hasText: 'Programme'})).toBeVisible();
    await checkout.setAddonQuantity('Programme', 1);
    await expect(page.getByTestId('seat-basket-total')).toBeVisible();
    await expect(page.getByTestId('seated-products-section')).toContainText('1 seat · 1 other item');
    await checkout.continueToCheckout();

    await expect(page.getByTestId('inline-order-summary')).toContainText('Programme');
    await checkout.fillOrderAndAttendees({firstName: 'Addie', lastName: 'Seated', email: uniqueEmail('seataddon')}, 1);
    await checkout.completeFreeOrder();

    const [order] = await api.listOrders(event.eventId);
    expect((order.order_items ?? []).map(item => item.item_name).sort()).toEqual(['Premium Seat', 'Programme']);
  });

  test('the full-screen picker offers add-ons and unseated tickets in an extras step before checkout', async ({page, api, account}) => {
    const event = await createSeatedEventWithAddon(api, account.organizerId);
    const [category] = await api.listProductCategories(event.eventId);
    await api.createProduct(event.eventId, {
      title: 'Car Parking',
      product_type: 'GENERAL',
      type: 'FREE',
      product_category_id: category.id,
      prices: [{price: 0}],
    });
    const checkout = new CheckoutPage(page);
    await checkout.gotoPublicEvent(event.eventId, event.slug);

    const picker = await new SeatPickerPage(page).openFullScreen();
    await picker.selectSeat('e2.0.9');
    await expect(picker.continueButton()).toHaveText('Next');
    await picker.continueButton().click();

    const extras = picker.extrasStep();
    await expect(extras).toBeVisible();
    await expect(extras.getByText('Car Parking')).toBeVisible();
    await setWidgetQuantity(extras.locator('.hi-product-addon').filter({hasText: 'Programme'}), 1);
    await setWidgetQuantity(extras.locator('.hi-product-row').filter({hasText: 'Car Parking'}), 1);
    await expect(page.getByTestId('seat-picker-order-total')).toBeVisible();

    await page.getByTestId('seat-picker-back-button').click();
    await expect(picker.seat('e2.0.9')).toHaveAttribute('data-state', 'selected');
    await picker.continueToDetails();

    const summary = page.getByTestId('inline-order-summary');
    await expect(summary).toContainText('Programme');
    await expect(summary).toContainText('Car Parking');
    await expect(summary).toContainText('Stalls · A-10');
  });

  test('a single seat is named in the checkout summary when there are no attendee cards', async ({page, api, account}) => {
    const event = await createSeatedEvent(api, account.organizerId);
    await api.updateEventSettings(event.eventId, {attendee_details_collection_method: 'PER_ORDER'});
    const checkout = new CheckoutPage(page);
    await checkout.gotoPublicEvent(event.eventId, event.slug);

    const picker = new SeatPickerPage(page);
    await picker.selectSeat('e2.0.9');
    await picker.continueToDetails();

    await expect(page.getByTestId('inline-order-summary')).toContainText('Stalls · A-10');
    await expect(page.getByText('Attendee 1')).toHaveCount(0);
  });

  test('a seat map that fails to load shows a retry instead of plain ticket rows', async ({page, api, account}) => {
    const event = await createSeatedEvent(api, account.organizerId);
    let failing = true;
    await page.route(/\/public\/events\/\d+\/seat-map$/, route => failing
      ? route.fulfill({status: 500, contentType: 'application/json', body: '{"message":"Server Error"}'})
      : route.fallback());

    const checkout = new CheckoutPage(page);
    await checkout.gotoPublicEvent(event.eventId, event.slug);

    await expect(page.getByTestId('seat-map-load-error')).toBeVisible({timeout: 20_000});
    await expect(page.locator('.hi-product-row h3').filter({hasText: 'Premium Seat'})).toHaveCount(0);

    failing = false;
    await page.getByTestId('seat-map-retry-button').click();

    await expect(new SeatPickerPage(page).root()).toBeVisible();
    await expect(page.getByTestId('seat-map-load-error')).toHaveCount(0);
  });

  test('best available picks adjacent seats for the buyer', async ({page, api, account}) => {
    const event = await createSeatedEvent(api, account.organizerId);
    await new CheckoutPage(page).gotoPublicEvent(event.eventId, event.slug);

    const picker = new SeatPickerPage(page);
    await picker.bestAvailable();

    await expect(picker.basketLine('Stalls · A-10')).toBeVisible();
    await expect(picker.basketLine('Stalls · A-11')).toBeVisible();
    await expect(picker.continueButton()).toBeEnabled();
  });

  test('a seat taken by someone else while choosing is reported and can be replaced', async ({page, api, account, publicApi}) => {
    const event = await createSeatedEvent(api, account.organizerId);
    await new CheckoutPage(page).gotoPublicEvent(event.eventId, event.slug);

    const picker = new SeatPickerPage(page);
    await picker.selectSeats(FRONT_ROW_CENTRE);

    await takeSeats(publicApi, event, [FRONT_ROW_CENTRE[0]]);
    await picker.continueButton().click();

    await expect(picker.conflictNotice()).toContainText('Stalls · A-10');
    await expect(page).not.toHaveURL(/\/checkout\//);

    await picker.selectSeat('e2.0.11');
    await picker.continueToDetails();
  });

  test('seats can be chosen from the list view with the keyboard', async ({page, api, account}) => {
    const event = await createSeatedEvent(api, account.organizerId);
    await new CheckoutPage(page).gotoPublicEvent(event.eventId, event.slug);

    const picker = await new SeatPickerPage(page).openFullScreen();
    await picker.showListView();
    await picker.listSeat(/Stalls A–D/).click();

    const seat = picker.listSeat(/^A-10, Premium, available$/);
    await seat.focus();
    await page.keyboard.press('Enter');

    await expect(seat).toHaveAttribute('aria-pressed', 'true');
    await expect(picker.basketLine('Stalls · A-10')).toBeVisible();
  });

  test('a companion seat is only sold together with a wheelchair space', async ({page, api, account}) => {
    const event = await createSeatedEvent(api, account.organizerId, {bandKey: 'b_standard'});
    await makeCompanionSeat(api, event.eventId, COMPANION_SEAT);
    const checkout = new CheckoutPage(page);
    await checkout.gotoPublicEvent(event.eventId, event.slug);

    const picker = new SeatPickerPage(page);
    await picker.selectSeat(COMPANION_SEAT);
    await expect(page.getByTestId('seat-sheet-companion-notice')).toContainText('Choose a wheelchair space first');
    await expect(page.getByTestId('seat-sheet-confirm-button')).toBeDisabled();
    await page.getByRole('button', {name: 'Cancel'}).click();

    await picker.selectSeat(WHEELCHAIR_SPACE);
    await picker.confirmSeatSheet();
    await picker.selectSeat(COMPANION_SEAT);
    await picker.confirmSeatSheet();
    await expect(picker.basketLine('Stalls · W-2')).toBeVisible();

    await picker.selectSeat(WHEELCHAIR_SPACE);
    await expect(page.getByTestId('seat-companion-warning')).toBeVisible();
    await expect(picker.continueButton()).toBeDisabled();

    await picker.selectSeat(WHEELCHAIR_SPACE);
    await picker.confirmSeatSheet();
    await expect(page.getByTestId('seat-companion-warning')).toHaveCount(0);
    await picker.continueToDetails();

    await checkout.fillOrderAndAttendees({firstName: 'Wendy', lastName: 'Wheels', email: uniqueEmail('access')}, 2);
    await checkout.completeFreeOrder();

    await expect(page.getByText('Stalls · W-1').filter({visible: true}).first()).toBeVisible();
    await expect(page.getByText('Stalls · W-2').filter({visible: true}).first()).toBeVisible();
    const labels = (await api.listAttendees(event.eventId)).map(attendee => attendee.seat_label).sort();
    expect(labels).toEqual(['Stalls · W-1', 'Stalls · W-2']);
  });
});
