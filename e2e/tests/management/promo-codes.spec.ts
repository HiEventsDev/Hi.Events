import { test, expect } from '../../fixtures';
import { PromoCodePage } from '../../pages/promo-code.page';
import { createDraftEvent } from '../../api/factory';
import { uniqueCode } from '../../utils/unique';

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
});
