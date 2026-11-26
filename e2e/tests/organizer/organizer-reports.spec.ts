import fs from 'node:fs';
import { test, expect } from '../../fixtures';
import { OrganizerReportPage } from '../../pages/event-report.page';
import { createCompletedPaidOrder, createFreshOrganizer, createLiveEventWithProduct } from '../../api/factory';
import { invitedMember } from '../../utils/team';

test.describe('organizer reports', () => {
  test('the revenue report shows the completed order and exports to CSV', async ({ authedPage, api, publicApi }) => {
    const seeded = await createFreshOrganizer(api);
    const event = await createLiveEventWithProduct(api, { organizerId: seeded.id, price: 25 });
    const gross = `$${(await createCompletedPaidOrder(api, publicApi, event)).totalGross.toFixed(2)}`;
    const report = new OrganizerReportPage(authedPage);

    await report.goto(seeded.id, 'revenue_summary');
    await report.reloadUntilRowAppears(gross);

    await expect(authedPage.getByRole('heading', { name: 'Revenue' })).toBeVisible();
    const revenueRow = report.row(gross);
    await expect(revenueRow).toBeVisible();
    await expect(revenueRow.getByRole('cell', { name: '1', exact: true })).toBeVisible();
    await expect(report.summaryValue('Gross sales')).toHaveText(gross);

    const [download] = await Promise.all([
      authedPage.waitForEvent('download'),
      report.exportCsvButton.click(),
    ]);
    expect(download.suggestedFilename()).toMatch(/^revenue_summary_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.csv$/);
    expect(fs.statSync(await download.path()).size).toBeGreaterThan(0);
  });

  test('the events report lists every event and sorts by a column', async ({ authedPage, api, publicApi }) => {
    const seeded = await createFreshOrganizer(api);
    const sold = await createLiveEventWithProduct(api, { organizerId: seeded.id, price: 25 });
    const unsold = await createLiveEventWithProduct(api, { organizerId: seeded.id, price: 25 });
    const gross = `$${(await createCompletedPaidOrder(api, publicApi, sold)).totalGross.toFixed(2)}`;
    const report = new OrganizerReportPage(authedPage);

    await report.goto(seeded.id, 'events_performance');

    await expect(authedPage.getByRole('heading', { name: 'Events' })).toBeVisible();
    await expect(report.row(sold.title)).toContainText(gross);
    await expect(report.summaryValue('Events')).toHaveText('2');
    await expect(report.summaryValue('Gross sales')).toHaveText(gross);
    await expect(report.totalsRow).toContainText(gross);

    await report.columnHeader('Gross sales').click();
    await expect(authedPage).toHaveURL(/sort=gross_revenue&dir=desc/);
    await expect(report.rows.first()).toContainText(sold.title);

    await report.columnHeader('Gross sales').click();
    await expect(authedPage).toHaveURL(/dir=asc/);
    await expect(report.rows.first()).toContainText(unsold.title);
  });

  test('a member who cannot view financials only gets the check-ins report', async ({ freshAccount, mailpit, publicApi, browser }) => {
    await createLiveEventWithProduct(freshAccount.api, { organizerId: freshAccount.organizerId, price: 25 });
    const { page } = await invitedMember(freshAccount, mailpit, publicApi, browser, { role: 'ORGANIZER', permissions: { VIEW_FINANCIALS: false } });
    const report = new OrganizerReportPage(page);

    await page.goto(`/manage/organizer/${freshAccount.organizerId}/reports`);

    await expect(page).toHaveURL(/\/report\/check_in_summary/);
    await expect(report.tab('check_in_summary')).toBeVisible();
    await expect(report.tab('revenue_summary')).toHaveCount(0);
    await expect(report.summaryValue('Check-in rate')).toHaveText('0%');

    await page.context().close();
  });
});
