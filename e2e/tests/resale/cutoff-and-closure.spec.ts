import type { APIRequestContext } from '@playwright/test';
import { test, expect } from '../../fixtures';
import type { ApiClient } from '../../api/api-client';
import { AttendeePage } from '../../pages/attendee.page';
import { CheckInPage } from '../../pages/check-in.page';
import { OrderSummaryPage } from '../../pages/order-summary.page';
import { PublicEventPage } from '../../pages/public-event.page';
import { TicketPage } from '../../pages/ticket.page';
import { createReservedOrder, type SeededEvent } from '../../api/factory';
import { canTakeStripePayments, getPaymentIntentRefundedAmount, STRIPE_PAYMENTS_SKIP_REASON } from '../../api/stripe';
import type { MailpitClient } from '../../utils/mailpit';
import { liftResaleThrottles } from '../../utils/resale';
import {
  attemptOrder,
  createCardPaidOrder,
  createResaleEvent,
  createSoldOutEventWithListings,
  expectOrderRefusedAsSoldOut,
  listingFor,
  listTicketForResale,
  moveEventStart,
  RESALE_TICKET_PRICE,
  runResaleMaintenance,
  scanTicket,
  startSellerSession,
  waitUntilResellable,
  type CardPaidOrder,
} from '../../utils/stripe-resale';
import { uniqueName, uniqueShort } from '../../utils/unique';

const OTHER_TICKET = 'Balcony Ticket';
const CLOSED_SUBJECT = 'Your ticket resale listing was closed';

const lastName = (prefix: string): string => uniqueShort(prefix).replace(' ', '');

async function addOtherTicketType(api: ApiClient, event: SeededEvent): Promise<void> {
  const [category] = await api.listProductCategories(event.eventId);
  await api.createProduct(event.eventId, {
    title: OTHER_TICKET,
    product_type: 'TICKET',
    type: 'PAID',
    product_category_id: category.id,
    prices: [{ price: RESALE_TICKET_PRICE }],
  });
}

async function sellerWithListing(
  api: ApiClient,
  publicApi: APIRequestContext,
  mailpit: MailpitClient,
  event: SeededEvent,
  buyerLastName: string,
): Promise<CardPaidOrder> {
  const seller = await createCardPaidOrder(api, publicApi, event, { buyerLastName });
  await waitUntilResellable(publicApi, event.eventId, seller);
  const session = await startSellerSession(publicApi, mailpit, event.eventId, seller);
  await listTicketForResale(publicApi, event.eventId, seller.orderShortId, seller.attendees[0].shortId, session);
  return seller;
}

