import type { APIRequestContext, Page } from '@playwright/test';
import { test, expect } from '../../fixtures';
import { BoxOfficePage } from '../../pages/box-office.page';
import { createSeatedEvent, defaultBoxOffice, type SeatedEvent } from '../../api/factory';
import type { ApiClient } from '../../api/api-client';

async function addSeatedTicket(api: ApiClient, event: SeatedEvent, title: string): Promise<{ productId: number; priceId: number }> {
  const [category] = await api.listProductCategories(event.eventId);
  const created = await api.createProduct(event.eventId, {
    title,
    product_type: 'TICKET',
    type: 'FREE',
    product_category_id: category.id,
    prices: [{ price: 0 }],
  });
  await api.linkSeatMapBands(event.eventId, [{ band_key: 'b_premium', products: [{ product_id: event.productId }, { product_id: created.id }] }]);
  return { productId: created.id, priceId: created.prices![0].id! };
}

async function openDoor(page: Page, api: ApiClient, eventId: number): Promise<BoxOfficePage> {
  const boxOffice = await defaultBoxOffice(api, eventId);
  const door = new BoxOfficePage(page);
  await door.goto(boxOffice.short_id);
  await door.startSession('Sam', boxOffice.pin);
  await expect(door.seatMap()).toBeVisible();
  return door;
}

async function completeFreeSale(page: Page, door: BoxOfficePage): Promise<void> {
  await door.charge();
  await page.getByRole('button', { name: 'Complete sale' }).click();
  await expect(door.saleCompleteHeading()).toBeVisible();
}

async function takeSeatOnline(publicApi: APIRequestContext, event: SeatedEvent, seatUid: string): Promise<void> {
  const response = await publicApi.post(`public/events/${event.eventId}/order`, {
    data: {
      products: [{
        product_id: event.productId,
        event_occurrence_id: event.occurrenceId,
        quantities: [{ price_id: event.priceId, quantity: 1, seat_uids: [seatUid] }],
      }],
    },
  });
  expect(response.status()).toBe(201);
}

const seatNumbers = (labels: string[]): number[] => labels.map((label) => Number(/(\d+)$/.exec(label)![1])).sort((a, b) => a - b);

const expectSideBySide = (labels: string[]): void => {
  expect(new Set(labels.map((label) => label.replace(/-\d+$/, ''))).size).toBe(1);
  const numbers = seatNumbers(labels);
  expect(numbers).toEqual(numbers.map((_, offset) => numbers[0] + offset));
};

