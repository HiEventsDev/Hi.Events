import {test, expect} from '../../fixtures';
import {CheckoutPage} from '../../pages/checkout.page';
import {SeatPickerPage} from '../../pages/seat-picker.page';
import {createSeatedEvent, type SeatedEvent} from '../../api/factory';
import type {ApiClient} from '../../api/api-client';
import type {CreateProductPayload} from '../../api/types';

async function addSeatedTicket(
  api: ApiClient,
  event: SeatedEvent,
  bandKey: string,
  payload: Omit<CreateProductPayload, 'product_type' | 'type' | 'product_category_id' | 'prices'> & {price?: number},
): Promise<{productId: number; priceId: number}> {
  const [category] = await api.listProductCategories(event.eventId);
  const {price = 0, ...rest} = payload;
  const created = await api.createProduct(event.eventId, {
    ...rest,
    product_type: 'TICKET',
    type: price > 0 ? 'PAID' : 'FREE',
    product_category_id: category.id,
    prices: [{price}],
  });
  const current = await api.getEventSeatMap(event.eventId);
  const bands = new Map(current.band_products.map(band => [band.band_key, band.products.map(link => ({product_id: link.product_id}))]));
  bands.set(bandKey, [...(bands.get(bandKey) ?? []), {product_id: created.id}]);
  await api.linkSeatMapBands(event.eventId, [...bands.entries()].map(([band_key, products]) => ({band_key, products})));
  return {productId: created.id, priceId: created.prices![0].id!};
}

