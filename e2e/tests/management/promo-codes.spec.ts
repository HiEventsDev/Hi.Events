import { test, expect } from '../../fixtures';
import { PromoCodePage } from '../../pages/promo-code.page';
import { createDraftEvent } from '../../api/factory';
import { uniqueCode } from '../../utils/unique';
import { expectDateTime } from '../../utils/datePicker';

test.describe('promo codes', () => {
  test('an organizer creates a promo code and sees it in the list', { tag: '@smoke' }, async ({ authedPage, api, account }) => {
    const event = await createDraftEvent(api, account.organizerId);
    const code = uniqueCode('PROMO');

    const promoCodes = new PromoCodePage(authedPage);
    await promoCodes.goto(event.eventId);
    await promoCodes.createPromoCode(code);

    const row = authedPage.getByRole('row').filter({ hasText: code.toUpperCase() });
    await expect(row).toBeVisible();
    await expect(row.getByText('All Products')).toBeVisible();
  });

  test('an organizer creates a per-order fixed code and the edit modal restores its settings', async ({ authedPage, api, account }) => {
    const event = await createDraftEvent(api, account.organizerId);
    const code = uniqueCode('ORDER');

    const promoCodes = new PromoCodePage(authedPage);
    await promoCodes.goto(event.eventId);
    await promoCodes.createPromoCode(code, {
      discountType: 'Fixed amount',
      discount: 10,
      appliesTo: 'Entire order',
      expiryDate: '2030-12-31T23:59',
    });

    const row = authedPage.getByRole('row').filter({ hasText: code.toUpperCase() });
    await expect(row).toBeVisible();

    await promoCodes.openEditModal(code);

    const appliesTo = authedPage.getByTestId('promo-code-discount-applies-to');
    await expect(appliesTo.getByRole('radio', { name: 'Entire order' })).toBeChecked();
    await expect(authedPage.getByLabel('Expiry Date')).toBeVisible();
    await expect(authedPage.getByTestId('promo-code-advanced-toggle')).toHaveText('Hide advanced options');
  });

  test('an expiry picked as a date only lasts until the end of that day', async ({ authedPage, api, account }) => {
    const event = await createDraftEvent(api, account.organizerId);
    const code = uniqueCode('EOD');

    const promoCodes = new PromoCodePage(authedPage);
    await promoCodes.goto(event.eventId);
    await promoCodes.createPromoCode(code, { expiryDay: '2030-12-24' });

    await expect(authedPage.getByRole('row').filter({ hasText: code.toUpperCase() })).toBeVisible();
    await promoCodes.openEditModal(code);

    await expectDateTime(authedPage, authedPage.getByLabel('Expiry Date'), '2030-12-24T23:59');
  });
});