test.describe('box office reserved seating', () => {
  test.beforeEach(async ({ adminApi, account }) => {
    await adminApi.setAccountFeatureFlag(await adminApi.findAccountIdByEmail(account.email), 'seating', true);
  });

  test('door staff set a count and let best available seat the party together', { tag: '@smoke' }, async ({ page, api, account }) => {
    const event = await createSeatedEvent(api, account.organizerId);
    const door = await openDoor(page, api, event.eventId);

    await door.addProduct(event.priceId, 2);
    await expect(door.seatsToChoose()).toHaveText('Choose 2 seats');
    await expect(door.chargeButton()).toBeDisabled();
    await expect(door.chargeButton()).toHaveText('Choose 2 more seats');

    await door.bestAvailable();
    await expect(door.cartSeats(event.priceId).getByRole('button', { name: /Remove seat/ })).toHaveCount(2);
    await expect(door.chargeButton()).toBeEnabled();
    await completeFreeSale(page, door);

    const labels = (await api.listAttendees(event.eventId)).map((attendee) => attendee.seat_label!);
    expect(labels).toHaveLength(2);
    expectSideBySide(labels);
  });

  test('two ticket types in one band are seated side by side', async ({ page, api, account }) => {
    const event = await createSeatedEvent(api, account.organizerId);
    const child = await addSeatedTicket(api, event, 'Child Seat');
    const door = await openDoor(page, api, event.eventId);

    await door.addProduct(event.priceId, 2);
    await door.addProduct(child.priceId, 1);
    await expect(door.seatsToChoose()).toHaveText('Choose 3 seats');
    await door.bestAvailable();
    await expect(door.cartSeats(child.priceId).getByRole('button', { name: /Remove seat/ })).toHaveCount(1);
    await completeFreeSale(page, door);

    const attendees = await api.listAttendees(event.eventId);
    expect(attendees.filter((attendee) => attendee.product_id === child.productId)).toHaveLength(1);
    expectSideBySide(attendees.map((attendee) => attendee.seat_label!));
  });

  test('tapping seats with no count adds tickets, asking which type when the band has several', async ({ page, api, account }) => {
    const event = await createSeatedEvent(api, account.organizerId);
    const door = await openDoor(page, api, event.eventId);

    await door.seat('e2.0.0').click();
    await expect(door.cartSeats(event.priceId)).toContainText('Stalls · A-1');
    await expect(door.cartLine(event.priceId)).toContainText('1×');

    const child = await addSeatedTicket(api, event, 'Child Seat');
    await page.reload();
    await door.seat('e2.0.1').click();
    await page.getByTestId(`box-office-seat-type-${child.priceId}`).click();
    await expect(door.cartSeats(child.priceId)).toContainText('Stalls · A-2');
    await expect(door.chargeButton()).toBeEnabled();
  });

  test('unseating a seat keeps the ticket open until another seat is chosen', async ({ page, api, account }) => {
    const event = await createSeatedEvent(api, account.organizerId);
    const door = await openDoor(page, api, event.eventId);

    await door.addProduct(event.priceId, 1);
    await door.seat('e2.0.4').click();
    await expect(door.chargeButton()).toBeEnabled();

    await door.seat('e2.0.4').click();
    await expect(door.chargeButton()).toHaveText('Choose 1 more seat');
    await expect(door.cartLine(event.priceId)).toContainText('1×');

    await door.seat('e2.0.5').click();
    await page.getByTestId('box-office-seat-chip-remove-e2.0.5').click();
    await expect(door.seatsToChoose()).toHaveText('Choose 1 seat');
  });

  test('a held-back seat is only sold after the operator confirms', async ({ page, api, account }) => {
    const event = await createSeatedEvent(api, account.organizerId);
    await api.blockSeats(event.eventId, event.occurrenceId, ['e2.0.10'], 'House seat');
    const door = await openDoor(page, api, event.eventId);

    await door.seat('e2.0.10').click();
    const dialog = page.getByRole('dialog', { name: 'This seat is held back' });
    await expect(dialog).toContainText('Stalls · A-11 · House seat');
    await dialog.getByRole('button', { name: 'Cancel' }).click();
    await expect(door.chargeButton()).toHaveCount(0);

    await door.seat('e2.0.10').click();
    await page.getByTestId('box-office-held-seat-confirm').click();
    await completeFreeSale(page, door);

    const [attendee] = await api.listAttendees(event.eventId);
    expect(attendee.seat_label).toBe('Stalls · A-11');
  });

  test('going back from payment on a held-back seat leaves it held back', async ({ page, api, account }) => {
    const event = await createSeatedEvent(api, account.organizerId, { price: 30 });
    await api.blockSeats(event.eventId, event.occurrenceId, ['e2.0.10'], 'House seat');
    const door = await openDoor(page, api, event.eventId);

    await door.seat('e2.0.10').click();
    await page.getByTestId('box-office-held-seat-confirm').click();
    await door.charge();
    await expect(page.getByTestId('box-office-tender-cash')).toBeVisible();
    await expect.poll(async () => (await api.occupiedSeats(event.eventId, event.occurrenceId)).map((seat) => seat.status)).toEqual(['HELD']);

    await page.getByRole('button', { name: 'Back to sale' }).click();
    await expect(door.chargeButton()).toBeEnabled();

    await expect.poll(async () => (await api.occupiedSeats(event.eventId, event.occurrenceId))
      .map(({ seat_uid, status, block_reason }) => ({ seat_uid, status, block_reason })))
      .toEqual([{ seat_uid: 'e2.0.10', status: 'BLOCKED', block_reason: 'House seat' }]);
  });

  test('a seat taken online before charging is dropped and replaced without losing the sale', async ({ page, api, publicApi, account }) => {
    const event = await createSeatedEvent(api, account.organizerId);
    const door = await openDoor(page, api, event.eventId);

    await door.addProduct(event.priceId, 2);
    await door.seat('e2.0.9').click();
    await door.seat('e2.0.10').click();
    await takeSeatOnline(publicApi, event, 'e2.0.10');

    await door.charge();
    await expect(page.getByText('Just taken by someone else: Stalls · A-11. Choose replacement seats.')).toBeVisible();
    await expect(door.cartSeats(event.priceId)).toContainText('Stalls · A-10');
    await expect(door.chargeButton()).toHaveText('Choose 1 more seat');

    await door.bestAvailable();
    await completeFreeSale(page, door);

    const labels = (await api.listAttendees(event.eventId)).map((attendee) => attendee.seat_label);
    expect(labels).toHaveLength(2);
    expect(labels).toContain('Stalls · A-10');
    expect(labels).not.toContain('Stalls · A-11');
  });

  test('going back from payment releases the seats the sale was holding', async ({ page, authedPage, api, account }) => {
    const event = await createSeatedEvent(api, account.organizerId, { price: 30 });
    const door = await openDoor(page, api, event.eventId);

    await door.seat('e2.0.9').click();
    await door.seat('e2.0.10').click();
    await door.charge();
    await expect(page.getByTestId('box-office-tender-cash')).toBeVisible();

    await authedPage.goto(`/manage/event/${event.eventId}/seating/sales`);
    await expect(authedPage.getByText('In a basket · 2')).toBeVisible();

    await page.getByRole('button', { name: 'Back to sale' }).click();
    await expect(door.chargeButton()).toBeEnabled();

    await expect.poll(() => api.occupiedSeats(event.eventId, event.occurrenceId)).toEqual([]);
    await authedPage.reload();
    await expect(authedPage.getByText('In a basket · 0')).toBeVisible();
    await expect(door.cartSeats(event.priceId)).toContainText('Stalls · A-10');
  });

  test('one tap on a standing area fills every open ticket', async ({ page, api, account }) => {
    const event = await createSeatedEvent(api, account.organizerId, { layout: 'club', bandKey: 'b_standard' });
    const door = await openDoor(page, api, event.eventId);

    await door.addProduct(event.priceId, 3);
    await door.tapZone('z3');
    await expect(door.cartSeats(event.priceId).getByRole('button', { name: /Remove seat/ })).toHaveCount(3);
    await completeFreeSale(page, door);

    const labels = (await api.listAttendees(event.eventId)).map((attendee) => attendee.seat_label);
    expect(labels).toEqual(['Main floor · Main floor', 'Main floor · Main floor', 'Main floor · Main floor']);
  });

  test('best available fills a standing-only ticket from the standing area', async ({ page, api, account }) => {
    const event = await createSeatedEvent(api, account.organizerId, { layout: 'club', bandKey: 'b_standard' });
    const door = await openDoor(page, api, event.eventId);

    await door.addProduct(event.priceId, 2);
    await door.bestAvailable();
    await expect(door.chargeButton()).toBeEnabled();
    await expect(door.cartSeats(event.priceId)).toContainText('Main floor · Main floor');
  });

  test('a wheelchair space is called out when it is added', async ({ page, api, account }) => {
    const event = await createSeatedEvent(api, account.organizerId, { bandKey: 'b_standard' });
    const door = await openDoor(page, api, event.eventId);

    await door.seat('e6.0.0').click();
    await expect(page.getByText('Stalls · W-1 is a wheelchair space')).toBeVisible();
    await expect(door.cartSeats(event.priceId).getByLabel('Wheelchair space')).toBeVisible();
  });

  test('an unseated product sells alongside seats on a seated event', async ({ page, api, account }) => {
    const event = await createSeatedEvent(api, account.organizerId);
    const [category] = await api.listProductCategories(event.eventId);
    const programme = await api.createProduct(event.eventId, {
      title: 'Programme',
      product_type: 'GENERAL',
      type: 'FREE',
      product_category_id: category.id,
      prices: [{ price: 0 }],
    });
    const programmePriceId = programme.prices![0].id!;
    const door = await openDoor(page, api, event.eventId);

    await door.addProduct(programmePriceId, 1);
    await expect(door.chargeButton()).toBeEnabled();
    await door.addProduct(event.priceId, 1);
    await expect(door.chargeButton()).toHaveText('Choose 1 more seat');
    await door.seat('e2.0.0').click();
    await completeFreeSale(page, door);

    const [attendee] = await api.listAttendees(event.eventId);
    expect(attendee.seat_label).toBe('Stalls · A-1');
  });

  test('a door seat map that fails to load offers a retry and keeps seated tickets off sale', async ({ page, api, account }) => {
    const event = await createSeatedEvent(api, account.organizerId);
    const boxOffice = await defaultBoxOffice(api, event.eventId);
    let failing = true;
    await page.route(/\/public\/box-offices\/[^/]+\/seat-map$/, (route) => failing
      ? route.fulfill({ status: 500, contentType: 'application/json', body: '{"message":"Server Error"}' })
      : route.fallback());

    const door = new BoxOfficePage(page);
    await door.goto(boxOffice.short_id);
    await door.startSession('Sam', boxOffice.pin);

    await expect(page.getByTestId('box-office-seat-map-error')).toBeVisible();
    await expect(page.getByTestId(`box-office-qty-plus-${event.priceId}`)).toHaveCount(0);

    failing = false;
    await page.getByTestId('box-office-seat-map-retry-button').click();
    await expect(door.seatMap()).toBeVisible();
    await expect(page.getByTestId('box-office-seat-map-error')).toHaveCount(0);
    await expect(page.getByTestId(`box-office-qty-plus-${event.priceId}`)).toBeVisible();
  });

  test('seat taps are ignored while best available is finding seats', async ({ page, api, account }) => {
    const event = await createSeatedEvent(api, account.organizerId);
    let releaseBestAvailable!: () => void;
    const bestAvailableHeld = new Promise<void>((resolve) => { releaseBestAvailable = resolve; });
    await page.route(/\/best-available-seats$/, async (route) => {
      await bestAvailableHeld;
      await route.fallback();
    });
    const door = await openDoor(page, api, event.eventId);

    await door.addProduct(event.priceId, 2);
    await door.bestAvailable();
    await expect(page.getByTestId('box-office-best-available-button')).toHaveAttribute('data-loading', 'true');
    await door.seat('e3.0.0').click();
    await expect(door.seat('e3.0.0')).not.toHaveAttribute('data-state', 'selected');

    releaseBestAvailable();
    await expect(door.cartSeats(event.priceId).getByRole('button', { name: /Remove seat/ })).toHaveCount(2);
    await expect(door.seat('e3.0.0')).not.toHaveAttribute('data-state', 'selected');
    await expect(door.chargeButton()).toBeEnabled();
  });

  test('the door reloads the layout after the organizer edits the seat map mid-shift', async ({ page, api, account }) => {
    const event = await createSeatedEvent(api, account.organizerId);
    await openDoor(page, api, event.eventId);

    const { version, layout } = await api.getEventSeatMap(event.eventId);
    const reloaded = page.waitForRequest(/\/public\/box-offices\/[^/]+\/seat-map$/, { timeout: 30_000 });
    const response = await api.updateEventSeatMapLayout(
      event.eventId,
      { ...layout, areas: layout.areas.map((area, index) => (index === 0 ? { ...area, name: 'Orchestra' } : area)) },
      { version },
    );
    expect(response.ok()).toBeTruthy();

    await reloaded;
  });
});

test.describe('box office reserved seating on a phone', () => {
  test.use({ viewport: { width: 390, height: 844 }, hasTouch: true, isMobile: true });

  test.beforeEach(async ({ adminApi, account }) => {
    await adminApi.setAccountFeatureFlag(await adminApi.findAccountIdByEmail(account.email), 'seating', true);
  });

  test('a tap on a seat too small to hit zooms in before it selects', async ({ page, api, account }) => {
    const event = await createSeatedEvent(api, account.organizerId);
    const door = await openDoor(page, api, event.eventId);

    const before = (await door.seat('e2.0.9').boundingBox())!.width;
    await door.seat('e2.0.9').tap();
    await expect.poll(async () => (await door.seat('e2.0.9').boundingBox())!.width).toBeGreaterThan(before * 2);
    await expect(door.chargeButton()).toHaveCount(0);

    await door.seat('e2.0.9').tap();
    await expect(door.chargeButton()).toBeEnabled();
    await expect(door.chargeButton()).toHaveText(/^Charge/);
  });
});