test.describe('seated ticket details on the event page', () => {
  test.beforeEach(async ({adminApi, account}) => {
    await adminApi.setAccountFeatureFlag(await adminApi.findAccountIdByEmail(account.email), 'seating', true);
  });

  test('the price key shows one price per area, all prices on request, and descriptions where a ticket type is chosen', async ({page, api, account}) => {
    const event = await createSeatedEvent(api, account.organizerId);
    const child = await addSeatedTicket(api, event, 'b_premium', {
      title: 'Child Seat',
      description: '<p>Must be accompanied by an adult.</p>',
    });
    const [category] = await api.listProductCategories(event.eventId);

    await new CheckoutPage(page).gotoPublicEvent(event.eventId, event.slug);

    await expect(page.getByTestId('seated-category-heading')).toContainText(category.name);
    await expect(page.getByTestId('seat-legend-band-b_premium')).toContainText('Free');
    await expect(page.getByTestId('seat-legend-band-b_premium')).not.toContainText('From');
    await expect(page.getByTestId('seat-prices-all')).toHaveCount(0);

    await page.getByTestId('seat-prices-toggle').click();
    const allPrices = page.getByTestId('seat-prices-all');
    await expect(allPrices).toContainText('Child Seat');
    await expect(page.getByTestId(`seat-price-details-price-${child.priceId}`)).toContainText('Must be accompanied by an adult.');

    await new SeatPickerPage(page).selectSeat('e2.0.9');
    await expect(page.getByTestId('seat-line-ticket-type-e2.0.9')).toContainText('Must be accompanied by an adult.');
  });

  test('an area with ticket types at different prices is keyed from its cheapest price', async ({page, api, account}) => {
    const event = await createSeatedEvent(api, account.organizerId, {price: 25});
    await api.updateEventSettings(event.eventId, {pass_platform_fee_to_buyer: false});
    await addSeatedTicket(api, event, 'b_premium', {title: 'Child Seat', price: 10});
    await new CheckoutPage(page).gotoPublicEvent(event.eventId, event.slug);

    await expect(page.getByTestId('seat-legend-band-b_premium')).toContainText('From $10.00');
    await page.getByTestId('seat-prices-toggle').click();
    await expect(page.getByTestId('seat-prices-all')).toContainText('$25.00');
    await expect(page.getByTestId('seat-prices-all')).toContainText('$10.00');
  });

  test('a standing zone asks only how many and each place gets its own ticket type', async ({page, api, account}) => {
    const event = await createSeatedEvent(api, account.organizerId, {layout: 'club', bandKey: 'b_standard'});
    await addSeatedTicket(api, event, 'b_standard', {title: 'Child Seat'});
    await new CheckoutPage(page).gotoPublicEvent(event.eventId, event.slug);
    const picker = new SeatPickerPage(page);

    await picker.zone('z3').click();
    await expect(page.getByTestId('ticket-type-option-' + event.priceId)).toHaveCount(0);
    await expect(page.getByText('You can choose the ticket type for each place in your order.')).toBeVisible();
    await page.getByRole('button', {name: 'More places'}).click();
    await picker.confirmSeatSheet();

    const lines = page.getByTestId('seat-line-ticket-type-z3');
    await expect(lines).toHaveCount(2);
    await lines.nth(1).getByRole('radio', {name: /^Child Seat/}).click();
    await expect(lines.nth(1).getByRole('radio', {checked: true})).toHaveText(/^Child Seat/);
    await expect(lines.nth(0).getByRole('radio', {checked: true})).toHaveText(/^Premium Seat/);
  });

  test('the seat map area holds its place while the map loads', async ({page, api, account}) => {
    const event = await createSeatedEvent(api, account.organizerId);
    let release: () => void = () => undefined;
    const held = new Promise<void>(resolve => { release = resolve; });
    await page.route(/\/public\/events\/\d+\/seat-map$/, async route => {
      await held;
      await route.continue();
    });

    await page.goto(`/event/${event.eventId}/${event.slug}`);
    await expect(page.getByTestId('seat-map-loading')).toBeVisible();
    await expect(page.getByTestId('seated-products-section')).toHaveCount(0);

    release();
    await expect(page.getByTestId('seated-products-section')).toBeVisible();
    await expect(page.getByTestId('seat-map-loading')).toHaveCount(0);
  });

  test('a ticket type limit is enforced while picking and a minimum blocks continuing', async ({page, api, account}) => {
    const event = await createSeatedEvent(api, account.organizerId);
    await addSeatedTicket(api, event, 'b_standard', {title: 'Family Seat', max_per_order: 2, min_per_order: 2});
    await new CheckoutPage(page).gotoPublicEvent(event.eventId, event.slug);
    const picker = new SeatPickerPage(page);

    await picker.selectSeat('e3.0.0');
    await expect(page.getByTestId('seat-minimum-warning')).toContainText('Choose at least 2 Family Seat tickets');
    await expect(picker.continueButton()).toBeDisabled();

    await picker.selectSeat('e3.0.1');
    await expect(page.getByTestId('seat-minimum-warning')).toHaveCount(0);
    await expect(picker.continueButton()).toBeEnabled();

    await picker.seat('e3.0.2').click();
    await expect(page.getByText('You can choose up to 2 Family Seat tickets per order')).toBeVisible();
    await expect(picker.seat('e3.0.2')).not.toHaveAttribute('data-state', 'selected');
    await expect(picker.seatTotal(2)).toBeVisible();
  });

  test('a band whose tickets are not on sale yet says so instead of sold out', async ({page, api, account}) => {
    const event = await createSeatedEvent(api, account.organizerId);
    const nextYear = new Date(Date.now() + 365 * 24 * 60 * 60 * 1000).toISOString().slice(0, 19).replace('T', ' ');
    await addSeatedTicket(api, event, 'b_value', {title: 'Late Release', sale_start_date: nextYear});
    await new CheckoutPage(page).gotoPublicEvent(event.eventId, event.slug);

    await expect(page.getByTestId('seat-legend-band-b_value')).toContainText('Not yet on sale');
    await expect(page.getByTestId('seat-legend-band-b_premium')).not.toContainText('Not yet on sale');
  });

  test('a seat map with no tickets linked says its seats are not on sale yet', async ({page, api, account}) => {
    const event = await createSeatedEvent(api, account.organizerId);
    await api.linkSeatMapBands(event.eventId, []);
    await new CheckoutPage(page).gotoPublicEvent(event.eventId, event.slug);

    await expect(page.getByTestId('seated-products-section')).toContainText('Seats for this event are not on sale yet');
    await expect(page.getByTestId('seat-prices')).toHaveCount(0);
    await expect(page.getByTestId('best-available-button')).toHaveCount(0);
  });

  test('the page continue button carries the order total once seats are chosen', async ({page, api, account}) => {
    const event = await createSeatedEvent(api, account.organizerId, {price: 25});
    await api.updateEventSettings(event.eventId, {pass_platform_fee_to_buyer: false});
    await new CheckoutPage(page).gotoPublicEvent(event.eventId, event.slug);
    const picker = new SeatPickerPage(page);

    await expect(page.getByTestId('checkout-continue-button')).not.toContainText('$');
    await picker.selectSeats(['e2.0.9', 'e2.0.10']);
    await expect(page.getByTestId('checkout-continue-button')).toContainText('$50.00');
    await expect(page.getByTestId('seat-basket-total')).toHaveText('$50.00');
  });
});
