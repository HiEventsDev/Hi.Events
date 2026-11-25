import { test, expect } from '../../fixtures';
import { OrderPage } from '../../pages/order.page';
import { OrderSummaryPage } from '../../pages/order-summary.page';
import { TicketPage } from '../../pages/ticket.page';
import { TransferClaimPage } from '../../pages/transfer-claim.page';
import { createCompletedOrder } from '../../api/factory';
import { getPublicOrder } from '../../api/public-client';
import { canTakeStripePayments, listPaymentIntentRefunds, STRIPE_PAYMENTS_SKIP_REASON } from '../../api/stripe';
import { liftResaleThrottles } from '../../utils/resale';
import {
  adoptSellerSession,
  createSoldOutEventWithListings,
  payReservedOrderByCard,
  settleSellerRefunds,
  waitForOrderStatus,
  waitForSellerRefund,
} from '../../utils/stripe-resale';
import { maskedEmail, waitForClaimLink } from '../../utils/transfers';
import { uniqueEmail } from '../../utils/unique';

test.describe('transferring one ticket and reselling another from the same order', () => {
  test.skip(!canTakeStripePayments(), STRIPE_PAYMENTS_SKIP_REASON);
  test.beforeEach(() => liftResaleThrottles());

  test('the recipient of a transfer cannot resell, the buyer resells a second ticket, and the organizer sees both in the order history', { tag: '@stripe' }, async ({ page, authedPage, api, account, publicApi, mailpit }) => {
    test.setTimeout(240_000);
    const { event, seller, session } = await createSoldOutEventWithListings(api, publicApi, mailpit, account.organizerId, {
      sellerTickets: 2,
      listed: 0,
    });
    const recipientEmail = uniqueEmail('recipient');

    await adoptSellerSession(page, event.eventId, seller.orderShortId, session);
    const summary = new OrderSummaryPage(page);
    await summary.goto(event.eventId, seller.orderShortId);
    await expect(summary.sellButton()).toHaveCount(2);
    await summary.startTransfer(recipientEmail);
    await expect(summary.pendingTransfer()).toBeVisible();

    const claim = new TransferClaimPage(page);
    await claim.open((await waitForClaimLink(mailpit, recipientEmail)).href);
    await claim.accept('Grace', 'Hopper');
    const ticketPage = new TicketPage(page);
    await expect(ticketPage.heading(event.title)).toBeVisible();
    await expect(ticketPage.qrCode()).toBeVisible();
    await expect(summary.sellButton()).toHaveCount(0);
    await expect(page.getByRole('button', { name: /sell/i })).toHaveCount(0);

    await summary.goto(event.eventId, seller.orderShortId);
    await expect(summary.transferredTickets()).toContainText(maskedEmail(recipientEmail));
    await expect(summary.sellButton()).toHaveCount(1);
    await summary.sellButton().click();
    await summary.confirmSell();
    await expect(summary.listedForResale()).toHaveCount(1);
    const [keptTicket] = (await getPublicOrder(publicApi, event.eventId, seller.orderShortId, seller.sessionId)).attendees ?? [];

    const buyerOrder = await createCompletedOrder(publicApi, event, { buyerLastName: 'Replacement' });
    await payReservedOrderByCard(publicApi, event.eventId, buyerOrder);
    await waitForOrderStatus(publicApi, event.eventId, buyerOrder);
    const { listing, refund } = await waitForSellerRefund(publicApi, seller, keptTicket.short_id);
    expect(listing).toMatchObject({ status: 'SOLD', soldOrder: buyerOrder.orderShortId });
    expect(await listPaymentIntentRefunds(publicApi, seller.paymentIntentId)).toHaveLength(1);
    expect(refund.amount).toBeLessThan(seller.amountPaidMinor / 2);
    await settleSellerRefunds(publicApi, seller, keptTicket.short_id);

    await summary.goto(event.eventId, seller.orderShortId);
    await expect(page.getByTestId('order-guest').filter({ hasText: 'Resold' })).toHaveCount(1);
    await expect(summary.sellButton()).toHaveCount(0);

    const orders = new OrderPage(authedPage);
    await orders.goto(event.eventId);
    await orders.chooseRowAction(seller.buyerEmail, 'Manage order');
    const history = orders.detailsDrawer().getByRole('list', { name: 'History' });
    for (const step of ['Transfer started', 'Transferred to a new holder', 'Listed for resale', 'Resold to a new buyer', 'Resale refund sent to the buyer']) {
      await expect(history.getByText(step).first()).toBeVisible();
    }
  });
});
