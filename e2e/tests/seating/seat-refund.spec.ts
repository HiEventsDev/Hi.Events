import type {APIRequestContext, Page} from '@playwright/test';
import {test, expect} from '../../fixtures';
import {OrderPage} from '../../pages/order.page';
import {createSeatedEvent, createSeatedOrder, type SeatedEvent, type SeededOrder} from '../../api/factory';
import type {ApiClient} from '../../api/api-client';
import {
  canTakeStripePayments,
  enableStripePayments,
  payOrderWithTestCard,
  sendPaymentIntentSucceededWebhook,
  STRIPE_PAYMENTS_SKIP_REASON,
} from '../../api/stripe';

const SEAT = 'e2.0.9';
const SEAT_LABEL = 'Stalls · A-10';

async function createCardPaidSeatedOrder(api: ApiClient, publicApi: APIRequestContext, organizerId: number): Promise<{event: SeatedEvent; order: SeededOrder}> {
  const event = await createSeatedEvent(api, organizerId, {price: 30});
  enableStripePayments(organizerId);
  const order = await createSeatedOrder(publicApi, event, [SEAT]);
  const {paymentIntentId} = await payOrderWithTestCard(publicApi, {eventId: event.eventId, ...order});
  await sendPaymentIntentSucceededWebhook(publicApi, paymentIntentId);
  await expect.poll(async () => (await api.listOrders(event.eventId))[0]?.status, {timeout: 45_000}).toBe('COMPLETED');
  return {event, order};
}

async function refund(page: Page, eventId: number, buyerEmail: string, alsoCancel: boolean): Promise<void> {
  const orders = new OrderPage(page);
  await orders.goto(eventId);
  await orders.chooseRowAction(buyerEmail, 'Refund order');
  await expect(page.getByRole('heading', {name: /^Refund Order/}).first()).toBeVisible();
  if (alsoCancel) {
    await page.getByRole('checkbox', {name: /Also cancel this order/}).check();
  }
  await page.getByRole('button', {name: 'Process Refund'}).click();
  await expect(orders.rowByEmail(buyerEmail).getByText(/Refund pending|Refunded/)).toBeVisible();
}

async function inspectSeatOnSalesMap(page: Page, eventId: number, soldCount: number): Promise<void> {
  await page.goto(`/manage/event/${eventId}/seating/sales`);
  await expect(page.getByText(`Sold · ${soldCount}`)).toBeVisible();
  await page.getByTestId('seating-sales-map').locator(`[data-uid="${SEAT}"]`).click();
}

test.describe('refunding a seated order', () => {
  test.skip(!canTakeStripePayments(), STRIPE_PAYMENTS_SKIP_REASON);

  test.beforeEach(async ({adminApi, account}) => {
    await adminApi.setAccountFeatureFlag(await adminApi.findAccountIdByEmail(account.email), 'seating', true);
  });

  test('a refund on its own leaves the buyer in their seat', {tag: '@stripe'}, async ({authedPage: page, api, account, publicApi}) => {
    test.slow();
    const {event, order} = await createCardPaidSeatedOrder(api, publicApi, account.organizerId);

    await refund(page, event.eventId, order.buyerEmail, false);

    await inspectSeatOnSalesMap(page, event.eventId, 1);
    await expect(page.getByTestId('seating-sales-seat-detail')).toContainText(SEAT_LABEL);
    await expect(page.getByTestId('seating-sales-seat-detail')).toContainText('Sold to');
  });

  test('a refund that also cancels the order frees the seat', {tag: '@stripe'}, async ({authedPage: page, api, account, publicApi}) => {
    test.slow();
    const {event, order} = await createCardPaidSeatedOrder(api, publicApi, account.organizerId);

    await refund(page, event.eventId, order.buyerEmail, true);

    await expect.poll(() => api.occupiedSeats(event.eventId, event.occurrenceId)).toEqual([]);
    await inspectSeatOnSalesMap(page, event.eventId, 0);
    await expect(page.getByTestId('seating-sales-seat-detail')).toHaveCount(0);
    await expect(page.getByTestId('seating-sales-block-button')).toHaveText('Hold back 1 seats');
  });
});
