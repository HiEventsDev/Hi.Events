import fs from 'node:fs';
import { test, expect } from '../../fixtures';
import { createCompletedPaidOrder, createLiveEventWithProduct } from '../../api/factory';
import { EventReportPage } from '../../pages/event-report.page';

const grossLabel = (order: { totalGross: number }): string => `$${order.totalGross.toFixed(2)}`;

test.describe('event reports', () => {
  test("the daily sales report shows today's completed order with its totals", async ({ authedPage, api, account, publicApi }) => {
    const event = await createLiveEventWithProduct(api, { organizerId: account.organizerId, price: 25 });
    const gross = grossLabel(await createCompletedPaidOrder(api, publicApi, event));
    const report = new EventReportPage(authedPage);

    await report.goto(event.eventId, 'daily_sales_report');
    await report.reloadUntilRowAppears(gross);

    await expect(authedPage.getByRole('heading', { name: 'Daily sales' })).toBeVisible();
    await expect(report.rows).toHaveCount(1);
    const todayRow = report.row(gross);
    await expect(todayRow).toBeVisible();
    await expect(todayRow.getByRole('cell', { name: '1', exact: true })).toHaveCount(2);
    await expect(report.summaryValue('Gross sales')).toHaveText(gross);
    await expect(report.summaryValue('Orders')).toHaveText('1');
  });

  test('days with no sales stay hidden until they are asked for, and the totals row adds every day up', async ({ authedPage, api, account, publicApi }) => {
    const event = await createLiveEventWithProduct(api, { organizerId: account.organizerId, price: 25 });
    const gross = grossLabel(await createCompletedPaidOrder(api, publicApi, event));
    const report = new EventReportPage(authedPage);

    await report.goto(event.eventId, 'daily_sales_report');
    await report.reloadUntilRowAppears(gross);
    await expect(report.rows).toHaveCount(1);
    await expect(authedPage.getByText('1 of 90 rows')).toBeVisible();

    await report.showDaysWithNoSales();

    await expect(report.rows).toHaveCount(90);
    await expect(authedPage.getByText('90 rows', { exact: true })).toBeVisible();
    await expect(report.totalsRow).toContainText('Total');
    await expect(report.totalsRow).toContainText(gross);
    await expect(authedPage).toHaveURL(/empty=show/);
  });

  test('the product sales report lists the product with units sold', async ({ authedPage, api, account, publicApi }) => {
    const event = await createLiveEventWithProduct(api, { organizerId: account.organizerId, price: 25 });
    const order = await createCompletedPaidOrder(api, publicApi, event);
    const report = new EventReportPage(authedPage);

    await report.goto(event.eventId, 'product_sales');

    await expect(authedPage.getByRole('heading', { name: 'Product sales' })).toBeVisible();
    const productRow = report.row(event.productTitle);
    await expect(productRow).toBeVisible();
    await expect(productRow.getByRole('cell', { name: '1', exact: true })).toBeVisible();
    await expect(productRow).toContainText(`$${order.totalGross.toFixed(2)}`);
    await expect(report.summaryValue('Units sold')).toHaveText('1');
  });

  test('opening reports lands on the first report, and the chosen period follows you between reports', async ({ authedPage, api, account }) => {
    const event = await createLiveEventWithProduct(api, { organizerId: account.organizerId, price: 25 });
    const report = new EventReportPage(authedPage);

    await report.gotoIndex(event.eventId);
    await expect(authedPage).toHaveURL(new RegExp(`/manage/event/${event.eventId}/report/daily_sales_report`));
    await expect(report.tab('daily_sales_report')).toHaveAttribute('aria-current', 'page');

    await report.choosePeriod('Last 7 days');
    await expect(authedPage).toHaveURL(/period=7d/);

    await report.tab('product_sales').click();

    await expect(authedPage).toHaveURL(new RegExp(`/report/product_sales\\?period=7d`));
    await expect(report.periodButton).toHaveText('Last 7 days');
    await expect(report.row(event.productTitle)).toBeVisible();
  });

  test('a report with nothing in it keeps its filters and explains why it is empty', async ({ authedPage, api, account }) => {
    const event = await createLiveEventWithProduct(api, { organizerId: account.organizerId, price: 25 });
    const report = new EventReportPage(authedPage);

    await report.goto(event.eventId, 'promo_codes_report');

    await expect(authedPage.getByText('Nothing to show yet')).toBeVisible();
    await expect(authedPage.getByText('Create a promo code to see how it performs here.')).toBeVisible();
    await expect(report.periodButton).toBeVisible();
    await expect(report.exportCsvButton).toBeDisabled();
  });

  test('the daily sales report exports the full period as a CSV', async ({ authedPage, api, account, publicApi }) => {
    const event = await createLiveEventWithProduct(api, { organizerId: account.organizerId, price: 25 });
    const gross = grossLabel(await createCompletedPaidOrder(api, publicApi, event));
    const report = new EventReportPage(authedPage);
    await report.goto(event.eventId, 'daily_sales_report');
    await report.reloadUntilRowAppears(gross);

    const [download] = await Promise.all([
      authedPage.waitForEvent('download'),
      report.exportCsvButton.click(),
    ]);

    expect(download.suggestedFilename()).toBe('daily_sales_report.csv');
    const lines = fs.readFileSync(await download.path(), 'utf8').split('\r\n');
    expect(lines[0]).toBe('Date,Gross sales,Sales before tax and fees,Tax,Service fees,Refunded,Orders,Products sold,Page views');
    expect(lines).toHaveLength(91);
    expect(lines.filter(line => line.split(',')[1] === gross.slice(1))).toHaveLength(1);
  });

  for (const viewport of [{ width: 390, height: 844 }, { width: 768, height: 1024 }, { width: 1280, height: 800 }]) {
    test(`the daily sales report fits a ${viewport.width}px wide screen without sideways page scrolling`, async ({ authedPage, api, account, publicApi }) => {
      const event = await createLiveEventWithProduct(api, { organizerId: account.organizerId, price: 25 });
      await createCompletedPaidOrder(api, publicApi, event);
      await authedPage.setViewportSize(viewport);
      const report = new EventReportPage(authedPage);

      await report.goto(event.eventId, 'daily_sales_report');
      await report.showDaysWithNoSales();
      await expect(report.rows).toHaveCount(90);

      await expect(report.periodButton).toBeInViewport();
      await expect(report.exportCsvButton).toBeInViewport();
      const overflow = await authedPage.evaluate(() => {
        const main = document.getElementById('app-manage-main') ?? document.documentElement;
        return Math.max(main.scrollWidth - main.clientWidth, document.documentElement.scrollWidth - window.innerWidth);
      });
      expect(overflow).toBeLessThanOrEqual(0);
    });
  }
});
