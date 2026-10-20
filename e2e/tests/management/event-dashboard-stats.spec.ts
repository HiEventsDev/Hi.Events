import { test, expect } from '../../fixtures';
import {
  createCompletedOrder,
  createCompletedPaidOrder,
  createLiveEventWithProduct,
  createRecurringLiveEvent,
} from '../../api/factory';
import { EventDashboardPage, type EventStatKey } from '../../pages/event-dashboard.page';
import { uniqueEmail } from '../../utils/unique';

type Ledger = Partial<Record<EventStatKey, string>>;

const formatUsd = (amount: number): string => `$${amount.toFixed(2)}`;

async function expectLedger(
  dashboard: EventDashboardPage,
  eventId: number,
  expected: Ledger,
  occurrenceId?: number,
): Promise<void> {
  await expect(async () => {
    if (occurrenceId) {
      await dashboard.gotoOccurrence(eventId, occurrenceId);
    } else {
      await dashboard.goto(eventId);
    }
    for (const [key, value] of Object.entries(expected) as [EventStatKey, string][]) {
      await expect(dashboard.stat(key)).toHaveText(value, { timeout: 5_000 });
    }
  }).toPass({ timeout: 60_000, intervals: [2_000, 3_000, 5_000] });
}

test.describe('event dashboard stats ledger', () => {
  test('every order path adds up on the dashboard: paid, manual attendee, order cancel, attendee cancel and reactivate', async ({
    authedPage,
    api,
    account,
    publicApi,
  }) => {
    const event = await createLiveEventWithProduct(api, { organizerId: account.organizerId, price: 25 });
    const dashboard = new EventDashboardPage(authedPage);

    const orderA = await createCompletedPaidOrder(api, publicApi, event, { quantity: 2 });
    const orderB = await createCompletedPaidOrder(api, publicApi, event, { quantity: 1 });
    const manualAmountPaid = 10;
    await api.createAttendee(event.eventId, {
      product_id: event.productId,
      product_price_id: event.priceId,
      email: uniqueEmail('manual'),
      first_name: 'Manual',
      last_name: 'Attendee',
      amount_paid: manualAmountPaid,
      send_confirmation_email: false,
      locale: 'en',
    });
    const grossSales = formatUsd(orderA.totalGross + orderB.totalGross + manualAmountPaid);

    await expectLedger(dashboard, event.eventId, {
      attendees: '4',
      products_sold: '4',
      gross_sales: grossSales,
      orders: '3',
    });

    await api.cancelOrder(event.eventId, orderB.orderId);

    await expectLedger(dashboard, event.eventId, {
      attendees: '3',
      products_sold: '3',
      gross_sales: grossSales,
      orders: '2',
    });

    const attendeeId = await api.findAttendeeIdByPublicId(event.eventId, orderA.attendees[0].publicId);
    await api.updateAttendeeStatus(event.eventId, attendeeId, 'CANCELLED');

    await expectLedger(dashboard, event.eventId, {
      attendees: '2',
      products_sold: '3',
      orders: '2',
    });

    await api.updateAttendeeStatus(event.eventId, attendeeId, 'ACTIVE');

    await expectLedger(dashboard, event.eventId, {
      attendees: '3',
      products_sold: '3',
      gross_sales: grossSales,
      orders: '2',
    });

    const product = await api.getProduct(event.eventId, event.productId);
    expect(product.prices?.[0]?.quantity_sold).toBe(3);
  });

  test('a recurring event keeps per-occurrence stats separate and the event total is their sum', async ({
    authedPage,
    api,
    account,
    publicApi,
  }) => {
    const event = await createRecurringLiveEvent(api, account.organizerId, { count: 2, price: 0 });
    const [first, second] = event.occurrences;
    const dashboard = new EventDashboardPage(authedPage);

    await createCompletedOrder(publicApi, event, { quantity: 2, eventOccurrenceId: first.id });
    const secondOrder = await createCompletedOrder(publicApi, event, { quantity: 1, eventOccurrenceId: second.id });

    await expectLedger(dashboard, event.eventId, { attendees: '3', products_sold: '3', orders: '2' });
    await expectLedger(dashboard, event.eventId, { attendees: '2', products_sold: '2', orders: '1' }, first.id);
    await expectLedger(dashboard, event.eventId, { attendees: '1', products_sold: '1', orders: '1' }, second.id);

    const secondOrderId = await api.findOrderIdByShortId(event.eventId, secondOrder.orderShortId);
    await api.cancelOrder(event.eventId, secondOrderId);

    await expectLedger(dashboard, event.eventId, { attendees: '2', products_sold: '2', orders: '1' });
    await expectLedger(dashboard, event.eventId, { attendees: '2', products_sold: '2', orders: '1' }, first.id);
    await expectLedger(dashboard, event.eventId, { attendees: '0', products_sold: '0', orders: '0' }, second.id);
  });
});
