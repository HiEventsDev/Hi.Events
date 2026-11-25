import type { APIRequestContext } from '@playwright/test';
import { test, expect } from '../../fixtures';
import type { ApiClient } from '../../api/api-client';
import { createCompletedPaidOrder, type SeededEvent, type SeededOrder } from '../../api/factory';
import { AttendeePage } from '../../pages/attendee.page';
import { ResalePage } from '../../pages/resale.page';
import { createEventWithResale } from '../../utils/resale';
import { resaleRefundStatus, seedResaleListing, settleResaleRefund } from '../../utils/resale-listings';
import { invitedMember } from '../../utils/team';
import { uniqueShort } from '../../utils/unique';

const REFUND = 38.4;

const paidOrder = (
  api: ApiClient,
  publicApi: APIRequestContext,
  event: SeededEvent,
  lastName: string,
): Promise<SeededOrder> => createCompletedPaidOrder(api, publicApi, event, { buyerLastName: uniqueShort(lastName).replace(' ', '') });

async function publicIdOf(api: ApiClient, eventId: number, orderShortId: string): Promise<string> {
  const order = (await api.listOrders(eventId)).find((candidate) => candidate.short_id === orderShortId);
  if (!order) {
    throw new Error(`Order ${orderShortId} not found for event ${eventId}`);
  }
  return order.public_id;
}

