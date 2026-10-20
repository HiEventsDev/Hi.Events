import type { Page } from '@playwright/test';
import { test, expect } from '../../fixtures';
import { OccurrencePage } from '../../pages/occurrence.page';
import { createCompletedOrder, createRecurringLiveEvent } from '../../api/factory';
import type { Occurrence } from '../../api/types';
import { IS_SAAS_MODE } from '../../utils/env';
import { uniqueName } from '../../utils/unique';

const times = (start: string, end: string): RegExp => new RegExp(`${start}.*${end}`);

const earliest = (occurrences: Occurrence[]): Occurrence =>
  [...occurrences].sort((a, b) => a.start_date.localeCompare(b.start_date))[0];

const daysFromNow = (days: number, hour: number): string => {
  const date = new Date();
  date.setDate(date.getDate() + days);
  date.setUTCHours(hour, 0, 0, 0);
  return date.toISOString();
};

const reloadUntil = async (
  occurrences: OccurrencePage,
  eventId: number,
  assertion: () => Promise<void>,
): Promise<void> => {
  await expect(async () => {
    await occurrences.goto(eventId);
    await assertion();
  }).toPass({ timeout: 45_000 });
};

const waitForOrderStatistics = (page: Page, occurrences: OccurrencePage, eventId: number): Promise<void> =>
  reloadUntil(occurrences, eventId, async () => {
    await expect(page.getByText('1 order')).toBeVisible({ timeout: 3_000 });
  });

