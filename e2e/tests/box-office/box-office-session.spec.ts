import type { Page } from '@playwright/test';
import { test, expect } from '../../fixtures';
import { BoxOfficePage } from '../../pages/box-office.page';
import { createLiveEventWithPaidTicket, createRecurringLiveEvent, defaultBoxOffice } from '../../api/factory';
import { startBoxOfficeSession } from '../../api/public-client';
import { uniqueShort } from '../../utils/unique';

const sessionToken = (page: Page, shortId: string): Promise<string> => page.evaluate(
  (id) => JSON.parse(localStorage.getItem(`boxOfficeSession:${id}`) ?? '{}').token as string,
  shortId,
);

const signInAgainModal = (page: Page) => page.getByRole('dialog', { name: 'Sign in again to keep selling' });

const daysFromNow = (days: number): string => {
  const date = new Date();
  date.setDate(date.getDate() + days);
  return date.toISOString().slice(0, 19).replace('T', ' ');
};

test.describe('box office sessions', () => {
  test('a session survives a reload and ends when the operator signs out', async ({ page, api, account }) => {
    const event = await createLiveEventWithPaidTicket(api, account.organizerId, 25);
    const boxOffice = await defaultBoxOffice(api, event.eventId);

    const door = new BoxOfficePage(page);
    await door.goto(boxOffice.short_id);
    await door.startSession('Sam', boxOffice.pin);
    await expect(page.getByText(event.productTitle)).toBeVisible();

    await page.reload();
    await expect(page.getByText(event.productTitle)).toBeVisible();
    await expect(door.pinInput()).toHaveCount(0);

    await door.signOut();
    await expect(door.pinInput()).toBeVisible();
  });

  test('signing out revokes the session on the server', async ({ page, api, account, publicApi }) => {
    const event = await createLiveEventWithPaidTicket(api, account.organizerId, 25);
    const boxOffice = await defaultBoxOffice(api, event.eventId);

    const door = new BoxOfficePage(page);
    await door.goto(boxOffice.short_id);
    await door.startSession('Sam', boxOffice.pin);
    await expect(page.getByText(event.productTitle)).toBeVisible();

    const token = await page.evaluate(
      (shortId) => JSON.parse(localStorage.getItem(`boxOfficeSession:${shortId}`) ?? '{}').token as string,
      boxOffice.short_id,
    );
    expect(token).toMatch(/^bos_/);

    const before = await publicApi.get(`public/box-offices/${boxOffice.short_id}/products`, {
      headers: { 'X-Box-Office-Session': token },
    });
    expect(before.status()).toBe(200);

    await door.signOut();
    await expect(door.pinInput()).toBeVisible();

    await expect.poll(async () => {
      const after = await publicApi.get(`public/box-offices/${boxOffice.short_id}/products`, {
        headers: { 'X-Box-Office-Session': token },
      });
      return after.status();
    }).toBe(401);
  });

  test('five wrong PINs lock the device out', async ({ page, api, account }) => {
    const event = await createLiveEventWithPaidTicket(api, account.organizerId, 25);
    const boxOffice = await defaultBoxOffice(api, event.eventId);

    const door = new BoxOfficePage(page);
    await door.goto(boxOffice.short_id);
    await page.getByTestId('box-office-operator-name').fill('Sam');

    for (let attempt = 0; attempt < 5; attempt += 1) {
      await door.pinInput().fill('000000');
      await page.getByTestId('box-office-start-button').click();
      await expect(page.getByText('Incorrect PIN')).toBeVisible();
    }

    await door.pinInput().fill(boxOffice.pin);
    await page.getByTestId('box-office-start-button').click();
    await expect(page.getByText(/Too many incorrect PINs/)).toBeVisible();
    await expect(page.getByText(event.productTitle)).toHaveCount(0);
  });

  test('a signed-in organizer starts a session without the PIN', async ({ authedPage, api, account }) => {
    const event = await createLiveEventWithPaidTicket(api, account.organizerId, 25);
    const boxOffice = await defaultBoxOffice(api, event.eventId);

    const door = new BoxOfficePage(authedPage);
    await door.goto(boxOffice.short_id);

    await expect(door.pinInput()).toHaveCount(0);
    await expect(authedPage.getByTestId('box-office-start-button')).toContainText('E2E Organizer');

    await door.startSessionAsAccount();
    await expect(authedPage.getByText(event.productTitle)).toBeVisible();
  });

  test('a signed-in organizer can hand the door to staff with a PIN', async ({ authedPage, api, account }) => {
    const event = await createLiveEventWithPaidTicket(api, account.organizerId, 25);
    const boxOffice = await defaultBoxOffice(api, event.eventId);

    const door = new BoxOfficePage(authedPage);
    await door.goto(boxOffice.short_id);
    await authedPage.getByTestId('box-office-use-pin-button').click();

    await expect(door.pinInput()).toBeVisible();
    await door.startSession('Sam', boxOffice.pin);
    await expect(authedPage.getByText(event.productTitle)).toBeVisible();
    await expect(authedPage.getByText('Sam')).toBeVisible();
  });

  test('a signed-in organizer keeps selling after switching date', async ({ authedPage, api, account }) => {
    const event = await createRecurringLiveEvent(api, account.organizerId, { price: 25 });
    const boxOffice = await defaultBoxOffice(api, event.eventId);

    const door = new BoxOfficePage(authedPage);
    await door.goto(boxOffice.short_id);
    await door.chooseStartDate(0);
    await door.startSessionAsAccount();
    await expect(authedPage.getByText(event.productTitle)).toBeVisible();

    await door.switchDate(1);
    await door.addProduct(event.priceId, 1);
    await door.charge();
    await door.payCash();
    await expect(door.saleCompleteHeading()).toBeVisible();

    const orders = await api.listOrders(event.eventId);
    const order = orders.find((candidate) => candidate.box_office_id === boxOffice.id);
    expect(order?.order_items?.[0]?.event_occurrence_id).toBe(event.occurrences[1].id);
  });

  test('a box office outside its activation window cannot be opened', async ({ page, api, account }) => {
    const event = await createLiveEventWithPaidTicket(api, account.organizerId, 25);
    const closed = await api.createBoxOffice(event.eventId, {
      name: uniqueShort('Closed door'),
      expires_at: daysFromNow(-1),
    });
    const notOpenYet = await api.createBoxOffice(event.eventId, {
      name: uniqueShort('Future door'),
      activates_at: daysFromNow(2),
    });

    const door = new BoxOfficePage(page);
    await door.goto(closed.short_id);
    await expect(page.getByRole('heading', { name: 'Box office has closed' })).toBeVisible();

    await door.goto(notOpenYet.short_id);
    await expect(page.getByRole('heading', { name: 'Box office is not open yet' })).toBeVisible();
  });

  test('a box office that closes mid-session tells the operator instead of going blank', async ({ page, api, account }) => {
    const event = await createLiveEventWithPaidTicket(api, account.organizerId, 25);
    const boxOffice = await api.createBoxOffice(event.eventId, { name: uniqueShort('Closing door') });

    const door = new BoxOfficePage(page);
    await door.goto(boxOffice.short_id);
    await door.startSession('Sam', boxOffice.pin);
    await door.addProduct(event.priceId, 1);

    await api.updateBoxOffice(event.eventId, boxOffice.id, { name: boxOffice.name, expires_at: daysFromNow(-1) });
    await door.chargeButton().click();

    await expect(page.getByRole('heading', { name: 'Box office has closed' })).toBeVisible();
  });

  test('the sign-in screen carries the Powered by notice', async ({ page, api, account }) => {
    const event = await createLiveEventWithPaidTicket(api, account.organizerId, 25);
    const boxOffice = await defaultBoxOffice(api, event.eventId);

    const door = new BoxOfficePage(page);
    await door.goto(boxOffice.short_id);

    await expect(door.pinInput()).toBeVisible();
    await expect(page.getByRole('link', { name: /Hi\.Events/ })).toBeVisible();
  });

  test('a box office scoped to one date sells for that date without a date picker', async ({ page, api, account }) => {
    const event = await createRecurringLiveEvent(api, account.organizerId, { price: 25 });
    const scopedOccurrence = event.occurrences[1];
    const boxOffice = await api.createBoxOffice(event.eventId, {
      name: uniqueShort('Night two door'),
      event_occurrence_id: scopedOccurrence.id,
    });

    const door = new BoxOfficePage(page);
    await door.goto(boxOffice.short_id);
    await expect(page.getByLabel('Date')).toHaveCount(0);

    await door.startSession('Sam', boxOffice.pin);
    await expect(door.occurrenceChip()).toHaveCount(0);

    await door.addProduct(event.priceId, 1);
    await door.charge();
    await door.payCash();
    await expect(door.saleCompleteHeading()).toBeVisible();

    const orders = await api.listOrders(event.eventId);
    const order = orders.find((candidate) => candidate.box_office_id === boxOffice.id);
    expect(order?.order_items?.[0]?.event_occurrence_id).toBe(scopedOccurrence.id);
  });

  test('a box office scoped to one date ignores another date sent in the order body', async ({ api, account, publicApi }) => {
    const event = await createRecurringLiveEvent(api, account.organizerId, { price: 25 });
    const scopedOccurrence = event.occurrences[1];
    const boxOffice = await api.createBoxOffice(event.eventId, {
      name: uniqueShort('Night two door'),
      event_occurrence_id: scopedOccurrence.id,
    });

    const token = await startBoxOfficeSession(publicApi, boxOffice.short_id, { pin: boxOffice.pin, operatorName: 'Sam' });
    const response = await publicApi.post(`public/box-offices/${boxOffice.short_id}/orders`, {
      headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-Box-Office-Session': token },
      data: {
        idempotency_key: crypto.randomUUID(),
        event_occurrence_id: event.occurrences[0].id,
        items: [{ product_id: event.productId, product_price_id: event.priceId, quantity: 1 }],
      },
    });
    expect(response.status()).toBe(201);
    const order = (await response.json()).data as { order_items: { event_occurrence_id: number }[] };
    expect(order.order_items[0].event_occurrence_id).toBe(scopedOccurrence.id);
  });

  test('a session revoked mid-sale asks for the PIN again and keeps the cart', async ({ page, api, account, publicApi }) => {
    const event = await createLiveEventWithPaidTicket(api, account.organizerId, 25);
    const boxOffice = await defaultBoxOffice(api, event.eventId);

    const door = new BoxOfficePage(page);
    await door.goto(boxOffice.short_id);
    await door.startSession('Sam', boxOffice.pin);
    await door.addProduct(event.priceId, 2);

    const token = await sessionToken(page, boxOffice.short_id);
    const revoked = await publicApi.delete(`public/box-offices/${boxOffice.short_id}/sessions/current`, {
      headers: { 'X-Box-Office-Session': token },
    });
    expect(revoked.ok()).toBe(true);

    await door.charge();
    const modal = signInAgainModal(page);
    await expect(modal).toBeVisible();
    await expect(modal.getByTestId('box-office-operator-name')).toHaveValue('Sam');
    await modal.getByTestId('box-office-pin').fill(boxOffice.pin);
    await modal.getByTestId('box-office-start-button').click();
    await expect(modal).toBeHidden();

    await expect(door.chargeButton()).toContainText('50.00');
    await door.charge();
    await door.payCash();
    await expect(door.saleCompleteHeading()).toBeVisible();

    const orders = await api.listOrders(event.eventId);
    expect(orders.find((order) => order.box_office_id === boxOffice.id)?.total_gross).toBe(50);
  });

  test('re-pinning the box office to another date sends the open session back to sign in', async ({ page, api, account }) => {
    const event = await createRecurringLiveEvent(api, account.organizerId, { price: 25 });
    const name = uniqueShort('Night one door');
    const boxOffice = await api.createBoxOffice(event.eventId, { name, event_occurrence_id: event.occurrences[0].id });

    const door = new BoxOfficePage(page);
    await door.goto(boxOffice.short_id);
    await door.startSession('Sam', boxOffice.pin);
    await door.addProduct(event.priceId, 1);

    await api.updateBoxOffice(event.eventId, boxOffice.id, { name, event_occurrence_id: event.occurrences[1].id });

    await door.charge();
    await expect(signInAgainModal(page)).toBeVisible();
    await expect(signInAgainModal(page).getByTestId('box-office-pin')).toBeVisible();

    const orders = await api.listOrders(event.eventId);
    expect(orders.filter((order) => order.box_office_id === boxOffice.id)).toHaveLength(0);
  });

  test('resetting the PIN lifts a box-office-wide lockout', async ({ page, api, account, publicApi }) => {
    const event = await createLiveEventWithPaidTicket(api, account.organizerId, 25);
    const boxOffice = await defaultBoxOffice(api, event.eventId);

    for (let attempt = 0; attempt < 20; attempt += 1) {
      const response = await publicApi.post(`public/box-offices/${boxOffice.short_id}/sessions`, {
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-Forwarded-For': `203.0.113.${Math.floor(attempt / 4) + 1}` },
        data: { operator_name: 'Mallory', pin: '000000' },
      });
      expect(response.status()).toBe(401);
    }

    const door = new BoxOfficePage(page);
    await door.goto(boxOffice.short_id);
    await door.startSession('Sam', boxOffice.pin);
    await expect(page.getByText(/Too many incorrect PINs/)).toBeVisible();

    const reset = await api.resetBoxOfficePin(event.eventId, boxOffice.id);
    await door.pinInput().fill(reset.pin);
    await page.getByTestId('box-office-start-button').click();
    await expect(page.getByText(event.productTitle)).toBeVisible();
  });
});
