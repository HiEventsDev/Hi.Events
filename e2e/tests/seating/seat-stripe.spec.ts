import type {Page} from '@playwright/test';
import {test, expect} from '../../fixtures';
import {CheckoutPage} from '../../pages/checkout.page';
import {SeatPickerPage} from '../../pages/seat-picker.page';
import {createSeatedEvent, createSeatedOrder, type SeatedEvent} from '../../api/factory';
import type {ApiClient} from '../../api/api-client';
import {
  canTakeStripePayments,
  deliverPaymentIntentSucceededWebhook,
  enableStripePayments,
  getPaymentIntentAmount,
  getPaymentIntentRefundedAmount,
  parsePaymentReturnUrl,
  payOrderWithTestCard,
  sendPaymentIntentSucceededWebhook,
  STRIPE_PAYMENTS_SKIP_REASON,
} from '../../api/stripe';
import {expireOrder} from '../../utils/db';
import {uniqueEmail} from '../../utils/unique';

const BASE_PRICE = 30;
const PREMIUM_ADJUSTMENT_MINOR = 1500;
const PREMIUM_SEAT = 'e2.0.0';
const PREMIUM_SEAT_LABEL = 'Stalls · A-1';

async function createPaidSeatedEvent(api: ApiClient, organizerId: number): Promise<SeatedEvent> {
  const event = await createSeatedEvent(api, organizerId, {price: BASE_PRICE});
  await api.linkSeatMapBands(event.eventId, [
    {band_key: 'b_premium', products: [{product_id: event.productId, price_adjustment: PREMIUM_ADJUSTMENT_MINOR}]},
    {band_key: 'b_standard', products: [{product_id: event.productId, price_adjustment: 0}]},
  ]);
  await api.updateEventSettings(event.eventId, {pass_platform_fee_to_buyer: false});
  enableStripePayments(organizerId);
  return event;
}

async function expectOrderSummary(page: Page, eventId: number, orderShortId: string, text: string | RegExp): Promise<void> {
  await expect(async () => {
    await page.goto(`/checkout/${eventId}/${orderShortId}/summary`);
    await expect(page.getByText(text).first()).toBeVisible({timeout: 3_000});
  }).toPass({timeout: 45_000});
}

test.describe('paying for seats by card', () => {
  test.skip(!canTakeStripePayments(), STRIPE_PAYMENTS_SKIP_REASON);

  test.beforeEach(async ({adminApi, account}) => {
    await adminApi.setAccountFeatureFlag(await adminApi.findAccountIdByEmail(account.email), 'seating', true);
  });

  test('a buyer is charged the price of the band their seat is in', {tag: '@stripe'}, async ({page, api, account, publicApi}) => {
    test.slow();
    const event = await createPaidSeatedEvent(api, account.organizerId);
    const buyer = {firstName: 'Carla', lastName: 'Card', email: uniqueEmail('seatcard')};

    const checkout = new CheckoutPage(page);
    await checkout.gotoPublicEvent(event.eventId, event.slug);
    const picker = new SeatPickerPage(page);
    await picker.selectSeat(PREMIUM_SEAT);
    await expect(picker.root().getByText('Premium Seat · $45.00')).toBeVisible();
    await picker.continueToDetails();
    await checkout.fillOrderAndAttendees(buyer, 1);
    await checkout.continueToPayment();
    await checkout.payWithStripeTestCard();

    const {orderShortId, sessionId} = parsePaymentReturnUrl(page.url());
    const paymentIntentId = await deliverPaymentIntentSucceededWebhook(publicApi, {eventId: event.eventId, orderShortId, sessionId});
    expect(await getPaymentIntentAmount(publicApi, paymentIntentId)).toBe((BASE_PRICE * 100) + PREMIUM_ADJUSTMENT_MINOR);

    await expectOrderSummary(page, event.eventId, orderShortId, /You're going to/);
    await expect(page.getByText(PREMIUM_SEAT_LABEL).filter({visible: true}).first()).toBeVisible();
    await expect(page.getByText('$45.00').filter({visible: true}).first()).toBeVisible();
  });

  test('a payment that lands after the hold ran out still completes when nobody took the seat', {tag: '@stripe'}, async ({page, api, account, publicApi}) => {
    test.slow();
    const event = await createPaidSeatedEvent(api, account.organizerId);
    const order = await createSeatedOrder(publicApi, event, [PREMIUM_SEAT]);
    const {paymentIntentId} = await payOrderWithTestCard(publicApi, {eventId: event.eventId, ...order});

    expireOrder(order.orderShortId);
    await sendPaymentIntentSucceededWebhook(publicApi, paymentIntentId);

    await expectOrderSummary(page, event.eventId, order.orderShortId, /You're going to/);
    await expect(page.getByText(PREMIUM_SEAT_LABEL).filter({visible: true}).first()).toBeVisible();
    expect(await getPaymentIntentRefundedAmount(publicApi, paymentIntentId)).toBe(0);
  });

  test('a payment that lands after the seat went to someone else is refunded', {tag: '@stripe'}, async ({page, api, account, publicApi, mailpit}) => {
    test.slow();
    const event = await createPaidSeatedEvent(api, account.organizerId);
    const lateOrder = await createSeatedOrder(publicApi, event, [PREMIUM_SEAT]);
    const {paymentIntentId, amount} = await payOrderWithTestCard(publicApi, {eventId: event.eventId, ...lateOrder});

    expireOrder(lateOrder.orderShortId);

    const checkout = new CheckoutPage(page);
    await checkout.gotoPublicEvent(event.eventId, event.slug);
    const picker = new SeatPickerPage(page);
    await expect(picker.seat(PREMIUM_SEAT)).toHaveAttribute('data-state', 'free');
    await picker.selectSeat(PREMIUM_SEAT);
    await picker.continueToDetails();

    await sendPaymentIntentSucceededWebhook(publicApi, paymentIntentId, {tolerateRejection: true});

    await expect.poll(() => getPaymentIntentRefundedAmount(publicApi, paymentIntentId), {timeout: 45_000}).toBe(amount);
    const apology = await mailpit.waitForMessage(lateOrder.buyerEmail, {subjectContains: 'We were unable to process your order'});
    expect(apology).toBeTruthy();

    const orders = await api.listOrders(event.eventId);
    expect(orders.find((candidate) => candidate.short_id === lateOrder.orderShortId)?.status).not.toBe('COMPLETED');
    await expect(page.getByText(`Premium Seat · ${PREMIUM_SEAT_LABEL}`)).toBeVisible();
  });
});