test.describe('occurrence bulk edit', () => {
  test('an organizer shifts every loaded date an hour later', { tag: '@smoke' }, async ({ authedPage, api, account }) => {
    const event = await createRecurringLiveEvent(api, account.organizerId, { count: 3 });

    const occurrences = new OccurrencePage(authedPage);
    await occurrences.goto(event.eventId);
    await expect(occurrences.timeCells()).toHaveText(Array(3).fill(times('7:00 PM', '9:00 PM')));

    await occurrences.openBulkEdit('shift-times');
    await expect(occurrences.affectedCount()).toHaveText('This will affect 3 date(s).');
    await occurrences.bulkEditModal().getByLabel('Hours').fill('1');
    await occurrences.applyBulkEdit();

    await expect(authedPage.getByText('Shifted times for 3 date(s)')).toBeVisible();
    await expect(occurrences.timeCells()).toHaveText(Array(3).fill(times('8:00 PM', '10:00 PM')));
  });

  test('an organizer moves every date earlier', async ({ authedPage, api, account }) => {
    const event = await createRecurringLiveEvent(api, account.organizerId, { count: 3 });

    const occurrences = new OccurrencePage(authedPage);
    await occurrences.goto(event.eventId);

    await occurrences.openBulkEdit('shift-times');
    await occurrences.bulkEditModal().getByText('Earlier', { exact: true }).click();
    await occurrences.bulkEditModal().getByLabel('Hours').fill('1');
    await occurrences.bulkEditModal().getByLabel('Minutes').fill('30');
    await occurrences.applyBulkEdit();

    await expect(authedPage.getByText('Shifted times for 3 date(s)')).toBeVisible();
    await expect(occurrences.timeCells()).toHaveText(Array(3).fill(times('5:30 PM', '7:30 PM')));
  });

  test('an organizer stretches every date to a new duration', async ({ authedPage, api, account }) => {
    const event = await createRecurringLiveEvent(api, account.organizerId, { count: 3 });

    const occurrences = new OccurrencePage(authedPage);
    await occurrences.goto(event.eventId);

    await occurrences.openBulkEdit('change-duration');
    await occurrences.bulkEditModal().getByLabel('Hours').fill('3');
    await occurrences.bulkEditModal().getByLabel('Minutes').fill('30');
    await occurrences.applyBulkEdit();

    await expect(authedPage.getByText('Changed duration for 3 date(s)')).toBeVisible();
    await expect(occurrences.timeCells()).toHaveText(Array(3).fill(times('7:00 PM', '10:30 PM')));
  });

  test('an organizer sets capacity on every date except the hand-edited one', async ({ authedPage, api, account }) => {
    const event = await createRecurringLiveEvent(api, account.organizerId, { count: 3 });
    const handEdited = earliest(event.occurrences);
    await api.updateOccurrence(event.eventId, handEdited.id, {
      start_date: handEdited.start_date,
      end_date: handEdited.end_date,
      capacity: 100,
    });

    const occurrences = new OccurrencePage(authedPage);
    await occurrences.goto(event.eventId);
    await expect(occurrences.capacityCells().filter({ hasText: '/ 100' })).toHaveCount(1);

    await occurrences.openBulkEdit('update-capacity');
    await expect(occurrences.affectedCount()).toHaveText('This will affect 2 date(s).');
    await occurrences.bulkOption('Skip manually edited dates').uncheck();
    await expect(occurrences.affectedCount()).toHaveText('This will affect 3 date(s).');
    await occurrences.bulkOption('Skip manually edited dates').check();

    await occurrences.bulkEditModal().getByLabel('New capacity').fill('40');
    await occurrences.applyBulkEdit();

    await expect(authedPage.getByText('Updated capacity for 2 date(s)')).toBeVisible();
    await expect(occurrences.capacityCells().filter({ hasText: '/ 40' })).toHaveCount(2);
    await expect(occurrences.capacityCells().filter({ hasText: '/ 100' })).toHaveCount(1);
  });

  test('an organizer clears capacity back to unlimited', async ({ authedPage, api, account }) => {
    const event = await createRecurringLiveEvent(api, account.organizerId, { count: 3 });
    for (const occurrence of event.occurrences) {
      await api.updateOccurrence(event.eventId, occurrence.id, {
        start_date: occurrence.start_date,
        end_date: occurrence.end_date,
        capacity: 25,
      });
    }

    const occurrences = new OccurrencePage(authedPage);
    await occurrences.goto(event.eventId);
    await expect(occurrences.capacityCells().filter({ hasText: '/ 25' })).toHaveCount(3);

    await occurrences.openBulkEdit('update-capacity');
    await occurrences.bulkOption('Skip manually edited dates').uncheck();
    await occurrences.bulkOption('Set to unlimited (remove limit)').check();
    await occurrences.applyBulkEdit();

    await expect(authedPage.getByText('Updated capacity for 3 date(s)')).toBeVisible();
    await expect(occurrences.capacityCells().filter({ hasText: '/' })).toHaveCount(0);
  });

  test('an organizer labels every date and then removes the label', async ({ authedPage, api, account }) => {
    const event = await createRecurringLiveEvent(api, account.organizerId, { count: 3 });

    const occurrences = new OccurrencePage(authedPage);
    await occurrences.goto(event.eventId);

    await occurrences.openBulkEdit('update-label');
    await occurrences.bulkEditModal().getByLabel('New label').fill('Morning Session');
    await occurrences.applyBulkEdit();

    await expect(authedPage.getByText('Updated label for 3 date(s)')).toBeVisible();
    await expect(occurrences.labelCells()).toHaveText(Array(3).fill('Morning Session'));

    await occurrences.openBulkEdit('update-label');
    await occurrences.bulkOption('Remove label from all dates').check();
    await occurrences.applyBulkEdit();

    await expect(occurrences.labelCells()).toHaveCount(0);
  });

  test('an organizer moves every date online and then clears the override', async ({ authedPage, api, account }) => {
    const event = await createRecurringLiveEvent(api, account.organizerId, { count: 3 });

    const occurrences = new OccurrencePage(authedPage);
    await occurrences.goto(event.eventId);

    await occurrences.openBulkEdit('update-location');
    await occurrences.bulkEditModal().getByText('Online — provide connection details').click();
    const editor = occurrences.bulkEditModal().locator('.ProseMirror').first();
    await editor.click();
    await editor.fill('Join at https://meet.example.test/e2e');
    await occurrences.applyBulkEdit();

    await expect(authedPage.getByText('Updated location for 3 date(s)')).toBeVisible();
    await expect(occurrences.locationCells()).toHaveText(Array(3).fill('Online'));

    await occurrences.openBulkEdit('update-location');
    await occurrences.bulkOption('Skip manually edited dates').uncheck();
    await occurrences.bulkEditModal().getByText('Clear location — fall back to the event default').click();
    await occurrences.applyBulkEdit();

    await expect(occurrences.locationCells()).toHaveCount(0);
  });

  test('an organizer points every date at a saved venue', async ({ authedPage, api, account }) => {
    const event = await createRecurringLiveEvent(api, account.organizerId, { count: 3 });
    const venue = uniqueName('Dockside Hall');
    await api.createOrganizerLocation(account.organizerId, {
      name: venue,
      structured_address: { venue_name: venue, address_line_1: '9 Dock Road', city: 'Brooklyn', country: 'US' },
    });

    const occurrences = new OccurrencePage(authedPage);
    await occurrences.goto(event.eventId);

    await occurrences.openBulkEdit('update-location');
    await occurrences.bulkEditModal().getByPlaceholder('Search saved locations or find an address...').fill(venue);
    await authedPage.getByRole('option', { name: venue }).click();
    await expect(occurrences.bulkEditModal().getByText('Saved location')).toBeVisible();
    await occurrences.applyBulkEdit();

    await expect(authedPage.getByText('Updated location for 3 date(s)')).toBeVisible();
    await expect(occurrences.locationCells()).toHaveText(Array(3).fill(`${venue}, Brooklyn`));
  });

  test('an organizer applies a change to every matching date, not just the loaded page', async ({ authedPage, api, account }) => {
    const event = await api.createEvent({
      title: uniqueName('E2E Bulk Scope'),
      type: 'RECURRING',
      organizer_id: account.organizerId,
      start_date: daysFromNow(30, 21),
      category: 'MUSIC',
      currency: 'USD',
      timezone: 'UTC',
    });
    await api.generateOccurrences(event.id, {
      frequency: 'daily',
      range: { type: 'count', count: 55, start: daysFromNow(1, 0) },
      times_of_day: ['19:00'],
      duration_minutes: 120,
    });

    const occurrences = new OccurrencePage(authedPage);
    await occurrences.goto(event.id);
    await expect(authedPage.getByText('Showing 1–50 of 55')).toBeVisible();

    await occurrences.openBulkEdit('update-capacity');
    await expect(occurrences.affectedCount()).toHaveText('This will affect 50 date(s).');

    await occurrences.setBulkScope('All matching dates');
    await expect(occurrences.affectedCount()).toHaveCount(0);
    await occurrences.bulkEditModal().getByLabel('New capacity').fill('15');
    await occurrences.applyBulkEdit();

    await expect(authedPage.getByText('Updated capacity for 55 date(s)')).toBeVisible();
    await expect(occurrences.capacityCells().filter({ hasText: '/ 15' })).toHaveCount(50);
  });

  test('an organizer includes past dates by turning off the future-only filter', async ({ authedPage, api, account }) => {
    const event = await createRecurringLiveEvent(api, account.organizerId, { count: 3 });
    await api.createOccurrence(event.eventId, {
      start_date: daysFromNow(-7, 19),
      end_date: daysFromNow(-7, 21),
    });

    const occurrences = new OccurrencePage(authedPage);
    await occurrences.goto(event.eventId);
    await occurrences.selectTimePeriod('All');
    await expect(occurrences.occurrenceRows()).toHaveCount(4);

    await occurrences.openBulkEdit('update-label');
    await occurrences.bulkOption('Skip manually edited dates').uncheck();
    await expect(occurrences.affectedCount()).toHaveText('This will affect 3 date(s).');
    await occurrences.bulkOption('Future dates only').uncheck();
    await expect(occurrences.affectedCount()).toHaveText('This will affect 4 date(s).');

    await occurrences.bulkEditModal().getByLabel('New label').fill('Season One');
    await occurrences.applyBulkEdit();

    await expect(authedPage.getByText('Updated label for 4 date(s)')).toBeVisible();
    await expect(occurrences.labelCells()).toHaveText(Array(4).fill('Season One'));
  });

  test('an organizer cancels the selected dates from the toolbar', async ({ authedPage, api, account }) => {
    const event = await createRecurringLiveEvent(api, account.organizerId, { count: 3 });

    const occurrences = new OccurrencePage(authedPage);
    await occurrences.goto(event.eventId);
    await occurrences.selectAllCheckbox().check();
    await expect(occurrences.selectionSummary()).toHaveText('3 selected');

    await occurrences.cancelSelected();
    await expect(authedPage.getByText('Cancelling 3 date(s). This may take a moment to complete.')).toBeVisible();

    await reloadUntil(occurrences, event.eventId, async () => {
      await expect(occurrences.statusBadges('CANCELLED')).toHaveCount(3);
    });
  });

  test('an organizer deletes the selected dates but keeps the one with an order', async ({ authedPage, api, account, publicApi }) => {
    const event = await createRecurringLiveEvent(api, account.organizerId, { count: 3 });
    const sold = earliest(event.occurrences);
    await createCompletedOrder(publicApi, event, { eventOccurrenceId: sold.id });

    const occurrences = new OccurrencePage(authedPage);
    await occurrences.goto(event.eventId);

    await occurrences.selectAllCheckbox().check();
    await expect(occurrences.selectionSummary()).toHaveText('3 selected');
    await occurrences.clearSelection();
    await expect(occurrences.selectionSummary()).toHaveCount(0);

    await occurrences.selectAllCheckbox().check();
    await occurrences.deleteSelected();

    await expect(authedPage.getByText('Deleted 2 date(s)')).toBeVisible();
    await expect(occurrences.occurrenceRows()).toHaveCount(1);
    await expect(authedPage.getByText('Showing 1–1 of 1')).toBeVisible();
  });

  test('an organizer is warned about registered attendees and lands in the message composer', async ({ authedPage, api, account, publicApi }) => {
    test.skip(IS_SAAS_MODE, 'The composer form is gated behind Stripe/manual account verification in SaaS mode.');

    const event = await createRecurringLiveEvent(api, account.organizerId, { count: 3 });
    const sold = earliest(event.occurrences);
    await createCompletedOrder(publicApi, event, { eventOccurrenceId: sold.id });

    const occurrences = new OccurrencePage(authedPage);
    await waitForOrderStatistics(authedPage, occurrences, event.eventId);

    await occurrences.openBulkEdit('shift-times');
    await occurrences.bulkEditModal().getByLabel('Hours').fill('2');
    await occurrences.applyBulkEdit();

    const warning = occurrences.dialog().filter({ hasText: "You're changing session times" });
    await expect(warning.getByText('1 attendee is registered across the affected sessions.')).toBeVisible();
    await warning.getByRole('button', { name: 'Save', exact: true }).click();

    await expect(authedPage.getByText('Shifted times for 3 date(s)')).toBeVisible();
    await expect(authedPage.getByRole('heading', { name: 'Send a message' })).toBeVisible();
    await expect(authedPage.getByLabel(/^Subject/)).toHaveValue(/schedule changes$/);
    await expect(authedPage.locator('.ProseMirror').first()).toContainText('affecting 3 session(s)');
  });
});
