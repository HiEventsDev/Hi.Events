import type { APIRequestContext, Locator, Page } from '@playwright/test';
import { test, expect } from '../../fixtures';
import { BoxOfficePage } from '../../pages/box-office.page';
import { createLiveEventWithFreeTicket, createSeatedEvent, defaultBoxOffice, type SeatedEvent, type SeededBoxOffice } from '../../api/factory';
import { BASE_URL } from '../../utils/env';

const PHONE = { viewport: { width: 390, height: 844 }, hasTouch: true, isMobile: true };

async function openDoor(page: Page, boxOffice: SeededBoxOffice, operatorName: string): Promise<BoxOfficePage> {
  const door = new BoxOfficePage(page);
  await door.goto(boxOffice.short_id);
  await door.startSession(operatorName, boxOffice.pin);
  return door;
}

const sessionToken = (page: Page, shortId: string): Promise<string> => page.evaluate(
  (key) => JSON.parse(localStorage.getItem(`boxOfficeSession:${key}`) ?? '{}').token as string,
  shortId,
);

const cartSheet = (page: Page): Locator => page.getByRole('dialog', { name: 'Sale' });

const doorSeat = (page: Page, uid: string): Locator =>
  page.getByTestId('box-office-seat-map').locator(`[data-uid="${uid}"]`);

async function tapDoorSeat(page: Page, uid: string): Promise<void> {
  const seat = doorSeat(page, uid);
  await expect(async () => {
    await seat.tap();
    await expect(seat).toHaveAttribute('data-state', 'selected', { timeout: 1_000 });
  }).toPass({ timeout: 15_000 });
}

const isTopmost = (locator: Locator): Promise<boolean> => locator.evaluate((element) => {
  const box = element.getBoundingClientRect();
  const hit = document.elementFromPoint(box.left + box.width / 2, box.top + box.height / 2);
  return hit !== null && element.contains(hit);
});

async function takeSeatOnline(publicApi: APIRequestContext, event: SeatedEvent, seatUid: string): Promise<void> {
  const response = await publicApi.post(`public/events/${event.eventId}/order`, {
    headers: { Accept: 'application/json' },
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

test.describe('box office door errors', () => {
  test.beforeEach(async ({ adminApi, account }) => {
    await adminApi.setAccountFeatureFlag(await adminApi.findAccountIdByEmail(account.email), 'seating', true);
  });

  test.describe('on a phone', () => {
    test.use(PHONE);

    test('a seat taken online while charging is reported without reopening the cart', async ({ page, api, publicApi, account }) => {
      const event = await createSeatedEvent(api, account.organizerId);
      const door = await openDoor(page, await defaultBoxOffice(api, event.eventId), 'Sam');
      await expect(door.seatMap()).toBeVisible();

      await door.addProduct(event.priceId, 2);
      await tapDoorSeat(page, 'e2.0.9');
      await tapDoorSeat(page, 'e2.0.10');
      await expect(door.chargeButton()).toHaveText(/^Charge/);

      await takeSeatOnline(publicApi, event, 'e2.0.10');
      await door.chargeButton().tap();

      const refusal = page.getByText('Just taken by someone else: Stalls · A-11. Choose replacement seats.');
      await expect(refusal).toBeVisible();
      expect(await isTopmost(refusal)).toBe(true);
      await expect(cartSheet(page)).toHaveCount(0);

      await expect(door.chargeButton()).toHaveText('1 to seat');
      await expect(door.chargeButton()).toBeDisabled();
      await expect(doorSeat(page, 'e2.0.9')).toHaveAttribute('data-state', 'selected');
      await expect(doorSeat(page, 'e2.0.10')).not.toHaveAttribute('data-state', 'selected');

      await tapDoorSeat(page, 'e2.0.11');
      await door.chargeButton().tap();
      await page.getByRole('button', { name: 'Complete sale' }).tap();
      await expect(door.saleCompleteHeading()).toBeVisible();

      const labels = (await api.listAttendees(event.eventId))
        .filter((attendee) => attendee.seat_label !== 'Stalls · A-11')
        .map((attendee) => attendee.seat_label)
        .sort();
      expect(labels).toEqual(['Stalls · A-10', 'Stalls · A-12']);
    });

    test('a session revoked mid-sale sends the operator back to sign in', async ({ page, api, publicApi, account }) => {
      const event = await createLiveEventWithFreeTicket(api, account.organizerId);
      const boxOffice = await defaultBoxOffice(api, event.eventId);
      const door = await openDoor(page, boxOffice, 'Sam');
      await expect(page.getByText(event.productTitle)).toBeVisible();

      await door.addProduct(event.priceId, 1);
      await expect(door.chargeButton()).toBeEnabled();

      const token = await sessionToken(page, boxOffice.short_id);
      const revoked = await publicApi.delete(`public/box-offices/${boxOffice.short_id}/sessions/current`, {
        headers: { 'X-Box-Office-Session': token },
      });
      expect(revoked.ok()).toBe(true);

      await door.chargeButton().tap();

      await expect(page.getByText('Your session expired. Sign in again to keep selling.')).toBeVisible();
      await expect(door.pinInput()).toBeVisible();
    });
  });

  test('a second door sees the seats held by a sale that is waiting for payment', async ({ page, browser, api, account }) => {
    const event = await createSeatedEvent(api, account.organizerId);
    const boxOffice = await defaultBoxOffice(api, event.eventId);
    const door = await openDoor(page, boxOffice, 'Sam');
    await expect(door.seatMap()).toBeVisible();

    await door.addProduct(event.priceId, 2);
    await door.seat('e2.0.9').click();
    await door.seat('e2.0.10').click();
    await door.charge();
    await expect(page.getByRole('button', { name: 'Complete sale' })).toBeVisible();

    const secondContext = await browser.newContext({ baseURL: BASE_URL, ignoreHTTPSErrors: true });
    const secondPage = await secondContext.newPage();
    try {
      const secondDoor = await openDoor(secondPage, boxOffice, 'Robin');
      await expect(secondDoor.seatMap()).toBeVisible();

      await expect(doorSeat(secondPage, 'e2.0.9')).toHaveAttribute('data-state', 'unavailable');
      await expect(doorSeat(secondPage, 'e2.0.10')).toHaveAttribute('data-state', 'unavailable');
      await expect(doorSeat(secondPage, 'e2.0.11')).toHaveAttribute('data-state', 'free');
    } finally {
      await secondContext.close();
    }
  });
});
