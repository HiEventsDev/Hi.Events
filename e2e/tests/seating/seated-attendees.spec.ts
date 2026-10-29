import { readFileSync } from 'node:fs';
import { inflateRawSync } from 'node:zlib';
import type { Page, Response } from '@playwright/test';
import { test, expect } from '../../fixtures';
import { AttendeePage } from '../../pages/attendee.page';
import { CheckInPage } from '../../pages/check-in.page';
import { createLiveEventWithFreeTicket, createSeatedEvent, createSeatedOrder, type SeatedEvent } from '../../api/factory';
import type { ApiClient } from '../../api/api-client';
import { uniqueEmail, uniqueName, uniqueShort } from '../../utils/unique';

async function addTicket(api: ApiClient, event: SeatedEvent, title: string): Promise<{ productId: number; priceId: number }> {
  const [category] = await api.listProductCategories(event.eventId);
  const created = await api.createProduct(event.eventId, {
    title,
    product_type: 'TICKET',
    type: 'FREE',
    product_category_id: category.id,
    prices: [{ price: 0 }],
  });
  return { productId: created.id, priceId: created.prices![0].id! };
}

async function openCreateAttendeeModal(page: Page, details: { firstName: string; lastName: string; email: string; productTitle: string }): Promise<void> {
  await page.getByTestId('attendee-create-button').click();
  await page.getByRole('heading', { name: 'Manually Add Attendee' }).waitFor();
  await page.getByLabel(/^First name/).fill(details.firstName);
  await page.getByLabel(/^Last name/).fill(details.lastName);
  await page.getByLabel(/^Email address/).fill(details.email);
  await page.getByRole('combobox', { name: /^Ticket/ }).click();
  await page.getByRole('option', { name: details.productTitle }).click();
}

function recordSeatApiRequests(page: Page): { status: number; url: string }[] {
  const requests: { status: number; url: string }[] = [];
  page.on('response', (response: Response) => {
    if (/\/api\/events\/\d+\/(seat-map|occurrences\/\d+\/occupied-seats)/.test(response.url())) {
      requests.push({ status: response.status(), url: response.url() });
    }
  });
  return requests;
}

function readZipEntry(archive: Buffer, entryName: string): string {
  const endOfDirectory = archive.lastIndexOf(Buffer.from([0x50, 0x4b, 0x05, 0x06]));
  const entryCount = archive.readUInt16LE(endOfDirectory + 10);
  let cursor = archive.readUInt32LE(endOfDirectory + 16);
  for (let entry = 0; entry < entryCount; entry++) {
    const method = archive.readUInt16LE(cursor + 10);
    const compressedSize = archive.readUInt32LE(cursor + 20);
    const nameLength = archive.readUInt16LE(cursor + 28);
    const extraLength = archive.readUInt16LE(cursor + 30);
    const commentLength = archive.readUInt16LE(cursor + 32);
    const localHeader = archive.readUInt32LE(cursor + 42);
    const name = archive.toString('utf8', cursor + 46, cursor + 46 + nameLength);
    if (name === entryName) {
      const dataStart = localHeader + 30 + archive.readUInt16LE(localHeader + 26) + archive.readUInt16LE(localHeader + 28);
      const data = archive.subarray(dataStart, dataStart + compressedSize);
      return (method === 8 ? inflateRawSync(data) : data).toString('utf8');
    }
    cursor += 46 + nameLength + extraLength + commentLength;
  }
  throw new Error(`${entryName} not found in archive`);
}

