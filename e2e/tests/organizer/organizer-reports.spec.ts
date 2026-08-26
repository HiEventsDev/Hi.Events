import fs from 'node:fs';
import { test, expect } from '../../fixtures';
import { OrganizerPage } from '../../pages/organizer.page';
import { createCompletedPaidOrder, createFreshOrganizer, createLiveEventWithProduct } from '../../api/factory';

test.describe('organizer reports', () => {
  test('the revenue summary report shows the completed order and exports to CSV', async ({ authedPage, api, publicApi }) => {
    const seeded = await createFreshOrganizer(api);
    const event = await createLiveEventWithProduct(api, { organizerId: seeded.id, price: 25 });
    await createCompletedPaidOrder(api, publicApi, event);

    const organizer = new OrganizerPage(authedPage);
    await organizer.gotoReport(seeded.id, 'revenue_summary');

    await expect(authedPage.getByRole('heading', { name: 'Revenue Summary' })).toBeVisible();
    const revenueRow = organizer.reportRow('$25.00');
    await expect(revenueRow).toBeVisible();
    await expect(revenueRow.getByRole('cell', { name: '1', exact: true })).toBeVisible();

    const [download] = await Promise.all([
      authedPage.waitForEvent('download'),
      organizer.exportCsvButton.click(),
    ]);
    expect(download.suggestedFilename()).toMatch(/^revenue_summary_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.csv$/);
    expect(fs.statSync(await download.path()).size).toBeGreaterThan(0);
  });

  test('the events performance report lists the event with its revenue', async ({ authedPage, api, publicApi }) => {
    const seeded = await createFreshOrganizer(api);
    const event = await createLiveEventWithProduct(api, { organizerId: seeded.id, price: 25 });
    await createCompletedPaidOrder(api, publicApi, event);

    const organizer = new OrganizerPage(authedPage);
    await organizer.gotoReport(seeded.id, 'events_performance');

    await expect(authedPage.getByRole('heading', { name: 'Events Performance' })).toBeVisible();
    const eventRow = organizer.reportRow(event.title);
    await expect(eventRow).toBeVisible();
    await expect(eventRow).toContainText('$25.00');
  });
});
