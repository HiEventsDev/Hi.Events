import { test, expect } from '../../fixtures';
import { CheckoutPage } from '../../pages/checkout.page';
import { createLiveEventWithProduct } from '../../api/factory';
import { uniqueCode, uniqueName } from '../../utils/unique';

test.describe('promo code with tax-inclusive prices', () => {
  test('the struck price includes VAT and the cart total matches the order total', async ({ page, api, account }) => {
    const { id: accountId } = await api.getAccount();
    const vat = await api.createTaxOrFee(accountId, {
      name: uniqueName('VAT'),
      description: 'Inclusive VAT',
      calculation_type: 'PERCENTAGE',
      type: 'TAX',
      rate: 23,
      is_active: true,
      is_default: false,
    });
    const event = await createLiveEventWithProduct(api, { organizerId: account.organizerId, price: 9.99, taxIds: [vat.id] });
    await api.updateEventSettings(event.eventId, { price_display_mode: 'INCLUSIVE', pass_platform_fee_to_buyer: false });
    const code = uniqueCode();
    await api.createPromoCode(event.eventId, { code, discount_type: 'PERCENTAGE', discount: 15 });

    const checkout = new CheckoutPage(page);
    await page.setViewportSize({ width: 390, height: 400 });
    await checkout.gotoPublicEvent(event.eventId, event.slug);
    await checkout.applyPromoCode(code);

    const productRow = page.locator('.hi-product-row').filter({ hasText: event.productTitle });
    await expect(productRow.locator('.hi-price-amount').first()).toHaveText('$10.44');
    await expect(productRow.locator('.hi-price-strike')).toHaveText('$12.29');

    await checkout.setQuantityForProduct(event.productTitle, 3);
    await page.evaluate(() => window.scrollTo(0, 0));
    await expect(page.getByTestId('floating-checkout-button')).toContainText('$31.33');

    await checkout.continueToCheckout();
    const totalRow = page.locator('[class*="totalsRow"]').filter({ has: page.getByText('Total', { exact: true }) });
    await expect(totalRow).toContainText('$31.33');
  });
});
