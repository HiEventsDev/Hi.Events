import { test, expect } from '../../fixtures';
import { createCompletedPaidOrder, createLiveEventWithProduct } from '../../api/factory';

test.describe('event reports', () => {
  test("the daily sales report shows today's completed order", async ({ authedPage, api, account, publicApi }) => {
    const event = await createLiveEventWithProduct(api, { organizerId: account.organizerId, price: 25 });
    await createCompletedPaidOrder(api, publicApi, event);

    await authedPage.goto(`/manage/event/${event.eventId}/report/daily_sales_report`);
    await authedPage.waitForLoadState('networkidle');

    await expect(authedPage.getByRole('heading', { name: 'Daily Sales Report' })).toBeVisible();
    const todayRow = authedPage.getByRole('row').filter({ hasText: '$25.00' });
    await expect(todayRow).toBeVisible();
    await expect(todayRow.getByRole('cell', { name: '1', exact: true })).toHaveCount(2);
  });

  test('the product sales report lists the product with units sold', async ({ authedPage, api, account, publicApi }) => {
    const event = await createLiveEventWithProduct(api, { organizerId: account.organizerId, price: 25 });
    const order = await createCompletedPaidOrder(api, publicApi, event);

    await authedPage.goto(`/manage/event/${event.eventId}/report/product_sales`);
    await authedPage.waitForLoadState('networkidle');

    await expect(authedPage.getByRole('heading', { name: 'Product Sales Report' })).toBeVisible();
    const productRow = authedPage.getByRole('row').filter({ hasText: event.productTitle });
    await expect(productRow).toBeVisible();
    await expect(productRow.getByRole('cell', { name: '1', exact: true })).toBeVisible();
    await expect(productRow).toContainText(`$${order.totalGross.toFixed(2)}`);
  });
});
