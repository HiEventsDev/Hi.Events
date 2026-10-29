import type {Page} from '@playwright/test';
import {test, expect} from '../../fixtures';
import {createSeatedEvent, defaultBoxOffice, enableOfflinePayments, type SeatedEvent} from '../../api/factory';
import type {ApiClient} from '../../api/api-client';
import type {Occurrence} from '../../api/types';
import {BoxOfficePage} from '../../pages/box-office.page';
import {CheckoutPage} from '../../pages/checkout.page';
import {PublicOccurrenceSelector} from '../../pages/occurrence.page';
import {SeatPickerPage} from '../../pages/seat-picker.page';
import {SeatingSettingsPage} from '../../pages/seating-settings.page';
import {uniqueCode, uniqueEmail} from '../../utils/unique';

const BASE_PRICE = 30;
const PREMIUM_ADJUSTMENT = 15;
const PREMIUM_SEAT = 'e2.0.0';
const PREMIUM_LABEL = 'Stalls · A-1';
const STANDARD_SEAT = 'e3.0.0';
const STANDARD_LABEL = 'Stalls · E-1';

const money = (amount: number): string => `$${amount.toFixed(2)}`;

async function createBandedEvent(api: ApiClient, organizerId: number, recurringCount?: number): Promise<SeatedEvent & {occurrences: Occurrence[]}> {
  const event = await createSeatedEvent(api, organizerId, {price: BASE_PRICE, recurringCount});
  await api.linkSeatMapBands(event.eventId, [
    {band_key: 'b_premium', products: [{product_id: event.productId, price_adjustment: PREMIUM_ADJUSTMENT * 100}]},
    {band_key: 'b_standard', products: [{product_id: event.productId, price_adjustment: 0}]},
  ]);
  await api.updateEventSettings(event.eventId, {pass_platform_fee_to_buyer: false});
  await enableOfflinePayments(api, event.eventId);
  return event;
}

async function payOffline(checkout: CheckoutPage): Promise<void> {
  await checkout.continueToPayment();
  await checkout.chooseOfflinePayment();
}

async function pickOneSeatInEachBand(page: Page, premiumPrice: number, standardPrice: number): Promise<SeatPickerPage> {
  const picker = new SeatPickerPage(page);
  await picker.selectSeats([PREMIUM_SEAT, STANDARD_SEAT]);
  await expect(picker.root().getByText(`Premium Seat · ${money(premiumPrice)}`)).toBeVisible();
  await expect(picker.root().getByText(`Premium Seat · ${money(standardPrice)}`)).toBeVisible();
  await expect(picker.seatTotal(2).locator('..')).toContainText(money(premiumPrice + standardPrice));
  return picker;
}

async function expectConfirmationTotal(page: Page, total: number): Promise<void> {
  await expect(page.getByText('Your order is awaiting payment')).toBeVisible();
  await expect(page.getByText(money(total)).filter({visible: true}).first()).toBeVisible();
  await expect(page.getByText(PREMIUM_LABEL).filter({visible: true}).first()).toBeVisible();
  await expect(page.getByText(STANDARD_LABEL).filter({visible: true}).first()).toBeVisible();
}