test.describe('seated attendees after the sale', () => {
  test.beforeEach(async ({ adminApi, account }) => {
    await adminApi.setAccountFeatureFlag(await adminApi.findAccountIdByEmail(account.email), 'seating', true);
  });

  test('an organizer adds an attendee to a seat chosen on the map, and taken seats cannot be chosen', { tag: '@smoke' }, async ({ authedPage: page, api, account, publicApi }) => {
    const event = await createSeatedEvent(api, account.organizerId);
    await createSeatedOrder(publicApi, event, ['e2.0.10']);
    const email = uniqueEmail('manual-seat');

    await new AttendeePage(page).goto(event.eventId);
    await openCreateAttendeeModal(page, { firstName: 'Manual', lastName: 'Seated', email, productTitle: 'Premium Seat' });
    await page.getByTestId('manual-attendee-choose-seat-button').click();

    const chooser = page.getByTestId('seat-chooser-map');
    await expect(chooser.locator('[data-uid="e2.0.10"]')).toHaveAttribute('data-state', 'unavailable');
    await chooser.locator('[data-uid="e2.0.10"]').click();
    await expect(page.getByTestId('seat-chooser-confirm-button')).toBeDisabled();

    await chooser.locator('[data-uid="e2.0.9"]').click();
    await page.getByTestId('seat-chooser-confirm-button').click();
    await expect(page.getByTestId('manual-attendee-choose-seat-button')).toHaveText('Stalls · A-10');
    await page.getByRole('button', { name: 'Create Attendee' }).click();

    await expect(page.getByRole('row').filter({ hasText: email })).toContainText('Stalls · A-10');

    await expect(api.createAttendee(event.eventId, {
      product_id: event.productId,
      product_price_id: event.priceId,
      event_occurrence_id: event.occurrenceId,
      seat_uid: 'e2.0.10',
      email: uniqueEmail('double-booked'),
      first_name: 'Double',
      last_name: 'Booked',
      amount_paid: 0,
      send_confirmation_email: false,
      locale: 'en',
    })).rejects.toThrow(/→ 4\d\d/);
  });

  test('adding an attendee to an event without a seat map is unchanged and makes no seat-map requests when seating is enabled', async ({ authedPage: page, api, account }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    const seatRequests = recordSeatApiRequests(page);
    const email = uniqueEmail('unseated');

    await new AttendeePage(page).goto(event.eventId);
    await openCreateAttendeeModal(page, { firstName: 'Plain', lastName: 'Guest', email, productTitle: event.productTitle });
    await expect(page.getByRole('combobox', { name: /^Ticket/ })).toHaveValue(event.productTitle);

    await expect(page.getByTestId('manual-attendee-choose-seat-button')).toHaveCount(0);
    await expect(page.getByText('Seat', { exact: true })).toHaveCount(0);
    await page.getByRole('button', { name: 'Create Attendee' }).click();

    await expect(page.getByText('Successfully created attendee')).toBeVisible();
    await expect(page.getByRole('row').filter({ hasText: email })).toBeVisible();
    expect(seatRequests).toEqual([]);
  });

  test('changing a seated attendee\'s ticket only offers tickets from the same band and keeps the seat', async ({ authedPage: page, api, account }) => {
    const event = await createSeatedEvent(api, account.organizerId);
    const child = await addTicket(api, event, 'Child Seat');
    const balcony = await addTicket(api, event, 'Balcony Seat');
    await api.linkSeatMapBands(event.eventId, [
      { band_key: 'b_premium', products: [{ product_id: event.productId }, { product_id: child.productId }] },
      { band_key: 'b_value', products: [{ product_id: balcony.productId }] },
    ]);
    const email = uniqueEmail('switcher');
    await api.createAttendee(event.eventId, {
      product_id: event.productId,
      product_price_id: event.priceId,
      event_occurrence_id: event.occurrenceId,
      seat_uid: 'e2.0.9',
      email,
      first_name: 'Band',
      last_name: 'Switcher',
      amount_paid: 0,
      send_confirmation_email: false,
      locale: 'en',
    });

    const attendees = new AttendeePage(page);
    await attendees.goto(event.eventId);
    await attendees.openRowAction(email, 'Manage attendee');
    await attendees.editButton().click();

    await page.getByRole('combobox', { name: /^Product/ }).click();
    await expect(page.getByRole('option', { name: 'Premium Seat' })).toBeVisible();
    await expect(page.getByRole('option', { name: 'Balcony Seat' })).toHaveCount(0);
    await page.getByRole('option', { name: 'Child Seat' }).click();
    await page.getByRole('button', { name: 'Save Changes' }).click();
    await expect(page.getByText('Successfully updated attendee')).toBeVisible();

    const updated = (await api.listAttendees(event.eventId)).find((candidate) => candidate.email === email);
    expect(updated).toEqual(expect.objectContaining({ product_id: child.productId, seat_label: 'Stalls · A-10' }));
  });

  test('the printable ticket shows the seat', async ({ page, api, account, publicApi }) => {
    const event = await createSeatedEvent(api, account.organizerId);
    const order = await createSeatedOrder(publicApi, event, ['e2.0.9'], { buyerFirstName: 'Printed', buyerLastName: 'Seat' });
    await page.addInitScript(() => {
      window.print = () => undefined;
    });

    await page.goto(`/product/${event.eventId}/${order.attendees[0].shortId}/print`);

    await expect(page.getByText('Printed Seat')).toBeVisible();
    await expect(page.getByText('Stalls · A-10')).toBeVisible();
  });

  test('check-in search shows the attendee\'s seat', async ({ page, api, account, publicApi }) => {
    const event = await createSeatedEvent(api, account.organizerId);
    const lastName = uniqueShort('Seat').replace(' ', '');
    const order = await createSeatedOrder(publicApi, event, ['e2.0.9'], { buyerFirstName: 'Door', buyerLastName: lastName });
    const list = await api.createCheckInList(event.eventId, { name: uniqueName('Stalls Door'), product_ids: [event.productId] });

    const checkIn = new CheckInPage(page);
    await checkIn.goto(list.short_id);
    await checkIn.openSearchTab();
    await checkIn.search(lastName);

    await expect(checkIn.attendeeRow(order.attendees[0].publicId)).toContainText('Premium Seat · Stalls · A-10');
  });

  test('the attendee export includes the seat', async ({ authedPage: page, api, account, publicApi }) => {
    const event = await createSeatedEvent(api, account.organizerId);
    await createSeatedOrder(publicApi, event, ['e2.0.9']);

    const attendees = new AttendeePage(page);
    await attendees.goto(event.eventId);
    const [download] = await Promise.all([page.waitForEvent('download'), attendees.clickExport()]);

    const workbook = readFileSync(await download.path());
    const strings = readZipEntry(workbook, 'xl/sharedStrings.xml');
    expect(strings).toContain('>Seat<');
    expect(strings).toContain('Stalls · A-10');
  });
});
