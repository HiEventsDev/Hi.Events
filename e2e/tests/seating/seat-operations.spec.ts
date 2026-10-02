import type {Page} from '@playwright/test';
import {test, expect} from '../../fixtures';
import {createSeatedEvent, createSeatedOrder} from '../../api/factory';
import type {ApiClient} from '../../api/api-client';
import {SeatingSettingsPage} from '../../pages/seating-settings.page';
import {uniqueEmail} from '../../utils/unique';

const seat = (page: Page, uid: string) => page.locator(`[data-uid="${uid}"]`);

const heldBackUids = async (api: ApiClient, eventId: number, occurrenceId: number): Promise<string[]> =>
  (await api.occupiedSeats(eventId, occurrenceId))
    .filter((occupied) => occupied.status === 'BLOCKED')
    .map((occupied) => occupied.seat_uid)
    .sort();

test.describe('seat operations', () => {
  test.beforeEach(async ({adminApi, account}) => {
    await adminApi.setAccountFeatureFlag(await adminApi.findAccountIdByEmail(account.email), 'seating', true);
  });

  test('an organizer holds seats back on the sales map and releases them', {tag: '@smoke'}, async ({authedPage: page, api, account}) => {
    const event = await createSeatedEvent(api, account.organizerId);

    await page.goto(`/manage/event/${event.eventId}/seating/sales`);
    await seat(page, 'e2.0.9').click();
    await seat(page, 'e2.0.10').click();
    await page.getByLabel('Reason (optional)').fill('Sound desk');
    await page.getByTestId('seating-sales-block-button').click();
    await expect(page.getByText('Seats held back')).toBeVisible();
    await expect(page.getByText('Held back · 2')).toBeVisible();

    await seat(page, 'e2.0.9').click();
    await expect(page.getByTestId('seating-sales-seat-detail')).toContainText('Sound desk');
    await page.getByTestId('seating-sales-release-button').click();
    await expect(page.getByText('Seats released for sale')).toBeVisible();
    await expect(page.getByText('Held back · 1')).toBeVisible();
  });

  test('an organizer adds an attendee to a seat and moves them', async ({authedPage: page, api, account, mailpit}) => {
    const event = await createSeatedEvent(api, account.organizerId);
    const email = uniqueEmail('seated');
    const attendee = await api.createAttendee(event.eventId, {
      product_id: event.productId,
      product_price_id: event.priceId,
      event_occurrence_id: event.occurrenceId,
      seat_uid: 'e2.0.9',
      email,
      first_name: 'Ada',
      last_name: 'Mover',
      amount_paid: 0,
      send_confirmation_email: false,
      locale: 'en',
    });
    expect(attendee.seat_label).toBe('Stalls · A-10');

    await page.goto(`/manage/event/${event.eventId}/attendees`);
    await expect(page.getByRole('row').filter({hasText: 'Ada Mover'})).toContainText('Stalls · A-10');
    await page.getByText('Ada Mover').first().click();
    await page.getByTestId('attendee-change-seat-button').click();
    await page.getByTestId('seat-chooser-map').locator('[data-uid="e2.1.9"]').click();
    await page.getByTestId('seat-chooser-confirm-button').click();
    await expect(page.getByText('Attendee moved')).toBeVisible();

    const moved = (await api.listAttendees(event.eventId)).find((candidate) => candidate.email === email);
    expect(moved?.seat_label).toBe('Stalls · B-10');

    const ticketEmail = await mailpit.waitForMessage(email, {subjectContains: 'Your Ticket'});
    expect((await mailpit.getMessage(ticketEmail.ID)).Text).toContain('Stalls · B-10');
  });

  test('holding seats on every upcoming date skips a seat already sold on one of them', async ({authedPage: page, api, publicApi, account}) => {
    const event = await createSeatedEvent(api, account.organizerId, {recurringCount: 3});
    const [firstDate, secondDate, thirdDate] = event.occurrences;
    await createSeatedOrder(publicApi, {...event, occurrenceId: secondDate.id}, ['e2.0.9']);

    const seating = new SeatingSettingsPage(page);
    await seating.goto(event.eventId);
    await seating.openSales();
    await seat(page, 'e2.0.9').click();
    await seat(page, 'e2.0.10').click();
    await page.getByTestId('seating-sales-date-scope').getByText('All upcoming').click();
    await page.getByLabel('Reason (optional)').fill('Press night');
    await expect(page.getByTestId('seating-sales-block-button')).toHaveText('Hold back 2 seats on 3 dates');
    await page.getByTestId('seating-sales-block-button').click();

    await expect(page.getByText('5 seats held back. 1 already sold or in a basket were skipped.')).toBeVisible();
    await expect(page.getByText('Held back · 2')).toBeVisible();

    expect(await heldBackUids(api, event.eventId, firstDate.id)).toEqual(['e2.0.10', 'e2.0.9']);
    expect(await heldBackUids(api, event.eventId, secondDate.id)).toEqual(['e2.0.10']);
    expect(await heldBackUids(api, event.eventId, thirdDate.id)).toEqual(['e2.0.10', 'e2.0.9']);
  });

  test('held seats are released on the chosen dates only', async ({authedPage: page, api, account}) => {
    const event = await createSeatedEvent(api, account.organizerId, {recurringCount: 3});
    const [firstDate, secondDate, thirdDate] = event.occurrences;
    for (const date of event.occurrences) {
      await api.blockSeats(event.eventId, date.id, ['e2.0.9', 'e2.0.10'], 'Sponsor');
    }

    await page.goto(`/manage/event/${event.eventId}/seating/sales`);
    await expect(page.getByText('Held back · 2')).toBeVisible();
    await seat(page, 'e2.0.9').click();
    await seat(page, 'e2.0.10').click();
    await page.getByTestId('seating-sales-date-scope').getByText('Choose dates').click();
    await page.getByTestId('seating-sales-dates-select').click();
    await page.getByRole('option').nth(2).click();
    await page.getByTestId('seating-sales-dates-select').click();
    await expect(page.getByTestId('seating-sales-release-button')).toHaveText('Release 2 seats on 2 dates');
    await page.getByTestId('seating-sales-release-button').click();

    await expect(page.getByText('4 seats released for sale')).toBeVisible();
    await expect(page.getByText('Held back · 0')).toBeVisible();

    expect(await heldBackUids(api, event.eventId, firstDate.id)).toEqual([]);
    expect(await heldBackUids(api, event.eventId, secondDate.id)).toEqual(['e2.0.10', 'e2.0.9']);
    expect(await heldBackUids(api, event.eventId, thirdDate.id)).toEqual([]);
  });
});
