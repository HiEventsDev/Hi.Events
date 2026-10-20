import type {APIRequestContext, Page} from '@playwright/test';
import {test, expect} from '../../fixtures';
import type {ApiClient, SeatMapLayout} from '../../api/api-client';
import {createSeatedEvent, createSeatedOrder, futureStartDate} from '../../api/factory';
import {CheckoutPage} from '../../pages/checkout.page';
import {ProductEditPage} from '../../pages/product-edit.page';
import {SeatingSettingsPage} from '../../pages/seating-settings.page';
import {SeatMapDesignerPage} from '../../pages/seat-map-designer.page';
import {uniqueName} from '../../utils/unique';

const SOLD_SEAT = 'e2.0.9';

type SeatedElement = {id: string; seats?: ({uid: string} & Record<string, unknown>)[]; overrides?: Record<string, unknown>} & Record<string, unknown>;

async function sourceSeatMap(api: ApiClient, organizerId: number, eventId: number): Promise<{id: number; layout: SeatMapLayout}> {
  const id = (await api.getEventSeatMap(eventId)).source_seat_map!.id;
  const {layout} = await api.getSeatMap(organizerId, id);
  return {id, layout: layout as unknown as SeatMapLayout};
}

const stallsElements = (layout: SeatMapLayout) => layout.areas[0].elements as SeatedElement[];

const withExtraRow = (layout: SeatMapLayout): SeatMapLayout => {
  const copy = structuredClone(layout);
  stallsElements(copy).push({
    id: 'e20',
    type: 'row',
    name: 'Extra',
    x: 520,
    y: 700,
    rotation: 0,
    count: 1,
    spacing: 42,
    curve: 0,
    aisles: [],
    rowLabelStyle: 'alpha',
    startRow: 'Z',
    startSeat: 1,
    numbering: 'seq',
    band: 'b_premium',
    seatSize: 18,
    overrides: {},
    seats: [{uid: 'e20.0.0', row: 0, n: '1', label: 'Z-1', x: 520, y: 700, a: 0, band: 'b_premium', acc: false, note: null, gapAfter: false}],
  });
  return copy;
};

const withoutSeat = (layout: SeatMapLayout, uid: string): SeatMapLayout => {
  const copy = structuredClone(layout);
  const [elementId, row, index] = uid.split('.');
  const element = stallsElements(copy).find(candidate => candidate.id === elementId)!;
  element.seats = element.seats!.filter(candidate => candidate.uid !== uid);
  element.overrides = {...element.overrides, [`${row}.${index}`]: {removed: true}};
  return copy;
};

async function publicEvent(publicApi: APIRequestContext, eventId: number): Promise<{title: string; slug: string}> {
  const response = await publicApi.get(`public/events/${eventId}`);
  expect(response.ok()).toBe(true);
  return (await response.json()).data;
}

async function confirmDialog(page: Page): Promise<void> {
  await page.getByRole('dialog').getByRole('button', {name: 'Confirm'}).click();
}

