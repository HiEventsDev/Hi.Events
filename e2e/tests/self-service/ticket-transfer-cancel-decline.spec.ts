import { test, expect } from '../../fixtures';
import { CheckInPage } from '../../pages/check-in.page';
import { OrderSummaryPage } from '../../pages/order-summary.page';
import { TransferClaimPage } from '../../pages/transfer-claim.page';
import { VerifyEmailDialog } from '../../pages/verify-email.page';
import { createCompletedOrder, createLiveEventWithFreeTicket, type SeededEvent, type SeededOrder } from '../../api/factory';
import type { ApiClient } from '../../api/api-client';
import type { MailpitClient } from '../../utils/mailpit';
import type { APIRequestContext, Page } from '@playwright/test';
import { liftSelfServiceThrottles } from '../../utils/throttle';
import { expirePendingTransferTo, maskedEmail, waitForClaimLink } from '../../utils/transfers';
import { uniqueEmail, uniqueName, uniqueShort } from '../../utils/unique';

interface PendingTransfer {
  event: SeededEvent;
  order: SeededOrder;
  recipientEmail: string;
  claimUrl: string;
  summary: OrderSummaryPage;
}

async function startTransfer(
  page: Page,
  api: ApiClient,
  organizerId: number,
  publicApi: APIRequestContext,
  mailpit: MailpitClient,
  recipientEmail = uniqueEmail('recipient'),
): Promise<PendingTransfer> {
  const event = await createLiveEventWithFreeTicket(api, organizerId);
  const order = await createCompletedOrder(publicApi, event, { buyerLastName: uniqueShort('Buyer').replace(' ', '') });
  const summary = new OrderSummaryPage(page);
  await summary.goto(event.eventId, order.orderShortId);
  await summary.startTransfer(recipientEmail);
  await new VerifyEmailDialog(page).confirmWithEmailedCode(mailpit, order.buyerEmail);
  await expect(summary.pendingTransfer()).toBeVisible();

  return { event, order, recipientEmail, summary, claimUrl: (await waitForClaimLink(mailpit, recipientEmail)).href };
}

test.describe('transfers that are not completed', () => {
  test.beforeEach(() => liftSelfServiceThrottles());

  test('the sender cancels and the emailed link stops working', async ({ page, api, account, publicApi, mailpit }) => {
    const transfer = await startTransfer(page, api, account.organizerId, publicApi, mailpit);

    await transfer.summary.cancelTransfer();

    await expect(page.getByText('Transfer cancelled. The ticket is still yours.')).toBeVisible();
    await expect(transfer.summary.pendingTransfer()).toHaveCount(0);
    await expect(transfer.summary.transferButton()).toBeVisible();
    await mailpit.waitForMessage(transfer.recipientEmail, { subjectContains: 'A ticket transfer to you was cancelled' });

    const claim = new TransferClaimPage(page);
    await claim.open(transfer.claimUrl);
    await expect(claim.heading('This transfer was cancelled')).toBeVisible();
    await expect(claim.acceptButton()).toHaveCount(0);
  });

  test('the recipient declines and the sender keeps the ticket', async ({ page, api, account, publicApi, mailpit }) => {
    const transfer = await startTransfer(page, api, account.organizerId, publicApi, mailpit);

    const claim = new TransferClaimPage(page);
    await claim.open(transfer.claimUrl);
    await claim.decline();

    await expect(claim.heading('This transfer was declined')).toBeVisible();
    await mailpit.waitForMessage(transfer.order.buyerEmail, { subjectContains: 'Your ticket transfer was declined' });
    await transfer.summary.goto(transfer.event.eventId, transfer.order.orderShortId);
    await expect(transfer.summary.pendingTransfer()).toHaveCount(0);
    await expect(transfer.summary.transferredTickets()).toHaveCount(0);
    await expect(transfer.summary.transferButton()).toBeVisible();
  });

  test('a transfer that is not accepted in time expires and the sender can start again', async ({ page, api, account, publicApi, mailpit }) => {
    const transfer = await startTransfer(page, api, account.organizerId, publicApi, mailpit);
    expirePendingTransferTo(transfer.recipientEmail);

    const claim = new TransferClaimPage(page);
    await claim.open(transfer.claimUrl);
    await expect(claim.heading('This transfer has expired')).toBeVisible();
    await expect(claim.acceptButton()).toHaveCount(0);

    await transfer.summary.goto(transfer.event.eventId, transfer.order.orderShortId);
    await expect(transfer.summary.pendingTransfer()).toHaveCount(0);
    await expect(transfer.summary.transferButton()).toBeVisible();
  });

  test('a ticket checked in while its transfer was pending can no longer be accepted', async ({ page, api, account, publicApi, mailpit }) => {
    const transfer = await startTransfer(page, api, account.organizerId, publicApi, mailpit);
    const list = await api.createCheckInList(transfer.event.eventId, { name: uniqueName('Main Door') });

    const checkIn = new CheckInPage(page);
    await checkIn.goto(list.short_id);
    await checkIn.openSearchTab();
    await checkIn.search(transfer.order.buyerLastName);
    await checkIn.checkInButton(transfer.order.attendees[0].publicId).click();
    await expect(checkIn.checkOutButton(transfer.order.attendees[0].publicId)).toBeVisible();

    const claim = new TransferClaimPage(page);
    await claim.open(transfer.claimUrl);
    await expect(claim.heading('This ticket can no longer be accepted')).toBeVisible();
    await expect(claim.acceptButton()).toHaveCount(0);
  });

  test('a ticket transferred back to the buyer shows on their order as a normal ticket', async ({ page, api, account, publicApi, mailpit }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    const guestEmail = uniqueEmail('guest');
    const order = await createCompletedOrder(publicApi, event, { attendeeEmail: guestEmail });
    const summary = new OrderSummaryPage(page);
    await summary.goto(event.eventId, order.orderShortId);
    await summary.startTransfer(order.buyerEmail);
    await new VerifyEmailDialog(page).confirmWithEmailedCode(mailpit, order.buyerEmail);

    const claim = new TransferClaimPage(page);
    await claim.open((await waitForClaimLink(mailpit, order.buyerEmail)).href);
    await claim.accept('Back', 'Again');
    await expect(page.getByText('Back Again')).toBeVisible();

    await summary.goto(event.eventId, order.orderShortId);
    await expect(page.getByText('Back Again')).toBeVisible();
    await expect(summary.transferredTickets()).toHaveCount(0);
    await expect(page.getByText(maskedEmail(order.buyerEmail))).toHaveCount(0);
  });
});
