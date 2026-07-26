import type { Page } from '@playwright/test';
import { test, expect } from '../../fixtures';
import { CheckoutPage } from '../../pages/checkout.page';
import { createLiveEventWithPaidTicket } from '../../api/factory';
import { uniqueCode, uniqueEmail } from '../../utils/unique';

const summaryLineItem = (page: Page, productTitle: string) => page.getByTitle(productTitle).locator('../..');

const buyer = (email: string) => ({ firstName: 'Promo', lastName: 'Buyer', email });

test.describe('promo code checkout', () => {
  test(
    'a buyer completes a paid-ticket order for free with a 100% promo code',
    { tag: '@smoke' },
    async ({ page, api, account, mailpit }) => {
      const event = await createLiveEventWithPaidTicket(api, account.organizerId);
      const code = uniqueCode();
      await api.createPromoCode(event.eventId, { code, discount_type: 'PERCENTAGE', discount: 100 });
      const buyerEmail = uniqueEmail('buyer');
      const buyer = { firstName: 'Promo', lastName: 'Buyer', email: buyerEmail };

      const checkout = new CheckoutPage(page);
      await checkout.gotoPublicEvent(event.eventId, event.slug);
      await checkout.applyPromoCode(code);

      const productRow = page.locator('.hi-product-row').filter({ hasText: event.productTitle });
      await expect(productRow.getByText('Free')).toBeVisible();
      await expect(productRow.getByText('$25.00')).toBeVisible();

      await checkout.setQuantityForProduct(event.productTitle, 1);
      await checkout.continueToCheckout();
      await checkout.fillOrderDetails(buyer);
      await checkout.fillFirstAttendee(buyer);
      await checkout.completeFreeOrder();

      await expect(page.getByText(`You're going to ${event.title}`)).toBeVisible();
      await mailpit.waitForMessage(buyerEmail, { subjectContains: 'Your Order is Confirmed' });
    },
  );

  test('a partial percentage code discounts every ticket', async ({ page, api, account }) => {
    const event = await createLiveEventWithPaidTicket(api, account.organizerId);
    const code = uniqueCode();
    await api.createPromoCode(event.eventId, { code, discount_type: 'PERCENTAGE', discount: 10 });

    const checkout = new CheckoutPage(page);
    await checkout.gotoPublicEvent(event.eventId, event.slug);
    await checkout.applyPromoCode(code);

    const productRow = page.locator('.hi-product-row').filter({ hasText: event.productTitle });
    await expect(productRow.getByText('$22.50')).toBeVisible();
    await expect(productRow.getByText('$25.00')).toBeVisible();

    await checkout.setQuantityForProduct(event.productTitle, 2);
    await checkout.continueToCheckout();
    await checkout.fillOrderDetails(buyer(uniqueEmail('buyer')));

    await expect(summaryLineItem(page, event.productTitle).getByText('$45.00')).toBeVisible();
    await expect(summaryLineItem(page, event.productTitle).getByText('$50.00')).toBeVisible();
  });

  test('a per-ticket fixed code multiplies the discount by quantity', async ({ page, api, account }) => {
    const event = await createLiveEventWithPaidTicket(api, account.organizerId);
    const code = uniqueCode();
    await api.createPromoCode(event.eventId, {
      code,
      discount_type: 'FIXED',
      discount: 6,
      discount_applies_to: 'EACH_PRODUCT',
    });

    const checkout = new CheckoutPage(page);
    await checkout.gotoPublicEvent(event.eventId, event.slug);
    await checkout.applyPromoCode(code);

    const productRow = page.locator('.hi-product-row').filter({ hasText: event.productTitle });
    await expect(productRow.getByText('$19.00')).toBeVisible();
    await expect(productRow.getByText('$25.00')).toBeVisible();

    await checkout.setQuantityForProduct(event.productTitle, 2);
    await checkout.continueToCheckout();
    await checkout.fillOrderDetails(buyer(uniqueEmail('buyer')));

    await expect(summaryLineItem(page, event.productTitle).getByText('$38.00')).toBeVisible();
    await expect(summaryLineItem(page, event.productTitle).getByText('$50.00')).toBeVisible();
  });

  test('a per-order fixed code discounts the order once, not per ticket', async ({ page, api, account }) => {
    const event = await createLiveEventWithPaidTicket(api, account.organizerId);
    const code = uniqueCode();
    await api.createPromoCode(event.eventId, {
      code,
      discount_type: 'FIXED',
      discount: 10,
      discount_applies_to: 'ORDER',
    });

    const checkout = new CheckoutPage(page);
    await checkout.gotoPublicEvent(event.eventId, event.slug);
    await checkout.applyPromoCode(code);

    await expect(page.getByText('applied — $10.00 off your order')).toBeVisible();
    const productRow = page.locator('.hi-product-row').filter({ hasText: event.productTitle });
    await expect(productRow.getByText('$25.00')).toBeVisible();
    await expect(productRow.locator('.hi-price-tier-price-amount')).toHaveCount(1);

    await checkout.setQuantityForProduct(event.productTitle, 2);
    await checkout.continueToCheckout();
    await checkout.fillOrderDetails(buyer(uniqueEmail('buyer')));

    await expect(summaryLineItem(page, event.productTitle).getByText('$40.00')).toBeVisible();
    await expect(summaryLineItem(page, event.productTitle).getByText('$50.00')).toBeVisible();
  });

  test('a per-order discount that cannot split evenly stays exact by splitting a line', async ({ page, api, account }) => {
    const event = await createLiveEventWithPaidTicket(api, account.organizerId);
    const code = uniqueCode();
    await api.createPromoCode(event.eventId, {
      code,
      discount_type: 'FIXED',
      discount: 10,
      discount_applies_to: 'ORDER',
    });

    const checkout = new CheckoutPage(page);
    await checkout.gotoPublicEvent(event.eventId, event.slug);
    await checkout.applyPromoCode(code);
    await expect(page.locator('.hi-promo-code-applied')).toBeVisible();

    await checkout.setQuantityForProduct(event.productTitle, 3);
    await checkout.continueToCheckout();
    await checkout.fillOrderDetails(buyer(uniqueEmail('buyer')));

    await expect(page.getByText('$21.66')).toBeVisible();
    await expect(page.getByText('$43.34')).toBeVisible();
    await expect(page.getByText('$65.00').first()).toBeVisible();
  });

  test('a per-order discount is split pro-rata across multiple products', async ({ page, api, account }) => {
    const event = await createLiveEventWithPaidTicket(api, account.organizerId, 50);
    const categories = await api.listProductCategories(event.eventId);
    await api.createProduct(event.eventId, {
      title: 'Cheap Ticket',
      product_type: 'TICKET',
      type: 'PAID',
      product_category_id: categories[0].id,
      prices: [{ price: 25 }],
    });
    const code = uniqueCode();
    await api.createPromoCode(event.eventId, {
      code,
      discount_type: 'FIXED',
      discount: 30,
      discount_applies_to: 'ORDER',
    });

    const checkout = new CheckoutPage(page);
    await checkout.gotoPublicEvent(event.eventId, event.slug);
    await checkout.applyPromoCode(code);
    await expect(page.locator('.hi-promo-code-applied')).toBeVisible();

    await checkout.setQuantityForProduct(event.productTitle, 1);
    await checkout.setQuantityForProduct('Cheap Ticket', 2);
    await checkout.continueToCheckout();
    await checkout.fillOrderDetails(buyer(uniqueEmail('buyer')));

    await expect(summaryLineItem(page, event.productTitle).getByText('$35.00')).toBeVisible();
    await expect(summaryLineItem(page, event.productTitle).getByText('$50.00')).toBeVisible();
    await expect(summaryLineItem(page, 'Cheap Ticket').getByText('$35.00')).toBeVisible();
    await expect(summaryLineItem(page, 'Cheap Ticket').getByText('$50.00')).toBeVisible();
  });

  test('a no-discount code applies without changing any price', async ({ page, api, account }) => {
    const event = await createLiveEventWithPaidTicket(api, account.organizerId);
    const code = uniqueCode();
    await api.createPromoCode(event.eventId, { code, discount_type: 'NONE' });

    const checkout = new CheckoutPage(page);
    await checkout.gotoPublicEvent(event.eventId, event.slug);
    await checkout.applyPromoCode(code);

    await expect(page.locator('.hi-promo-code-applied')).toBeVisible();
    const productRow = page.locator('.hi-product-row').filter({ hasText: event.productTitle });
    await expect(productRow.getByText('$25.00')).toBeVisible();
    await expect(productRow.locator('.hi-price-tier-price-amount')).toHaveCount(1);

    await checkout.setQuantityForProduct(event.productTitle, 2);
    await checkout.continueToCheckout();
    await checkout.fillOrderDetails(buyer(uniqueEmail('buyer')));

    await expect(summaryLineItem(page, event.productTitle).getByText('$50.00')).toHaveCount(1);
  });

  test('applying an invalid promo code shows an error', async ({ page, api, account }) => {
    const event = await createLiveEventWithPaidTicket(api, account.organizerId);

    const checkout = new CheckoutPage(page);
    await checkout.gotoPublicEvent(event.eventId, event.slug);
    await checkout.applyPromoCode('BOGUS123');

    await expect(page.getByText('That promo code is invalid')).toBeVisible();
  });
});
