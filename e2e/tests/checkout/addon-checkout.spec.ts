import { test, expect } from '../../fixtures';
import { CheckoutPage, setWidgetQuantity } from '../../pages/checkout.page';
import { createDraftEvent, enableOfflinePayments } from '../../api/factory';
import { uniqueEmail } from '../../utils/unique';

test.describe('add-on checkout', () => {
  test('a buyer sees add-ons under the parent ticket and orders both', async ({ page, api, account }) => {
    const event = await createDraftEvent(api, account.organizerId);
    const categories = await api.listProductCategories(event.eventId);
    const categoryId = categories[0].id;

    const addon = await api.createProduct(event.eventId, {
      title: 'Parking Pass',
      product_type: 'GENERAL',
      type: 'FREE',
      product_category_id: categoryId,
      is_addon_only: true,
      prices: [{ price: 0 }],
    });

    await api.createProduct(event.eventId, {
      title: 'Festival Ticket',
      product_type: 'TICKET',
      type: 'FREE',
      product_category_id: categoryId,
      addon_product_ids: [addon.id],
      prices: [{ price: 0 }],
    });

    await api.publishEvent(event.eventId);

    const checkout = new CheckoutPage(page);
    await checkout.gotoPublicEvent(event.eventId, event.slug);

    await expect(page.locator('.hi-product-row h3').filter({ hasText: 'Festival Ticket' })).toBeVisible();
    await expect(page.locator('.hi-product-row h3').filter({ hasText: 'Parking Pass' })).toHaveCount(0);
    await expect(page.locator('.hi-product-addon').filter({ hasText: 'Parking Pass' })).toBeVisible();
    await expect(page.getByText('Add Festival Ticket first')).toBeVisible();

    await checkout.setQuantityForProduct('Festival Ticket', 1);
    await expect(page.getByText('Add Festival Ticket first')).toHaveCount(0);

    await checkout.setAddonQuantity('Parking Pass', 1);

    await checkout.setQuantityForProduct('Festival Ticket', 0);
    await expect(page.getByText('Add Festival Ticket first')).toBeVisible();
    await expect(page.locator('.hi-product-addon').filter({ hasText: 'Parking Pass' }).locator('input')).toBeHidden();

    await checkout.setQuantityForProduct('Festival Ticket', 1);
    await checkout.setAddonQuantity('Parking Pass', 1);
    await checkout.continueToCheckout();

    await expect(page.getByText('Festival Ticket').first()).toBeVisible();
    await expect(page.getByText('Parking Pass').first()).toBeVisible();
  });

  test('a paid add-on is charged as part of the order total', async ({ page, api, account }) => {
    const event = await createDraftEvent(api, account.organizerId);
    const categories = await api.listProductCategories(event.eventId);
    const categoryId = categories[0].id;

    const addon = await api.createProduct(event.eventId, {
      title: 'Camping Pitch',
      product_type: 'GENERAL',
      type: 'PAID',
      product_category_id: categoryId,
      is_addon_only: true,
      prices: [{ price: 5 }],
    });

    await api.createProduct(event.eventId, {
      title: 'Weekend Ticket',
      product_type: 'TICKET',
      type: 'PAID',
      product_category_id: categoryId,
      addon_product_ids: [addon.id],
      prices: [{ price: 10 }],
    });

    await enableOfflinePayments(api, event.eventId);
    await api.publishEvent(event.eventId);

    const buyer = { firstName: 'Addon', lastName: 'Payer', email: uniqueEmail('addon-payer') };

    const checkout = new CheckoutPage(page);
    await checkout.gotoPublicEvent(event.eventId, event.slug);
    await checkout.setQuantityForProduct('Weekend Ticket', 1);
    await checkout.setAddonQuantity('Camping Pitch', 2);
    await checkout.continueToCheckout();

    await expect(page.getByText('Weekend Ticket').first()).toBeVisible();
    await expect(page.getByText('Camping Pitch').first()).toBeVisible();
    await expect(page.getByText('× 2', { exact: true })).toBeVisible();

    await checkout.fillOrderDetails(buyer);
    await checkout.fillFirstAttendee(buyer);
    await checkout.continueToPayment();
    await checkout.chooseOfflinePayment();

    await expect(page.getByText('Your order is awaiting payment')).toBeVisible();
    await expect(page.getByText('$20.00').first()).toBeVisible();
  });

  test('a product sold on its own can also be an add-on, with both steppers kept in sync', async ({ page, api, account }) => {
    const event = await createDraftEvent(api, account.organizerId);
    const categories = await api.listProductCategories(event.eventId);
    const categoryId = categories[0].id;

    const shirt = await api.createProduct(event.eventId, {
      title: 'Tour Shirt',
      product_type: 'GENERAL',
      type: 'FREE',
      product_category_id: categoryId,
      prices: [{ price: 0 }],
    });

    await api.createProduct(event.eventId, {
      title: 'Gig Ticket',
      product_type: 'TICKET',
      type: 'FREE',
      product_category_id: categoryId,
      addon_product_ids: [shirt.id],
      prices: [{ price: 0 }],
    });

    await api.publishEvent(event.eventId);

    const checkout = new CheckoutPage(page);
    await checkout.gotoPublicEvent(event.eventId, event.slug);

    const shirtRow = page.locator('.hi-product-row').filter({ has: page.locator('h3', { hasText: 'Tour Shirt' }) });
    const shirtAddon = page.locator('.hi-product-addon').filter({ hasText: 'Tour Shirt' });
    await expect(shirtRow).toBeVisible();
    await expect(shirtAddon).toBeVisible();

    await checkout.setQuantityForProduct('Gig Ticket', 1);
    await checkout.setAddonQuantity('Tour Shirt', 2);
    await expect(shirtRow.locator('.hi-product-quantity-selector input').first()).toHaveValue('2');

    await setWidgetQuantity(shirtRow, 1);
    await expect(shirtAddon.locator('input')).toHaveValue('1');

    await checkout.continueToCheckout();

    await expect(page.getByText('Gig Ticket').first()).toBeVisible();
    await expect(page.getByText('Tour Shirt').first()).toBeVisible();
    await expect(page.getByText('× 1', { exact: true })).toHaveCount(2);
  });

  test('a buyer completes an order that includes an add-on', async ({ page, api, account, mailpit }) => {
    const event = await createDraftEvent(api, account.organizerId);
    const categories = await api.listProductCategories(event.eventId);
    const categoryId = categories[0].id;

    const addon = await api.createProduct(event.eventId, {
      title: 'Drink Token',
      product_type: 'GENERAL',
      type: 'FREE',
      product_category_id: categoryId,
      is_addon_only: true,
      prices: [{ price: 0 }],
    });

    await api.createProduct(event.eventId, {
      title: 'Entry Ticket',
      product_type: 'TICKET',
      type: 'FREE',
      product_category_id: categoryId,
      addon_product_ids: [addon.id],
      prices: [{ price: 0 }],
    });

    await api.publishEvent(event.eventId);

    const buyerEmail = uniqueEmail('addon-buyer');
    const buyer = { firstName: 'Addon', lastName: 'Buyer', email: buyerEmail };

    const checkout = new CheckoutPage(page);
    await checkout.gotoPublicEvent(event.eventId, event.slug);
    await checkout.setQuantityForProduct('Entry Ticket', 1);
    await checkout.setAddonQuantity('Drink Token', 1);
    await checkout.continueToCheckout();
    await checkout.fillOrderDetails(buyer);
    await checkout.fillFirstAttendee(buyer);
    await checkout.completeFreeOrder();

    await expect(page.getByText(`You're going to ${event.title}`)).toBeVisible();
    await mailpit.waitForMessage(buyerEmail);
  });
});
