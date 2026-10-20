import fs from 'node:fs';
import { test, expect } from '../../fixtures';
import { AffiliatePage } from '../../pages/affiliate.page';
import { createCompletedOrder, createDraftEvent, createLiveEventWithFreeTicket } from '../../api/factory';
import { uniqueCode, uniqueName } from '../../utils/unique';

test.describe('affiliates', () => {
  test('an organizer creates an affiliate and sees it in the table', async ({ authedPage, api, account }) => {
    const event = await createDraftEvent(api, account.organizerId);
    const name = uniqueName('Affiliate');
    const code = uniqueCode('AFF').slice(0, 20);

    const affiliates = new AffiliatePage(authedPage);
    await affiliates.goto(event.eventId);
    await affiliates.createAffiliate({ name, code });

    const row = affiliates.rowByText(code);
    await expect(row).toBeVisible();
    await expect(row.getByText(/^Active$/)).toBeVisible();
    await expect(row.getByText('0', { exact: true })).toBeVisible();
    await expect(row.getByText('$0.00')).toBeVisible();
  });

  test('a completed order placed with an affiliate code is attributed to the affiliate', async ({ authedPage, api, account, publicApi }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    const code = uniqueCode('TRK').slice(0, 20);
    await api.createAffiliate(event.eventId, { name: uniqueName('Affiliate'), code, status: 'ACTIVE' });
    await createCompletedOrder(publicApi, event, { affiliateCode: code });

    const affiliates = new AffiliatePage(authedPage);
    await affiliates.goto(event.eventId);

    const row = affiliates.rowByText(code);
    await expect(row).toBeVisible();
    await expect(row.getByText('1', { exact: true })).toBeVisible();
    await expect(row.getByText('$0.00')).toBeVisible();
  });

  test('an organizer exports affiliates to a spreadsheet', async ({ authedPage, api, account }) => {
    const event = await createDraftEvent(api, account.organizerId);
    await api.createAffiliate(event.eventId, {
      name: uniqueName('Affiliate'),
      code: uniqueCode('EXP').slice(0, 20),
      status: 'ACTIVE',
    });

    const affiliates = new AffiliatePage(authedPage);
    await affiliates.goto(event.eventId);

    const [download] = await Promise.all([
      authedPage.waitForEvent('download'),
      affiliates.clickExport(),
    ]);

    expect(download.suggestedFilename()).toBe('affiliates.xlsx');
    const downloadPath = await download.path();
    expect(fs.statSync(downloadPath).size).toBeGreaterThan(0);
  });
});
