import fs from 'node:fs';
import { test, expect } from '../../fixtures';
import { createCompletedPaidOrder, createLiveEventWithPaidTicket } from '../../api/factory';

test.describe('invoice download', () => {
  test('a buyer downloads the invoice PDF from the order summary page', async ({ page, api, account, publicApi }) => {
    const event = await createLiveEventWithPaidTicket(api, account.organizerId);
    await api.updateEventSettings(event.eventId, {
      enable_invoicing: true,
      organization_name: 'E2E Events Ltd',
      organization_address: '1 Test Street, Test City',
    });
    const order = await createCompletedPaidOrder(api, publicApi, event);

    await page.goto(`/checkout/${event.eventId}/${order.orderShortId}/summary`);
    await page.waitForLoadState('networkidle');
    await expect(page.getByText(`You're going to ${event.title}`)).toBeVisible();

    const [download] = await Promise.all([
      page.waitForEvent('download'),
      page.getByTestId('download-invoice-button').click(),
    ]);

    expect(download.suggestedFilename()).toMatch(/\.pdf$/);
    expect(fs.statSync(await download.path()).size).toBeGreaterThan(0);
  });
});
