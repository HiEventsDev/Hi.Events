import { test, expect } from '../../fixtures';
import { PublicDateView } from '../../pages/public-date.page';
import { PublicEventPage } from '../../pages/public-event.page';
import { createCompletedOrder, createRecurringLiveEvent, createReservedOrder } from '../../api/factory';
import { getPublicOrder } from '../../api/public-client';
import {
  canTakeStripePayments,
  enableStripePayments,
  getPaymentIntentFees,
  getPaymentIntentRefundedAmount,
  listPaymentIntentRefunds,
  STRIPE_PAYMENTS_SKIP_REASON,
} from '../../api/stripe';
import { liftResaleThrottles } from '../../utils/resale';
import {
  attemptOrder,
  createCardPaidOrder,
  createResaleEvent,
  createSoldOutEventWithListings,
  expectOrderRefusedAsSoldOut,
  listingFor,
  listTicketForResale,
  payReservedOrderByCard,
  RESALE_TICKET_PRICE,
  soldCount,
  startSellerSession,
  waitForOrderStatus,
  waitForSellerRefund,
  waitUntilResellable,
} from '../../utils/stripe-resale';

test.describe('unsold tickets sell before listed ones', () => {
  test.skip(!canTakeStripePayments(), STRIPE_PAYMENTS_SKIP_REASON);
  test.beforeEach(() => liftResaleThrottles());

  test('an order that unsold tickets can fill leaves the listing open', { tag: '@stripe' }, async ({ page, api, account, publicApi, mailpit }) => {
    test.slow();
    const { event, seller } = await createSoldOutEventWithListings(api, publicApi, mailpit, account.organizerId, { quantity: 4, unsold: 3 });
    const sellerTicket = seller.attendees[0];
    const publicPage = new PublicEventPage(page);
    await publicPage.expectTicketsAvailable(event, 4);

    const buyerOrder = await createCompletedOrder(publicApi, event, { quantity: 2 });
    expect(listingFor(sellerTicket.shortId).claimedByOrder).toBeNull();
    await payReservedOrderByCard(publicApi, event.eventId, buyerOrder);
    await waitForOrderStatus(publicApi, event.eventId, buyerOrder);

    expect(listingFor(sellerTicket.shortId)).toMatchObject({ status: 'LISTED', claimedByOrder: null, soldOrder: null });
    expect(await getPaymentIntentRefundedAmount(publicApi, seller.paymentIntentId)).toBe(0);
    expect(await soldCount(api, event)).toBe(3);
    await publicPage.expectTicketsAvailable(event, 2);
  });

  test('an order for more than the unsold tickets takes the last one plus the listing and refunds one seller', { tag: '@stripe' }, async ({ page, api, account, publicApi, mailpit }) => {
    test.slow();
    const { event, seller } = await createSoldOutEventWithListings(api, publicApi, mailpit, account.organizerId, { quantity: 2, unsold: 1 });
    const sellerTicket = seller.attendees[0];
    const publicPage = new PublicEventPage(page);
    await publicPage.expectTicketsAvailable(event, 2);

    const buyerOrder = await createCompletedOrder(publicApi, event, { quantity: 2 });
    expect(listingFor(sellerTicket.shortId).claimedByOrder).toBe(buyerOrder.orderShortId);
    const buyerPayment = await payReservedOrderByCard(publicApi, event.eventId, buyerOrder);
    await waitForOrderStatus(publicApi, event.eventId, buyerOrder);

    const issued = (await getPublicOrder(publicApi, event.eventId, buyerOrder.orderShortId, buyerOrder.sessionId)).attendees ?? [];
    expect(issued).toHaveLength(2);
    const { listing, refund } = await waitForSellerRefund(publicApi, seller, sellerTicket.shortId);
    const feesPerTicket = (await getPaymentIntentFees(publicApi, buyerPayment.paymentIntentId)) / 2;
    expect(listing).toMatchObject({ status: 'SOLD', soldOrder: buyerOrder.orderShortId });
    expect(Math.abs(refund.amount - ((RESALE_TICKET_PRICE * 100) - feesPerTicket))).toBeLessThanOrEqual(1);
    expect(await listPaymentIntentRefunds(publicApi, seller.paymentIntentId)).toHaveLength(1);
    expect(await getPaymentIntentRefundedAmount(publicApi, buyerPayment.paymentIntentId)).toBe(0);
    expect(await soldCount(api, event)).toBe(2);
    await publicPage.expectTicketSoldOut(event);
  });

  test('a ticket listed for one date of a recurring event adds availability to that date only', { tag: '@stripe' }, async ({ page, api, account, publicApi, mailpit }) => {
    test.slow();
    const event = await createRecurringLiveEvent(api, account.organizerId, {
      count: 3,
      price: RESALE_TICKET_PRICE,
      quantityAvailable: 1,
      quantityAppliesTo: 'OCCURRENCE',
      showQuantityRemaining: true,
    });
    await api.updateEventSettings(event.eventId, { allow_ticket_resale: true, pass_platform_fee_to_buyer: false });
    enableStripePayments(account.organizerId);
    const [day1, day2, day3] = [...event.occurrences].sort((a, b) => a.start_date.localeCompare(b.start_date));
    const seller = await createCardPaidOrder(api, publicApi, event, { eventOccurrenceId: day1.id });
    await createCardPaidOrder(api, publicApi, event, { eventOccurrenceId: day2.id });
    await waitUntilResellable(publicApi, event.eventId, seller);
    const session = await startSellerSession(publicApi, mailpit, event.eventId, seller);
    await listTicketForResale(publicApi, event.eventId, seller.orderShortId, seller.attendees[0].shortId, session);

    const view = new PublicDateView(page, event);
    await expect(async () => {
      await view.expectRemaining(day1, 1);
    }).toPass({ timeout: 30_000 });
    await view.expectSoldOut(day2);
    await view.expectRemaining(day3, 1);

    await expectOrderRefusedAsSoldOut(await attemptOrder(publicApi, event, { eventOccurrenceId: day2.id }));
    expect(listingFor(seller.attendees[0].shortId).claimedByOrder).toBeNull();
    const day1Order = await createReservedOrder(publicApi, event, { eventOccurrenceId: day1.id });
    expect(listingFor(seller.attendees[0].shortId).claimedByOrder).toBe(day1Order.orderShortId);
  });

  test('a ticket listed on a type that shares a full capacity pool leaves the other type sold out', { tag: '@stripe' }, async ({ page, api, account, publicApi, mailpit }) => {
    test.slow();
    const event = await createResaleEvent(api, account.organizerId, { productTitle: 'Stalls Ticket' });
    const [category] = await api.listProductCategories(event.eventId);
    const circle = await api.createProduct(event.eventId, {
      title: 'Circle Ticket',
      product_type: 'TICKET',
      type: 'PAID',
      product_category_id: category.id,
      prices: [{ price: RESALE_TICKET_PRICE }],
      show_quantity_remaining: true,
    });
    const circleTicket = { eventId: event.eventId, productId: circle.id, priceId: (await api.getProduct(event.eventId, circle.id)).prices![0].id };
    await api.createCapacityAssignment(event.eventId, {
      name: 'Whole room',
      capacity: 1,
      status: 'ACTIVE',
      product_ids: [event.productId, circle.id],
    });
    const seller = await createCardPaidOrder(api, publicApi, event);
    await waitUntilResellable(publicApi, event.eventId, seller);
    const publicPage = new PublicEventPage(page);
    await publicPage.expectTicketSoldOut(event);
    await publicPage.expectTicketSoldOut({ ...event, productTitle: 'Circle Ticket' });

    const session = await startSellerSession(publicApi, mailpit, event.eventId, seller);
    await listTicketForResale(publicApi, event.eventId, seller.orderShortId, seller.attendees[0].shortId, session);

    await publicPage.expectTicketsAvailable(event, 1);
    await expect(publicPage.ticketRow('Circle Ticket')).toHaveAttribute('data-availability', 'sold-out');
    await expectOrderRefusedAsSoldOut(await attemptOrder(publicApi, circleTicket));
    const stallsOrder = await createReservedOrder(publicApi, event);
    expect(listingFor(seller.attendees[0].shortId).claimedByOrder).toBe(stallsOrder.orderShortId);
  });

  test('a listed ticket on a sold-out first tier can be bought without closing the second tier', { tag: '@stripe' }, async ({ page, api, account, publicApi, mailpit }) => {
    test.slow();
    const event = await createResaleEvent(api, account.organizerId, {
      productType: 'TIERED',
      productTitle: 'Tiered Ticket',
      sequentialTierReleaseEnabled: true,
      prices: [
        { price: RESALE_TICKET_PRICE, label: 'Early bird', initial_quantity_available: 1 },
        { price: RESALE_TICKET_PRICE + 10, label: 'General', initial_quantity_available: 5 },
      ],
    });
    const generalPriceId = (await api.getProduct(event.eventId, event.productId)).prices![1].id;
    const seller = await createCardPaidOrder(api, publicApi, event);
    await waitUntilResellable(publicApi, event.eventId, seller);
    const publicPage = new PublicEventPage(page);
    const earlyBirdRow = page.locator('.hi-price-tier-row').filter({ hasText: 'Early bird' });
    const generalRow = page.locator('.hi-price-tier-row').filter({ hasText: 'General' });
    await expect(async () => {
      await publicPage.goto(event.eventId, event.slug);
      await expect(earlyBirdRow.getByText('Sold out')).toBeVisible({ timeout: 2_000 });
    }).toPass({ timeout: 30_000 });
    await expect(generalRow.getByRole('button', { name: 'Increase quantity' })).toBeVisible();

    const session = await startSellerSession(publicApi, mailpit, event.eventId, seller);
    await listTicketForResale(publicApi, event.eventId, seller.orderShortId, seller.attendees[0].shortId, session);

    await expect(async () => {
      await publicPage.goto(event.eventId, event.slug);
      await expect(earlyBirdRow.getByRole('button', { name: 'Increase quantity' })).toBeVisible({ timeout: 2_000 });
    }).toPass({ timeout: 30_000 });
    await expect(earlyBirdRow.getByText('Sold out')).toHaveCount(0);
    await expect(generalRow).not.toHaveAttribute('data-unavailable');
    await expect(generalRow.getByRole('button', { name: 'Increase quantity' })).toBeVisible();

    const earlyBirdOrder = await createReservedOrder(publicApi, event);
    expect(listingFor(seller.attendees[0].shortId).claimedByOrder).toBe(earlyBirdOrder.orderShortId);
    const generalOrder = await attemptOrder(publicApi, { ...event, priceId: generalPriceId });
    expect(generalOrder.ok(), await generalOrder.text()).toBe(true);
    expect(listingFor(seller.attendees[0].shortId)).toMatchObject({ status: 'LISTED', claimedByOrder: earlyBirdOrder.orderShortId });
  });
});