test.describe('when a listed ticket can no longer be resold', () => {
  test.skip(!canTakeStripePayments(), STRIPE_PAYMENTS_SKIP_REASON);
  test.beforeEach(() => liftResaleThrottles());

  test('an unsold listing closes after the resale cutoff and the seller can use the ticket', { tag: '@stripe' }, async ({ page, api, account, publicApi, mailpit }) => {
    test.slow();
    const sellerLastName = lastName('Seller');
    const { event, seller } = await createSoldOutEventWithListings(api, publicApi, mailpit, account.organizerId, {
      sellerLastName,
      event: { settings: { ticket_resale_cutoff_minutes: 120 } },
    });
    const sellerTicket = seller.attendees[0];
    const door = await api.createCheckInList(event.eventId, { name: uniqueName('Main Door') });
    const publicPage = new PublicEventPage(page);
    await publicPage.expectTicketsAvailable(event, 1);

    moveEventStart(event.eventId, 60);

    await publicPage.expectTicketSoldOut(event);
    await expectOrderRefusedAsSoldOut(await attemptOrder(publicApi, event));
    runResaleMaintenance();

    expect(listingFor(sellerTicket.shortId)).toMatchObject({ status: 'CLOSED', closedReason: 'CUTOFF_PASSED' });
    const unsold = await mailpit.waitForMessage(seller.buyerEmail, { subjectContains: 'Your ticket did not sell and is yours to use' });
    expect((await mailpit.getLinks(unsold.ID)).some((link) => link.includes(`/checkout/${event.eventId}/${seller.orderShortId}/summary`))).toBe(true);
    expect(await getPaymentIntentRefundedAmount(publicApi, seller.paymentIntentId)).toBe(0);

    const summary = new OrderSummaryPage(page);
    await summary.goto(event.eventId, seller.orderShortId);
    await expect(summary.listedForResale()).toHaveCount(0);
    await expect(summary.guest(sellerLastName).getByRole('button', { name: 'View Ticket' })).toBeVisible();

    const checkIn = new CheckInPage(page);
    await checkIn.goto(door.short_id);
    await checkIn.openSearchTab();
    await checkIn.search(sellerLastName);
    await checkIn.checkInButton(sellerTicket.publicId).click();
    await expect(checkIn.checkOutButton(sellerTicket.publicId)).toBeVisible();
  });

  test('turning resale off takes the listed ticket off sale and lets the seller in at the door', { tag: '@stripe' }, async ({ page, api, account, publicApi, mailpit }) => {
    test.slow();
    const { event, seller } = await createSoldOutEventWithListings(api, publicApi, mailpit, account.organizerId);
    const sellerTicket = seller.attendees[0];
    const door = await api.createCheckInList(event.eventId, { name: uniqueName('Main Door') });
    const publicPage = new PublicEventPage(page);
    await publicPage.expectTicketsAvailable(event, 1);

    await api.updateEventSettings(event.eventId, { allow_ticket_resale: false });

    await publicPage.expectTicketSoldOut(event);
    await expectOrderRefusedAsSoldOut(await attemptOrder(publicApi, event));
    const ticketPage = new TicketPage(page);
    await ticketPage.goto(event.eventId, sellerTicket.shortId);
    await expect(ticketPage.qrCode()).toBeVisible();
    await expect(ticketPage.unusableNotice()).toHaveCount(0);

    expect((await scanTicket(publicApi, door.short_id, sellerTicket.publicId)).checkedIn).toBe(true);
    expect(listingFor(sellerTicket.shortId).status).toBe('CLOSED');
  });

  test('cancelling the ticket, changing its type or cancelling the date closes the listing and tells the seller', { tag: '@stripe' }, async ({ authedPage: page, api, account, publicApi, mailpit }) => {
    test.setTimeout(240_000);
    const event = await createResaleEvent(api, account.organizerId, { quantityAvailable: 3 });
    await addOtherTicketType(api, event);
    const cancelled = await sellerWithListing(api, publicApi, mailpit, event, lastName('Cancelled'));
    const moved = await sellerWithListing(api, publicApi, mailpit, event, lastName('Moved'));
    const dateCancelled = await sellerWithListing(api, publicApi, mailpit, event, lastName('Dateless'));

    const attendees = new AttendeePage(page);
    await attendees.goto(event.eventId);
    await attendees.cancelTicket(cancelled.buyerLastName);
    await expect(page.getByText('Successfully cancelled attendee')).toBeVisible();
    expect(listingFor(cancelled.attendees[0].shortId)).toMatchObject({ status: 'CLOSED', closedReason: 'TICKET_CANCELLED' });
    await mailpit.waitForMessage(cancelled.buyerEmail, { subjectContains: CLOSED_SUBJECT });

    await attendees.openRowAction(moved.buyerLastName, 'Manage attendee');
    await attendees.changeTicketType(OTHER_TICKET);
    await expect(page.getByText('Successfully updated attendee')).toBeVisible();
    expect(listingFor(moved.attendees[0].shortId)).toMatchObject({ status: 'CLOSED', closedReason: 'TICKET_TYPE_CHANGED' });
    await mailpit.waitForMessage(moved.buyerEmail, { subjectContains: CLOSED_SUBJECT });
    await attendees.closeDrawer();

    expect(listingFor(dateCancelled.attendees[0].shortId).status).toBe('LISTED');
    const [date] = await api.listOccurrences(event.eventId);
    await api.cancelOccurrence(event.eventId, date.id);
    await expect.poll(() => listingFor(dateCancelled.attendees[0].shortId).status, { timeout: 30_000 }).toBe('CLOSED');
    expect(listingFor(dateCancelled.attendees[0].shortId).closedReason).toBe('DATE_CANCELLED');
    await mailpit.waitForMessage(dateCancelled.buyerEmail, { subjectContains: CLOSED_SUBJECT });
  });

  test('a ticket type change is refused while a buyer is checking out with the listed ticket', { tag: '@stripe' }, async ({ authedPage: page, api, account, publicApi, mailpit }) => {
    test.slow();
    const sellerLastName = lastName('Seller');
    const { event, seller } = await createSoldOutEventWithListings(api, publicApi, mailpit, account.organizerId, { sellerLastName });
    await addOtherTicketType(api, event);
    const holding = await createReservedOrder(publicApi, event);

    const attendees = new AttendeePage(page);
    await attendees.goto(event.eventId);
    await attendees.openRowAction(sellerLastName, 'Manage attendee');
    await attendees.changeTicketType(OTHER_TICKET);

    await expect(page.getByText(/A buyer is checking out with this ticket right now/).first()).toBeVisible();
    expect(listingFor(seller.attendees[0].shortId)).toMatchObject({ status: 'LISTED', claimedByOrder: holding.orderShortId });
    const sellerAttendee = (await api.listAttendees(event.eventId)).find((attendee) => attendee.public_id === seller.attendees[0].publicId);
    expect(sellerAttendee?.product_id).toBe(event.productId);
  });
});