test.describe('seating management', () => {
  test.beforeEach(async ({adminApi, account}) => {
    await adminApi.setAccountFeatureFlag(await adminApi.findAccountIdByEmail(account.email), 'seating', true);
  });

  test('a ticket with sold seats cannot be unlinked from its band', async ({authedPage: page, api, account, publicApi}) => {
    const event = await createSeatedEvent(api, account.organizerId);
    await createSeatedOrder(publicApi, event, [SOLD_SEAT]);

    const seating = new SeatingSettingsPage(page);
    await seating.goto(event.eventId);
    await expect(seating.bandPill('Premium Seat')).toBeVisible();
    await seating.toggleBandProducts('b_premium', ['Premium Seat']);
    await expect(seating.bandPill('Premium Seat')).toHaveCount(0);
    await seating.saveBands();

    await expect(page.getByText(/so their tickets cannot be unlinked: b_premium/)).toBeVisible();
    await page.reload();
    await expect(seating.bandPill('Premium Seat')).toBeVisible();
    expect((await api.getEventSeatMap(event.eventId)).band_products).toEqual([{ band_key: 'b_premium', products: [{ product_id: event.productId, price_adjustment: 0 }] }]);
  });

  test('the seat map cannot be removed once a seat is sold', async ({authedPage: page, api, account, publicApi}) => {
    const event = await createSeatedEvent(api, account.organizerId);
    await createSeatedOrder(publicApi, event, [SOLD_SEAT]);

    const seating = new SeatingSettingsPage(page);
    await seating.goto(event.eventId);
    await seating.detach();

    await expect(page.getByText('Seats are sold or held on an upcoming date, so the seat map cannot be removed')).toBeVisible();
    await page.reload();
    await expect(seating.detachButton()).toBeVisible();
    await expect((await api.getEventSeatMap(event.eventId)).layout.areas).not.toHaveLength(0);
  });

  test('removing an unsold seat map puts the ticket back on sale by quantity', async ({authedPage: page, api, account}) => {
    const event = await createSeatedEvent(api, account.organizerId);

    const seating = new SeatingSettingsPage(page);
    await seating.goto(event.eventId);
    await seating.detach();

    await expect(page.getByText('Seat map removed')).toBeVisible();
    await expect(seating.attachButton()).toBeVisible();

    const checkout = new CheckoutPage(page);
    await checkout.gotoPublicEventAfterAvailabilityCacheExpires(event.eventId, event.slug);
    await expect(page.locator('.hi-product-row h3').filter({hasText: 'Premium Seat'})).toBeVisible();
    await expect(page.getByTestId('seated-products-section')).toHaveCount(0);
  });

  test('the event seat designer refuses to remove a sold seat but saves a harmless edit', async ({authedPage: page, api, account, publicApi}) => {
    const event = await createSeatedEvent(api, account.organizerId);
    await createSeatedOrder(publicApi, event, [SOLD_SEAT]);

    const designer = new SeatMapDesignerPage(page);
    await designer.gotoEventDesigner(event.eventId);
    const initialSeats = await designer.readSeatCount();

    await designer.chooseTool('seats');
    await designer.seat(SOLD_SEAT).click();
    await designer.removeSelectedSeats();
    await designer.expectSeatCount(initialSeats - 1);
    await designer.save();
    await expect(page.getByText(/cannot be removed or moved to another band: Stalls · A-10/)).toBeVisible();

    await designer.undo();
    await designer.expectSeatCount(initialSeats);
    await designer.seat('e2.3.0').click();
    await designer.removeSelectedSeats();
    await designer.save();
    await expect(page.getByText('Seat map saved')).toBeVisible();

    await page.reload();
    await designer.expectSeatCount(initialSeats - 1);
    await expect(designer.seat(SOLD_SEAT)).toBeVisible();
    await expect(designer.seat('e2.3.0')).toHaveCount(0);
  });

  test('changes to the venue seat map are reviewed and synced to the event', async ({authedPage: page, api, account}) => {
    const event = await createSeatedEvent(api, account.organizerId);
    const source = await sourceSeatMap(api, account.organizerId, event.eventId);
    await api.updateSeatMap(account.organizerId, source.id, 'Main auditorium', withExtraRow(source.layout));

    const seating = new SeatingSettingsPage(page);
    await seating.goto(event.eventId);
    const diff = await seating.reviewSourceChanges();
    await expect(diff.getByText('1 seats added')).toBeVisible();
    await expect(diff.getByText('0 seats removed')).toBeVisible();
    await seating.applySourceChanges();

    await expect(page.getByText('Seat map updated')).toBeVisible();
    await expect(seating.syncButton()).toHaveCount(0);

    const checkout = new CheckoutPage(page);
    await checkout.gotoPublicEvent(event.eventId, event.slug);
    const newSeat = page.getByTestId('seated-products-section').locator('[data-uid="e20.0.0"]');
    await expect(newSeat).toHaveAttribute('data-state', 'free');
  });

  test('a venue seat map sync that would remove a sold seat is refused', async ({authedPage: page, api, account, publicApi}) => {
    const event = await createSeatedEvent(api, account.organizerId);
    await createSeatedOrder(publicApi, event, [SOLD_SEAT]);
    const source = await sourceSeatMap(api, account.organizerId, event.eventId);
    await api.updateSeatMap(account.organizerId, source.id, 'Main auditorium', withoutSeat(source.layout, SOLD_SEAT));

    const seating = new SeatingSettingsPage(page);
    await seating.goto(event.eventId);
    const diff = await seating.reviewSourceChanges();
    await expect(diff.getByText('1 seats removed')).toBeVisible();
    await expect(diff.getByText('A-10')).toBeVisible();
    await seating.applySourceChanges();

    await expect(page.getByText(/cannot be removed or moved to another band: Stalls · A-10/)).toBeVisible();
    await page.reload();
    await expect(seating.syncButton()).toBeVisible();
    const seats = stallsElements((await api.getEventSeatMap(event.eventId)).layout).flatMap(element => element.seats ?? []);
    expect(seats.map(candidate => candidate.uid)).toContain(SOLD_SEAT);
  });

  test('a seated ticket cannot be turned into a donation', async ({authedPage: page, api, account}) => {
    const event = await createSeatedEvent(api, account.organizerId);

    const products = new ProductEditPage(page);
    await products.goto(event.eventId);
    await products.openFirstProduct();
    await page.getByTestId('product-price-type').getByText('Donation', {exact: true}).click();
    await products.clickSubmit();

    await expect(page.getByText('Unlink this ticket from the seat map before making it a donation or a non-ticket product')).toBeVisible();
    expect((await api.getProduct(event.eventId, event.productId)).type).toBe('FREE');
  });

  test('deleting a venue seat map leaves events using a copy on sale', async ({authedPage: page, api, account}) => {
    const event = await createSeatedEvent(api, account.organizerId);
    const seatMapId = (await api.getEventSeatMap(event.eventId)).source_seat_map!.id;

    await page.goto(`/manage/organizer/${account.organizerId}/seat-maps`);
    await page.getByTestId(`seat-map-delete-button-${seatMapId}`).click();
    await confirmDialog(page);
    await expect(page.getByText('Seat map deleted')).toBeVisible();
    await expect(page.getByTestId(`seat-map-delete-button-${seatMapId}`)).toHaveCount(0);
    await page.reload();
    await expect(page.getByTestId('seat-map-create-button')).toBeVisible();
    await expect(page.getByTestId(`seat-map-delete-button-${seatMapId}`)).toHaveCount(0);

    const checkout = new CheckoutPage(page);
    await checkout.gotoPublicEvent(event.eventId, event.slug);
    await page.getByTestId('seated-products-section').locator(`[data-uid="${SOLD_SEAT}"]`).click();
    await checkout.continueToCheckout();
    await expect(page.getByText('Premium Seat · Stalls · A-10')).toBeVisible();
  });

  test('duplicating a seated event copies the seat map and ticket links but no sold seats', async ({authedPage: page, api, account, publicApi}) => {
    const event = await createSeatedEvent(api, account.organizerId);
    await createSeatedOrder(publicApi, event, [SOLD_SEAT]);
    const copyId = (await api.duplicateEvent(event.eventId, uniqueName('E2E Seated Copy'), futureStartDate())).id;

    const seating = new SeatingSettingsPage(page);
    await seating.goto(copyId);
    await expect(page.getByText('Based on Main auditorium. This event keeps its own copy.')).toBeVisible();
    await expect(seating.bandPill('Premium Seat')).toBeVisible();
    await expect(seating.attachButton()).toHaveCount(0);

    await api.publishEvent(copyId);
    const copy = await publicEvent(publicApi, copyId);
    const checkout = new CheckoutPage(page);

    await checkout.gotoPublicEvent(event.eventId, event.slug);
    await expect(page.getByTestId('seated-products-section').locator(`[data-uid="${SOLD_SEAT}"]`)).not.toHaveAttribute('data-state', 'free');

    await checkout.gotoPublicEvent(copyId, copy.slug);
    const copyMap = page.getByTestId('seated-products-section');
    await expect(copyMap.locator(`[data-uid="${SOLD_SEAT}"]`)).toHaveAttribute('data-state', 'free');
    await expect(copyMap.locator('[data-uid^="e2."]')).toHaveCount(86);
    await expect(copyMap.locator('[data-uid^="e2."]:not([data-state="free"])')).toHaveCount(0);
  });

  test('seat selection rules edited on the seating page persist', async ({authedPage: page, api, account}) => {
    const event = await createSeatedEvent(api, account.organizerId);

    const seating = new SeatingSettingsPage(page);
    await seating.goto(event.eventId);
    await expect(seating.preventOrphansSwitch()).not.toBeChecked();
    await expect(seating.allowSeatChangeSwitch()).not.toBeChecked();

    await seating.setRules({preventOrphans: true, maxSeatsPerOrder: 4, allowSeatChange: true});
    await seating.saveRules();
    await expect(page.getByText('Rules saved')).toBeVisible();

    await page.reload();
    await expect(seating.preventOrphansSwitch()).toBeChecked();
    await expect(seating.maxSeatsInput()).toHaveValue('4');
    await expect(seating.allowSeatChangeSwitch()).toBeChecked();

    await seating.setRules({preventOrphans: false, maxSeatsPerOrder: null});
    await seating.saveRules();
    await expect(page.getByText('Rules saved')).toBeVisible();

    await page.reload();
    await expect(seating.preventOrphansSwitch()).not.toBeChecked();
    await expect(seating.maxSeatsInput()).toHaveValue('');
    await expect(seating.allowSeatChangeSwitch()).toBeChecked();
  });

  test('saving the rules card keeps an unsaved band price edit', async ({authedPage: page, api, account}) => {
    const event = await createSeatedEvent(api, account.organizerId, {price: 30});

    const seating = new SeatingSettingsPage(page);
    await seating.goto(event.eventId);
    await seating.setBandPrice('b_premium', event.productId, 42);

    await seating.setRules({maxSeatsPerOrder: 6});
    await seating.saveRules();
    await expect(page.getByText('Rules saved')).toBeVisible();
    await expect(seating.bandPriceInput('b_premium', event.productId)).toHaveValue('$42.00');

    await seating.saveBands();
    await expect(page.getByText('Tickets linked')).toBeVisible();

    await page.reload();
    await expect(seating.maxSeatsInput()).toHaveValue('6');
    await expect(seating.bandPriceInput('b_premium', event.productId)).toHaveValue('$42.00');
  });
});