test.describe('price bands', () => {
  test.beforeEach(async ({adminApi, account}) => {
    await adminApi.setAccountFeatureFlag(await adminApi.findAccountIdByEmail(account.email), 'seating', true);
  });

  test('two bands on one ticket type are priced by band through checkout and each attendee keeps their seat', {tag: '@smoke'}, async ({page, api, account}) => {
    const event = await createBandedEvent(api, account.organizerId);
    const premiumPrice = BASE_PRICE + PREMIUM_ADJUSTMENT;
    const checkout = new CheckoutPage(page);
    await checkout.gotoPublicEvent(event.eventId, event.slug);
    await expect(page.getByTestId('seat-legend-band-b_premium')).toContainText('86 left');
    await expect(page.getByTestId('seat-legend-band-b_standard')).toContainText('241 left');
    await expect(page.getByTestId('seat-legend-band-b_premium')).toContainText(money(premiumPrice));
    await expect(page.getByTestId('seat-legend-band-b_standard')).toContainText(money(BASE_PRICE));

    const picker = await pickOneSeatInEachBand(page, premiumPrice, BASE_PRICE);
    await picker.continueToDetails();
    await expect(page.getByTestId('inline-order-summary')).toContainText(money(premiumPrice + BASE_PRICE));

    const cardLabels = await page.getByText(/^Premium Seat · Stalls · /).allInnerTexts();
    expect([...cardLabels].sort()).toEqual([`Premium Seat · ${PREMIUM_LABEL}`, `Premium Seat · ${STANDARD_LABEL}`]);
    const email = uniqueEmail('bands');
    await checkout.fillOrderDetails({firstName: 'Order', lastName: 'Owner', email});
    for (const [index, label] of cardLabels.entries()) {
      await checkout.fillAttendee(index + 1, {firstName: label.endsWith(PREMIUM_LABEL) ? 'Premium' : 'Standard', lastName: 'Guest', email});
    }
    await payOffline(checkout);
    await expectConfirmationTotal(page, premiumPrice + BASE_PRICE);

    const seatByName = new Map((await api.listAttendees(event.eventId)).map(attendee => [attendee.first_name, attendee.seat_label]));
    expect(seatByName.get('Premium')).toBe(PREMIUM_LABEL);
    expect(seatByName.get('Standard')).toBe(STANDARD_LABEL);
  });

  test('an organizer prices a band on the seating page and buyers see it', async ({authedPage, page, api, account}) => {
    const event = await createSeatedEvent(api, account.organizerId, {price: BASE_PRICE});
    await api.linkSeatMapBands(event.eventId, [
      {band_key: 'b_premium', products: [{product_id: event.productId}]},
      {band_key: 'b_standard', products: [{product_id: event.productId}]},
    ]);
    await api.updateEventSettings(event.eventId, {pass_platform_fee_to_buyer: false});

    const seating = new SeatingSettingsPage(authedPage);
    await seating.goto(event.eventId);
    await seating.setBandPrice('b_premium', event.productId, BASE_PRICE + PREMIUM_ADJUSTMENT);
    await seating.setBandPrice('b_standard', event.productId, 20);
    await seating.resetBandPrice('b_standard', event.productId);
    await seating.saveBands();
    await expect(authedPage.getByText('Tickets linked')).toBeVisible();

    await authedPage.reload();
    await expect(seating.bandPriceInput('b_premium', event.productId)).toHaveValue(money(BASE_PRICE + PREMIUM_ADJUSTMENT));
    await expect(seating.bandPriceInput('b_standard', event.productId)).toHaveCount(0);

    await new CheckoutPage(page).gotoPublicEvent(event.eventId, event.slug);
    await expect(page.getByTestId('seat-legend-band-b_premium')).toContainText(money(BASE_PRICE + PREMIUM_ADJUSTMENT));
    await expect(page.getByTestId('seat-legend-band-b_standard')).toContainText(money(BASE_PRICE));
  });

  test('a zero-decimal currency band price is stored and shown exactly as the organizer typed it', async ({authedPage, page, api, account}) => {
    const basePrice = 3000;
    const premiumPrice = 4500;
    const event = await createSeatedEvent(api, account.organizerId, {price: basePrice, currency: 'JPY'});
    await api.linkSeatMapBands(event.eventId, [
      {band_key: 'b_premium', products: [{product_id: event.productId}]},
      {band_key: 'b_standard', products: [{product_id: event.productId}]},
    ]);
    await api.updateEventSettings(event.eventId, {pass_platform_fee_to_buyer: false});

    const seating = new SeatingSettingsPage(authedPage);
    await seating.goto(event.eventId);
    await seating.setBandPrice('b_premium', event.productId, premiumPrice, 0);
    await seating.saveBands();
    await expect(authedPage.getByText('Tickets linked')).toBeVisible();

    const {band_products} = await api.getEventSeatMap(event.eventId);
    const premiumLink = band_products.find(band => band.band_key === 'b_premium')!.products[0];
    expect(premiumLink.price_adjustment).toBe(premiumPrice - basePrice);

    await authedPage.reload();
    await expect(seating.bandPriceInput('b_premium', event.productId)).toHaveValue(`¥${premiumPrice}`);

    await new CheckoutPage(page).gotoPublicEvent(event.eventId, event.slug);
    const section = page.getByTestId('seated-products-section');
    await expect(section).toContainText(/¥4,500(?!\d)/);
    await expect(section).toContainText(/¥3,000(?!\d)/);

    const picker = new SeatPickerPage(page);
    await picker.selectSeat(PREMIUM_SEAT);
    await expect(picker.seatTotal(1).locator('..')).toContainText(/¥4,500(?!\d)/);
  });

  test('the floating continue button totals seats at their band price', async ({page, api, account}) => {
    const event = await createBandedEvent(api, account.organizerId);
    const checkout = new CheckoutPage(page);
    await checkout.gotoPublicEvent(event.eventId, event.slug);

    const picker = new SeatPickerPage(page);
    await picker.selectSeats([PREMIUM_SEAT, STANDARD_SEAT]);
    await page.evaluate(() => window.scrollTo(0, 0));

    const total = BASE_PRICE * 2 + PREMIUM_ADJUSTMENT;
    await expect(page.getByTestId('floating-checkout-button')).toContainText(`(${money(total)})`);
  });

  test('two bands on one ticket type complete with one set of order details', async ({page, api, account}) => {
    const event = await createBandedEvent(api, account.organizerId);
    await api.updateEventSettings(event.eventId, {attendee_details_collection_method: 'PER_ORDER'});
    const premiumPrice = BASE_PRICE + PREMIUM_ADJUSTMENT;
    const checkout = new CheckoutPage(page);
    await checkout.gotoPublicEvent(event.eventId, event.slug);

    const picker = await pickOneSeatInEachBand(page, premiumPrice, BASE_PRICE);
    await picker.continueToDetails();
    await expect(page.getByTestId('inline-order-summary')).toContainText(`${PREMIUM_LABEL}`);
    await expect(page.getByTestId('inline-order-summary')).toContainText(`${STANDARD_LABEL}`);
    await expect(page.getByTestId('inline-order-summary')).toContainText(money(premiumPrice + BASE_PRICE));

    await checkout.fillOrderDetails({firstName: 'Per', lastName: 'Order', email: uniqueEmail('perorder')});
    await payOffline(checkout);
    await expectConfirmationTotal(page, premiumPrice + BASE_PRICE);

    const labels = (await api.listAttendees(event.eventId)).map(attendee => attendee.seat_label).sort();
    expect(labels).toEqual([PREMIUM_LABEL, STANDARD_LABEL]);
    const [order] = await api.listOrders(event.eventId);
    expect(Number(order.total_gross)).toBe(premiumPrice + BASE_PRICE);
  });

  test('a promo code is taken off the band price', async ({page, api, account}) => {
    const event = await createBandedEvent(api, account.organizerId);
    const code = uniqueCode('BAND');
    await api.createPromoCode(event.eventId, {code, discount_type: 'PERCENTAGE', discount: 20, applicable_product_ids: []});
    const premiumPrice = (BASE_PRICE + PREMIUM_ADJUSTMENT) * 0.8;
    const standardPrice = BASE_PRICE * 0.8;

    const checkout = new CheckoutPage(page);
    await checkout.gotoPublicEvent(event.eventId, event.slug);
    await checkout.applyPromoCode(code);
    await expect(page.getByText(code, {exact: true})).toBeVisible();

    const picker = await pickOneSeatInEachBand(page, premiumPrice, standardPrice);
    await picker.continueToDetails();
    await expect(page.getByTestId('inline-order-summary')).toContainText(money(premiumPrice + standardPrice));

    await checkout.fillOrderAndAttendees({firstName: 'Promo', lastName: 'Buyer', email: uniqueEmail('bandpromo')}, 2);
    await payOffline(checkout);
    await expectConfirmationTotal(page, premiumPrice + standardPrice);
  });

  test('a per-date price is the base the band adjustment is added to', async ({page, api, account}) => {
    const event = await createBandedEvent(api, account.organizerId, 2);
    const [, secondDate] = event.occurrences;
    const secondDatePrice = 40;
    await api.setOccurrencePriceOverride(event.eventId, secondDate.id, {product_price_id: event.priceId, price: secondDatePrice});

    const checkout = new CheckoutPage(page);
    await checkout.gotoPublicEvent(event.eventId, event.slug);
    const dates = new PublicOccurrenceSelector(page);
    await dates.selectDay(secondDate.start_date);
    await expect(dates.productsLoadingOverlay()).toHaveCount(0);

    const picker = await pickOneSeatInEachBand(page, secondDatePrice + PREMIUM_ADJUSTMENT, secondDatePrice);
    await picker.continueToDetails();
    await expect(page.getByTestId('inline-order-summary')).toContainText(money(secondDatePrice * 2 + PREMIUM_ADJUSTMENT));
  });

  test('the box office charges each seat at its band price', async ({page, api, account}) => {
    const event = await createBandedEvent(api, account.organizerId);
    const boxOffice = await defaultBoxOffice(api, event.eventId);
    const door = new BoxOfficePage(page);
    await door.goto(boxOffice.short_id);
    await door.startSession('Sam', boxOffice.pin);

    await door.seat(PREMIUM_SEAT).click();
    await expect(door.cartLine(event.priceId)).toContainText(money(BASE_PRICE + PREMIUM_ADJUSTMENT));
    await door.seat(STANDARD_SEAT).click();
    const total = BASE_PRICE * 2 + PREMIUM_ADJUSTMENT;
    await expect(door.cartLine(event.priceId)).toContainText(money(total));
    await expect(door.chargeButton()).toHaveText(`Charge ${money(total)}`);

    await door.charge();
    await door.payCash();
    await expect(door.saleCompleteHeading()).toBeVisible();

    const [order] = await api.listOrders(event.eventId);
    expect(Number(order.total_gross)).toBe(total);
    const labels = (await api.listAttendees(event.eventId)).map(attendee => attendee.seat_label).sort();
    expect(labels).toEqual([PREMIUM_LABEL, STANDARD_LABEL]);
  });
});