test.describe('organizer resale oversight', () => {
  test('the resale page lists open, sold and closed listings and narrows to sold ones', { tag: '@smoke' }, async ({ authedPage, api, account, publicApi }) => {
    const event = await createEventWithResale(api, account.organizerId);
    const open = await paidOrder(api, publicApi, event, 'Open');
    const sold = await paidOrder(api, publicApi, event, 'Sold');
    const closed = await paidOrder(api, publicApi, event, 'Closed');
    const replacement = await paidOrder(api, publicApi, event, 'Replacement');
    seedResaleListing(open.attendees[0].shortId, { status: 'LISTED' });
    seedResaleListing(sold.attendees[0].shortId, {
      status: 'SOLD',
      soldOrderShortId: replacement.orderShortId,
      refundStatus: 'SUCCEEDED',
      refundAmount: REFUND,
    });
    seedResaleListing(closed.attendees[0].shortId, { status: 'CLOSED', closedReason: 'WITHDRAWN' });
    const replacementPublicId = await publicIdOf(api, event.eventId, replacement.orderShortId);

    const resale = new ResalePage(authedPage);
    await resale.goto(event.eventId);

    await expect(resale.row(open.buyerEmail)).toContainText(event.productTitle);
    await expect(resale.status(open.buyerEmail)).toHaveText('Open');
    await expect(resale.refund(open.buyerEmail)).toHaveText('$38.40 estimated');
    await expect(resale.status(sold.buyerEmail)).toHaveText('Sold');
    await expect(resale.refund(sold.buyerEmail)).toHaveText('$38.40Refunded');
    await expect(resale.status(closed.buyerEmail)).toHaveText('ClosedWithdrawn by the seller');
    await expect(resale.attentionSummary()).toHaveCount(0);

    await resale.row(sold.buyerEmail).getByRole('button', { name: replacementPublicId }).click();
    const orderDrawer = authedPage.getByRole('dialog');
    await expect(orderDrawer).toContainText(replacement.buyerEmail);
    await authedPage.keyboard.press('Escape');
    await expect(orderDrawer).toHaveCount(0);

    await resale.filterBy('Sold');
    await expect(resale.row(open.buyerEmail)).toHaveCount(0);
    await expect(resale.row(closed.buyerEmail)).toHaveCount(0);
    await expect(resale.row(sold.buyerEmail)).toBeVisible();
    await expect(authedPage).toHaveURL(/filterFields%5Bstatus%5D%5Beq%5D=SOLD/);
  });

  test('an organizer closes an open listing and the seller is told', async ({ authedPage, api, account, publicApi, mailpit }) => {
    const event = await createEventWithResale(api, account.organizerId);
    const seller = await paidOrder(api, publicApi, event, 'Seller');
    seedResaleListing(seller.attendees[0].shortId, { status: 'LISTED' });

    const resale = new ResalePage(authedPage);
    await resale.goto(event.eventId);
    await expect(resale.status(seller.buyerEmail)).toHaveText('Open');
    await resale.closeListing(seller.buyerEmail);

    await expect(authedPage.getByText('Listing closed. The seller has been emailed.')).toBeVisible();
    await expect(resale.status(seller.buyerEmail)).toHaveText('ClosedClosed by an organizer');
    await expect(resale.actionsTrigger()).toHaveCount(0);
    await mailpit.waitForMessage(seller.buyerEmail, { subjectContains: 'Your ticket resale listing was closed' });
  });

  test('a failed refund is flagged and the owner retries it', async ({ authedPage, api, account, publicApi }) => {
    const event = await createEventWithResale(api, account.organizerId);
    const seller = await paidOrder(api, publicApi, event, 'Seller');
    const replacement = await paidOrder(api, publicApi, event, 'Replacement');
    const listingId = seedResaleListing(seller.attendees[0].shortId, {
      status: 'SOLD',
      soldOrderShortId: replacement.orderShortId,
      refundStatus: 'FAILED',
      refundAmount: REFUND,
    });

    try {
      const resale = new ResalePage(authedPage);
      await resale.goto(event.eventId);

      await expect(resale.attentionSummary()).toContainText('1 refund needs attention');
      await expect(resale.status(seller.buyerEmail)).toHaveText('SoldRefund failed');
      await expect(resale.refund(seller.buyerEmail)).toHaveText('$38.40Failed');
      await expect(resale.row(seller.buyerEmail)).toHaveAttribute('data-attention', 'true');

      await resale.retryRefund(seller.buyerEmail);

      await expect(authedPage.getByText('Refund retried. It can take a few minutes to go through.')).toBeVisible();
      await expect(resale.refund(seller.buyerEmail)).toHaveText('$38.40Pending');
      await expect(resale.status(seller.buyerEmail)).toHaveText('Sold');
      await expect(resale.attentionSummary()).toHaveCount(0);
      await expect(resale.actionsTrigger()).toHaveCount(0);
      expect(resaleRefundStatus(listingId)).toBe('PENDING');
    } finally {
      settleResaleRefund(listingId);
    }
  });

  test('members without financial access see no refund amounts and viewers get no actions', async ({ freshAccount, mailpit, publicApi, browser }) => {
    const event = await createEventWithResale(freshAccount.api, freshAccount.organizerId);
    const open = await paidOrder(freshAccount.api, publicApi, event, 'Open');
    const sold = await paidOrder(freshAccount.api, publicApi, event, 'Sold');
    const replacement = await paidOrder(freshAccount.api, publicApi, event, 'Replacement');
    seedResaleListing(open.attendees[0].shortId, { status: 'LISTED' });
    seedResaleListing(sold.attendees[0].shortId, {
      status: 'SOLD',
      soldOrderShortId: replacement.orderShortId,
      refundStatus: 'FAILED',
      refundAmount: REFUND,
    });

    const withoutFinancials = await invitedMember(freshAccount, mailpit, publicApi, browser, {
      role: 'ORGANIZER',
      permissions: { VIEW_FINANCIALS: false },
    });
    const restricted = new ResalePage(withoutFinancials.page);
    await restricted.goto(event.eventId);
    await expect(restricted.status(sold.buyerEmail)).toHaveText('SoldRefund failed');
    await expect(restricted.status(open.buyerEmail)).toHaveText('Open');
    await expect(restricted.table().getByRole('columnheader', { name: 'Refund' })).toHaveCount(0);
    await expect(restricted.table().getByTestId('resale-listing-refund')).toHaveCount(0);
    await expect(restricted.table()).not.toContainText('$');
    await restricted.row(open.buyerEmail).getByTestId('resale-listing-actions-trigger').click();
    await expect(withoutFinancials.page.getByTestId('resale-close-listing-button')).toBeVisible();
    await withoutFinancials.page.context().close();

    const viewer = await invitedMember(freshAccount, mailpit, publicApi, browser, { role: 'VIEWER' });
    const readOnly = new ResalePage(viewer.page);
    await readOnly.goto(event.eventId);
    await expect(readOnly.status(open.buyerEmail)).toHaveText('Open');
    await expect(readOnly.status(sold.buyerEmail)).toHaveText('SoldRefund failed');
    await expect(readOnly.actionsTrigger()).toHaveCount(0);
    await viewer.page.context().close();
  });

  test('a resold attendee links to the replacement order and cannot be re-activated', async ({ authedPage, api, account, publicApi }) => {
    const event = await createEventWithResale(api, account.organizerId);
    const seller = await paidOrder(api, publicApi, event, 'Seller');
    const replacement = await paidOrder(api, publicApi, event, 'Replacement');
    seedResaleListing(seller.attendees[0].shortId, {
      status: 'SOLD',
      soldOrderShortId: replacement.orderShortId,
      refundStatus: 'SUCCEEDED',
      refundAmount: REFUND,
    });
    const replacementPublicId = await publicIdOf(api, event.eventId, replacement.orderShortId);

    const attendees = new AttendeePage(authedPage);
    await attendees.goto(event.eventId);
    const sellerRow = attendees.rowByText(seller.buyerLastName);

    await expect(sellerRow).toContainText('Resold');
    await expect(sellerRow).not.toContainText('Cancelled');
    await sellerRow.getByTestId('attendee-actions-trigger').click();
    await expect(authedPage.getByRole('menuitem', { name: 'Manage attendee' })).toBeVisible();
    await expect(authedPage.getByRole('menuitem', { name: 'Activate' })).toHaveCount(0);
    await authedPage.keyboard.press('Escape');

    await sellerRow.getByRole('button', { name: replacementPublicId }).click();
    const orderDrawer = authedPage.getByRole('dialog');
    await expect(orderDrawer).toContainText(replacement.buyerEmail);
    await authedPage.keyboard.press('Escape');
    await expect(orderDrawer).toHaveCount(0);

    await attendees.openRowAction(seller.buyerLastName, 'Manage attendee');
    const attendeeDrawer = authedPage.getByRole('dialog');
    await expect(attendeeDrawer).toContainText('Resold');
    await expect(attendeeDrawer.getByTestId('attendee-replacement-order-link')).toHaveText(replacementPublicId);
    await expect(attendeeDrawer.getByRole('button', { name: 'Activate' })).toHaveCount(0);
  });
});
