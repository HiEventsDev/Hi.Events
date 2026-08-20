import type { APIRequestContext } from '@playwright/test';
import { test, expect } from '../../fixtures';
import { CheckInPage } from '../../pages/check-in.page';
import { createCompletedOrder, createLiveEventWithFreeTicket } from '../../api/factory';
import type { ApiClient } from '../../api/api-client';
import { uniqueName, uniqueShort } from '../../utils/unique';

async function seedCheckInList(api: ApiClient, publicApi: APIRequestContext, organizerId: number) {
  const event = await createLiveEventWithFreeTicket(api, organizerId);
  const order = await createCompletedOrder(publicApi, event, {
    quantity: 2,
    buyerFirstName: 'Casey',
    buyerLastName: uniqueShort('Lee').replace(' ', ''),
  });
  const list = await api.createCheckInList(event.eventId, { name: uniqueName('Main Door') });
  return { order, list };
}

test.describe('public check-in app', () => {
  test('a staff member searches for an attendee and checks them in', { tag: '@smoke' }, async ({ page, api, publicApi, account }) => {
    const { order, list } = await seedCheckInList(api, publicApi, account.organizerId);
    const attendee = order.attendees[0];

    const checkIn = new CheckInPage(page);
    await checkIn.goto(list.short_id);
    await checkIn.openSearchTab();
    await checkIn.search(order.buyerLastName);

    await expect(checkIn.attendeeRow(attendee.publicId)).toBeVisible();

    await checkIn.checkInButton(attendee.publicId).click();

    await expect(checkIn.checkOutButton(attendee.publicId)).toBeVisible();
    await expect(checkIn.progressChip()).toHaveText('1/2');
  });

  test('a staff member undoes a check-in and the attendee reverts to pending', async ({ page, api, publicApi, account }) => {
    const { order, list } = await seedCheckInList(api, publicApi, account.organizerId);
    const attendee = order.attendees[0];

    const checkIn = new CheckInPage(page);
    await checkIn.goto(list.short_id);
    await checkIn.openSearchTab();
    await checkIn.search(order.buyerLastName);
    await checkIn.checkInButton(attendee.publicId).click();
    await expect(checkIn.checkOutButton(attendee.publicId)).toBeVisible();
    await expect(checkIn.progressChip()).toHaveText('1/2');

    await checkIn.checkOutButton(attendee.publicId).click();

    await expect(checkIn.checkInButton(attendee.publicId)).toBeVisible();
    await expect(checkIn.progressChip()).toHaveText('0/2');
  });

  test('the stats tab reflects attendance totals and check-ins', async ({ page, api, publicApi, account }) => {
    const { order, list } = await seedCheckInList(api, publicApi, account.organizerId);
    const attendee = order.attendees[0];

    const checkIn = new CheckInPage(page);
    await checkIn.goto(list.short_id);
    await checkIn.openSearchTab();
    await checkIn.search(order.buyerLastName);
    await checkIn.checkInButton(attendee.publicId).click();
    await expect(checkIn.checkOutButton(attendee.publicId)).toBeVisible();

    await checkIn.openStatsTab();

    await expect(page.getByText('attendees checked in')).toBeVisible();
    await expect(page.getByText('/ 2', { exact: true })).toBeVisible();
    await expect(page.getByText('50%')).toBeVisible();
    await expect(page.getByText('Latest check-ins')).toBeVisible();
    await expect(page.getByText(attendee.publicId)).toBeVisible();
    await expect(checkIn.progressChip()).toHaveText('1/2');
  });
});
