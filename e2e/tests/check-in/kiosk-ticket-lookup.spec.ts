import type { APIRequestContext, Page } from '@playwright/test';
import { test, expect } from '../../fixtures';
import { CheckInPage } from '../../pages/check-in.page';
import { createCompletedOrder, createLiveEventWithFreeTicket } from '../../api/factory';
import type { ApiClient } from '../../api/api-client';
import { uniqueName, uniqueShort } from '../../utils/unique';

async function seedKiosk(
  api: ApiClient,
  publicApi: APIRequestContext,
  organizerId: number,
  options: { tickets?: number; lookupEnabled?: boolean } = {},
) {
  const event = await createLiveEventWithFreeTicket(api, organizerId);
  const order = await createCompletedOrder(publicApi, event, {
    quantity: options.tickets ?? 1,
    buyerFirstName: 'Casey',
    buyerLastName: uniqueShort('Lee').replace(' ', ''),
  });
  const list = await api.createCheckInList(event.eventId, {
    name: uniqueName('Self check-in'),
    kiosk_mode: true,
    kiosk_lookup_enabled: options.lookupEnabled ?? true,
  });
  return { order, list };
}

async function findTicket(page: Page, email: string, lastName: string): Promise<void> {
  await page.getByLabel('Email address').fill(email);
  await page.getByLabel('Last name').fill(lastName);
  await page.getByTestId('kiosk-lookup-submit').click();
}

test.describe('kiosk ticket lookup', () => {
  test('an attendee without their QR code checks in with their email and last name', async ({ page, api, publicApi, account }) => {
    const { order, list } = await seedKiosk(api, publicApi, account.organizerId);

    const checkIn = new CheckInPage(page);
    await checkIn.goto(list.short_id);
    await page.getByTestId('kiosk-find-ticket-button').click();

    await findTicket(page, order.buyerEmail, 'Nobody');
    await expect(page.getByText("We couldn't find a ticket with those details.", { exact: false })).toBeVisible();

    await findTicket(page, order.buyerEmail.toUpperCase(), order.buyerLastName.toLowerCase());

    await expect(page.getByTestId('kiosk-result-success')).toContainText('Welcome, Casey!');
  });

  test('an attendee with several tickets chooses theirs', async ({ page, api, publicApi, account }) => {
    const { order, list } = await seedKiosk(api, publicApi, account.organizerId, { tickets: 2 });

    const checkIn = new CheckInPage(page);
    await checkIn.goto(list.short_id);
    await page.getByTestId('kiosk-find-ticket-button').click();
    await findTicket(page, order.buyerEmail, order.buyerLastName);

    await expect(page.getByText('Which ticket is yours?')).toBeVisible();
    await expect(page.getByTestId('kiosk-lookup-match')).toHaveCount(2);
    await page.getByTestId('kiosk-lookup-match').first().click();

    await expect(page.getByTestId('kiosk-result-success')).toContainText('Welcome, Casey!');
  });

  test('kiosks with the lookup switched off only take QR codes', async ({ page, api, publicApi, account }) => {
    const { list } = await seedKiosk(api, publicApi, account.organizerId, { lookupEnabled: false });

    const checkIn = new CheckInPage(page);
    await checkIn.goto(list.short_id);

    await expect(checkIn.kiosk()).toContainText('Scan your ticket to check in');
    await expect(page.getByTestId('kiosk-find-ticket-button')).toHaveCount(0);
  });
});
