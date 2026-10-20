import { test, expect } from '../../fixtures';
import { CheckoutPage } from '../../pages/checkout.page';
import { createLiveEventWithProduct, enableOfflinePayments } from '../../api/factory';
import { uniqueEmail, uniqueName } from '../../utils/unique';

test.describe('taxes and fees', () => {
  test('tax and fee amounts appear in checkout and on the order summary', async ({ page, api, account }) => {
    const { id: accountId } = await api.getAccount();
    const tax = await api.createTaxOrFee(accountId, {
      name: uniqueName('Sales Tax'),
      description: 'Percentage sales tax',
      calculation_type: 'PERCENTAGE',
      type: 'TAX',
      rate: 10,
      is_active: true,
      is_default: false,
    });
    const fee = await api.createTaxOrFee(accountId, {
      name: uniqueName('Booking Fee'),
      description: 'Fixed booking fee',
      calculation_type: 'FIXED',
      type: 'FEE',
      rate: 2.5,
      is_active: true,
      is_default: false,
    });
    const event = await createLiveEventWithProduct(api, {
      organizerId: account.organizerId,
      price: 25,
      taxIds: [tax.id, fee.id],
    });
    await enableOfflinePayments(api, event.eventId);

    const buyer = { firstName: 'Taxed', lastName: 'Buyer', email: uniqueEmail('taxes') };
    const checkout = new CheckoutPage(page);
    await checkout.gotoPublicEvent(event.eventId, event.slug);
    await checkout.setFirstProductQuantity(1);
    await checkout.continueToCheckout();

    const summaryRow = (label: string) =>
      page.locator('[class*="totalsRow"]').filter({ has: page.getByText(label, { exact: true }) });
    await expect(summaryRow('Subtotal')).toContainText('$25.00');
    await expect(summaryRow('Fees')).toContainText('$2.50');
    await expect(summaryRow('Taxes')).toContainText('$2.75');
    await expect(summaryRow('Total')).toContainText('$30.25');

    await checkout.fillOrderDetails(buyer);
    await checkout.fillFirstAttendee(buyer);
    await checkout.continueToPayment();
    await Promise.all([
      page.waitForResponse((r) => r.url().includes('await-offline-payment') && r.ok()),
      page.getByRole('button', { name: /^Pay\b/ }).click(),
    ]);
    await page.waitForURL(/\/checkout\/\d+\/[^/]+\/summary/);
    await page.reload();
    await page.waitForLoadState('networkidle');

    await expect(page.getByText('Your order is awaiting payment')).toBeVisible();
    await expect(page.getByText('$30.25 USD')).toBeVisible();
  });
});
