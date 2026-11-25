import type { Page } from '@playwright/test';
import { test, expect } from '../../fixtures';
import { CheckoutPage } from '../../pages/checkout.page';
import { ProductEditPage } from '../../pages/product-edit.page';
import { PublicEventPage } from '../../pages/public-event.page';
import { createCompletedOrder, createReservedOrder } from '../../api/factory';
import { canTakeStripePayments, getPaymentIntentFees, getPaymentIntentRefundedAmount, STRIPE_PAYMENTS_SKIP_REASON } from '../../api/stripe';
import { liftResaleThrottles } from '../../utils/resale';
import {
  attemptOrder,
  createSoldOutEventWithListings,
  expectOrderRefusedAsSoldOut,
  formatMoney,
  listingFor,
  payReservedOrderByCard,
  RESALE_TICKET_PRICE,
  waitForOrderStatus,
  waitForSellerRefund,
} from '../../utils/stripe-resale';
import { uniqueCode } from '../../utils/unique';

async function changeTicketPrice(page: Page, eventId: number, price: number): Promise<void> {
  const products = new ProductEditPage(page);
  await products.goto(eventId);
  await products.openFirstProduct();
  await products.setPrice(price);
  await products.submitEdit();
}

test.describe('a seller is not undersold', () => {
  test.skip(!canTakeStripePayments(), STRIPE_PAYMENTS_SKIP_REASON);
  test.beforeEach(() => liftResaleThrottles());

  test('a promo code that lowers the price cannot take a listed ticket', { tag: '@stripe' }, async ({ page, api, account, publicApi, mailpit }) => {
    test.slow();
    const { event, seller } = await createSoldOutEventWithListings(api, publicApi, mailpit, account.organizerId);
    const sellerTicket = seller.attendees[0];
    const promo = await api.createPromoCode(event.eventId, {
      code: uniqueCode('HALF'),
      discount_type: 'PERCENTAGE',
      discount: 50,
      applicable_product_ids: [event.productId],
    });

    await new PublicEventPage(page).expectTicketsAvailable(event, 1);
    const checkout = new CheckoutPage(page);
    await checkout.applyPromoCode(promo.code);
    await expect(page.locator('.hi-product-row').getByText(formatMoney(2000, event.currency))).toBeVisible();
    await checkout.setFirstProductQuantity(1);
    await page.getByTestId('checkout-continue-button').click();

    await expect(page.getByText(/can only be bought at full price/).first()).toBeVisible();
    await expect(page).not.toHaveURL(/\/checkout\/\d+\/[^/]+\/details/);
    const discountedOrder = await attemptOrder(publicApi, event, { promoCode: promo.code });
    expect(discountedOrder.status()).toBe(422);
    expect(await discountedOrder.text()).toContain('can only be bought at full price');
    expect(listingFor(sellerTicket.shortId)).toMatchObject({ status: 'LISTED', claimedByOrder: null });

    const fullPriceOrder = await createReservedOrder(publicApi, event);
    expect(listingFor(sellerTicket.shortId).claimedByOrder).toBe(fullPriceOrder.orderShortId);
  });

  test('lowering the ticket price below what the seller paid takes the listed ticket off sale', { tag: '@stripe' }, async ({ page, authedPage, api, account, publicApi, mailpit }) => {
    test.slow();
    const { event, seller } = await createSoldOutEventWithListings(api, publicApi, mailpit, account.organizerId);
    const publicPage = new PublicEventPage(page);
    await publicPage.expectTicketsAvailable(event, 1);

    await changeTicketPrice(authedPage, event.eventId, RESALE_TICKET_PRICE - 10);

    await publicPage.expectTicketSoldOut(event);
    await expectOrderRefusedAsSoldOut(await attemptOrder(publicApi, event));
    expect(listingFor(seller.attendees[0].shortId)).toMatchObject({ status: 'LISTED', claimedByOrder: null });
  });

  test('raising the ticket price never refunds the seller more than they paid', { tag: '@stripe' }, async ({ page, authedPage, api, account, publicApi, mailpit }) => {
    test.slow();
    const { event, seller } = await createSoldOutEventWithListings(api, publicApi, mailpit, account.organizerId);
    const sellerTicket = seller.attendees[0];
    const raisedPrice = RESALE_TICKET_PRICE + 20;

    await changeTicketPrice(authedPage, event.eventId, raisedPrice);

    const publicPage = new PublicEventPage(page);
    await publicPage.expectTicketsAvailable(event, 1);
    await expect(publicPage.ticketRow(event.productTitle)).toContainText(formatMoney(raisedPrice * 100, event.currency));

    const buyerOrder = await createCompletedOrder(publicApi, event, { buyerLastName: 'Later' });
    const buyerPayment = await payReservedOrderByCard(publicApi, event.eventId, buyerOrder);
    expect(buyerPayment.amount).toBe(raisedPrice * 100);
    await waitForOrderStatus(publicApi, event.eventId, buyerOrder);

    const { listing, refund } = await waitForSellerRefund(publicApi, seller, sellerTicket.shortId);
    const buyerFees = await getPaymentIntentFees(publicApi, buyerPayment.paymentIntentId);
    expect(listing).toMatchObject({ status: 'SOLD', soldOrder: buyerOrder.orderShortId });
    expect(refund.amount).toBe(seller.amountPaidMinor - buyerFees);
    expect(refund.amount).toBeLessThanOrEqual(seller.amountPaidMinor);
    expect(await getPaymentIntentRefundedAmount(publicApi, seller.paymentIntentId)).toBe(refund.amount);
  });
});
