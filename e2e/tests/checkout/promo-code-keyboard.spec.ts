import { test, expect } from '../../fixtures';
import { CheckoutPage } from '../../pages/checkout.page';
import { createLiveEventWithPaidTicket } from '../../api/factory';
import { uniqueCode } from '../../utils/unique';

test.describe('promo code keyboard access', () => {
  test('a keyboard-only buyer can open, apply and dismiss the promo code field', async ({ page, api, account }) => {
    const event = await createLiveEventWithPaidTicket(api, account.organizerId);
    const code = uniqueCode();
    await api.createPromoCode(event.eventId, { code, discount_type: 'PERCENTAGE', discount: 10 });

    const checkout = new CheckoutPage(page);
    await checkout.gotoPublicEvent(event.eventId, event.slug);
    await checkout.setQuantityForProduct(event.productTitle, 1);
    const productPrice = page.locator('.hi-product-row').filter({ hasText: event.productTitle }).locator('.hi-price-amount').first();
    const fullPrice = (await productPrice.textContent()) ?? '';

    const trigger = page.getByRole('button', { name: 'Have a promo code?' });
    const continueButton = page.getByTestId('checkout-continue-button');
    await continueButton.focus();
    await page.keyboard.press('Tab');
    await expect(trigger).toBeFocused();

    await page.keyboard.press('Enter');
    const promoInput = page.getByRole('textbox', { name: 'Promo code' });
    await expect(promoInput).toBeFocused();

    await page.keyboard.press('Tab');
    await page.keyboard.press('Tab');
    await expect(page.getByRole('button', { name: 'close' })).toBeFocused();
    await page.keyboard.press('Enter');
    await expect(promoInput).toHaveCount(0);
    await expect(trigger).toBeFocused();

    await page.keyboard.press('Enter');
    await expect(promoInput).toBeFocused();
    await page.keyboard.type(code);
    await page.keyboard.press('Enter');

    await expect(page.locator('.hi-promo-code-applied')).toContainText(code);
    await expect(productPrice).not.toHaveText(fullPrice);
  });
});
