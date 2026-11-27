import type { APIRequestContext } from '@playwright/test';
import { test, expect } from '../../fixtures';
import { BadgePrintPage, lastPrintedPageSides, PrintBadgesModal, printCalls, stubBrowserPrinting } from '../../pages/badges.page';
import { AttendeePage } from '../../pages/attendee.page';
import { createCompletedOrder, createLiveEventWithFreeTicket, type SeededEvent } from '../../api/factory';
import type { ApiClient } from '../../api/api-client';
import { uniqueShort } from '../../utils/unique';

async function seedNamedAttendees(api: ApiClient, publicApi: APIRequestContext, event: SeededEvent, firstNames: string[]) {
  const order = await createCompletedOrder(publicApi, event, {
    quantity: firstNames.length,
    buyerFirstName: firstNames[0],
    buyerLastName: uniqueShort('Duplex').replace(' ', ''),
  });
  for (const [index, attendee] of order.attendees.entries()) {
    if (index > 0) {
      await api.renameAttendee(event.eventId, await api.findAttendeeIdByPublicId(event.eventId, attendee.publicId), firstNames[index]);
    }
  }
}

test.describe('badge printing', () => {
  test('an organizer prints everyone not printed yet and marks the batch as printed', { tag: '@smoke' }, async ({ authedPage, api, publicApi, account }) => {
    await stubBrowserPrinting(authedPage.context());
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    await api.enableBadges(event.eventId);
    const company = await api.createQuestion(event.eventId, {
      title: 'Company',
      type: 'SINGLE_LINE_TEXT',
      belongs_to: 'PRODUCT',
      product_ids: [event.productId],
      required: false,
      is_hidden: false,
    });
    const lastName = uniqueShort('Lovelace').replace(' ', '');
    await createCompletedOrder(publicApi, event, {
      quantity: 2,
      buyerFirstName: 'Ada',
      buyerLastName: lastName,
      attendeeQuestions: [{ question_id: company.id, response: { answer: 'Analytical Engines' } }],
    });

    await authedPage.goto(`/manage/event/${event.eventId}/attendees`);
    await authedPage.getByTestId('attendees-print-badges-button').click();

    const modal = new PrintBadgesModal(authedPage);
    await expect(modal.summary).toContainText('2 badges');
    const popupPromise = authedPage.waitForEvent('popup');
    await modal.openPreviewButton.click();
    const popup = await popupPromise;
    await popup.waitForLoadState('networkidle');

    const printPage = new BadgePrintPage(popup);
    await expect(printPage.badges).toHaveCount(2);
    await expect(printPage.badges.first()).toContainText('Ada');
    await expect(printPage.badges.first()).toContainText('Analytical Engines');
    await expect(printPage.summary).toContainText('2 badges');

    await expect(printPage.printButton).toBeEnabled();
    await printPage.printButton.click();
    await expect(printPage.confirmBar).toBeVisible();
    expect(await printCalls(popup)).toBe(1);
    await printPage.markPrintedButton.click();
    await expect(popup.getByText('Marked as printed. You can close this tab.')).toBeVisible();

    expect(await api.getBadgeStats(event.eventId)).toEqual({ total: 2, printed: 2, not_printed: 0, needs_reprint: 0 });
  });

  test('renaming an attendee after printing puts their badge back in the queue', async ({ authedPage, api, publicApi, account }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    await api.enableBadges(event.eventId);
    const order = await createCompletedOrder(publicApi, event, { quantity: 2, buyerFirstName: 'Grace', buyerLastName: 'Hopper' });
    const attendeeIds = await Promise.all(order.attendees.map((attendee) => api.findAttendeeIdByPublicId(event.eventId, attendee.publicId)));
    await api.markBadgesPrinted(event.eventId, attendeeIds);
    await api.renameAttendee(event.eventId, attendeeIds[0], 'Amazing');

    await authedPage.goto(`/manage/event/${event.eventId}/attendees`);
    await authedPage.waitForLoadState('networkidle');

    await expect(authedPage.getByRole('row').filter({ hasText: 'Amazing' }).getByText('Needs reprint')).toBeVisible();
    await expect(authedPage.getByRole('row').filter({ hasText: order.attendees[1].publicId }).getByText('Printed', { exact: true })).toBeVisible();

    await authedPage.getByTestId('attendees-print-badges-button').click();
    const modal = new PrintBadgesModal(authedPage);
    await expect(modal.dialog.getByText('Not printed yet (1)')).toBeVisible();
    await expect(modal.summary).toContainText('1 badge');
  });

  test('a single attendee badge is printed from the attendee menu', async ({ authedPage, api, publicApi, account }) => {
    await stubBrowserPrinting(authedPage.context());
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    await api.enableBadges(event.eventId);
    const order = await createCompletedOrder(publicApi, event, { quantity: 2, buyerFirstName: 'Katherine', buyerLastName: 'Johnson' });

    const attendees = new AttendeePage(authedPage);
    await attendees.goto(event.eventId);
    const popupPromise = authedPage.waitForEvent('popup');
    await attendees.openRowAction(order.attendees[0].publicId, 'Print badge');
    const popup = await popupPromise;
    await popup.waitForLoadState('networkidle');

    const printPage = new BadgePrintPage(popup);
    await expect(printPage.badges).toHaveCount(1);
    await expect(printPage.badges.first()).toContainText('Katherine');
  });

  test('a test print fills a sheet with sample badges', async ({ authedPage, api, account }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    await api.enableBadges(event.eventId);
    await api.updateBadgeDesign(event.eventId, { size: 'A6_PORTRAIT', sheet: 'A4' });

    const printPage = new BadgePrintPage(authedPage);
    await printPage.goto(event.eventId, 'sample=1');

    await expect(authedPage.getByRole('heading', { name: 'Test print' })).toBeVisible();
    await expect(printPage.badges).toHaveCount(4);
  });

  test('two-sided sheets print each back directly behind its front', async ({ authedPage, api, publicApi, account }) => {
    await stubBrowserPrinting(authedPage.context());
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    await api.enableBadges(event.eventId);
    await api.updateBadgeDesign(event.eventId, { size: 'A6_PORTRAIT', sheet: 'A4', back_mode: 'SAME', fold_over: false });
    await seedNamedAttendees(api, publicApi, event, ['Alpha', 'Bravo', 'Charlie']);

    const printPage = new BadgePrintPage(authedPage);
    await printPage.goto(event.eventId, 'sort_by=first_name');

    await expect(printPage.summary).toContainText('3 badges · 2 pages');
    await expect(printPage.backNotice).toContainText('flip on the long edge');
    await expect(printPage.pages.nth(0)).toHaveAttribute('data-side', 'front');
    await expect(printPage.pages.nth(1)).toHaveAttribute('data-side', 'back');

    const fronts = printPage.pages.nth(0).locator(':scope > *');
    const backs = printPage.pages.nth(1).locator(':scope > *');
    await expect(fronts.nth(0)).toContainText('Alpha');
    await expect(fronts.nth(1)).toContainText('Bravo');
    await expect(fronts.nth(2)).toContainText('Charlie');
    await expect(backs.nth(0)).toContainText('Bravo');
    await expect(backs.nth(1)).toContainText('Alpha');
    await expect(backs.nth(2)).toBeEmpty();
    await expect(backs.nth(3)).toContainText('Charlie');

    await expect(printPage.printButton).toBeEnabled();
    await printPage.printButton.click();
    expect(await lastPrintedPageSides(authedPage)).toEqual(['front', 'back']);
  });

  test('fold-over badges print front and back together on one side of the paper', async ({ authedPage, api, publicApi, account }) => {
    await stubBrowserPrinting(authedPage.context());
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    await api.enableBadges(event.eventId);
    await api.updateBadgeDesign(event.eventId, {
      size: 'IN_4X3_LANDSCAPE',
      sheet: 'LETTER',
      back_mode: 'INFO',
      back_text: 'Wi-Fi: Hall B',
      fold_over: true,
    });
    await seedNamedAttendees(api, publicApi, event, ['Alpha', 'Bravo', 'Charlie']);

    const printPage = new BadgePrintPage(authedPage);
    await printPage.goto(event.eventId, 'sort_by=first_name');

    await expect(printPage.summary).toContainText('3 badges · 2 pages');
    await expect(printPage.backNotice).toContainText('Fold-over');
    await expect(printPage.pages).toHaveCount(2);
    await expect(printPage.pages.nth(0)).toHaveAttribute('data-side', 'fold');

    const units = authedPage.getByTestId('badge-fold-over');
    await expect(units).toHaveCount(3);
    await expect(units.first()).toHaveAttribute('data-fold', 'STACKED');
    await expect(units.first().locator('[data-panel="front"]')).toContainText('Alpha');
    await expect(units.first().locator('[data-panel="back"]')).toContainText('Wi-Fi: Hall B');
    const box = await units.first().boundingBox();
    expect(box && box.height / box.width).toBeCloseTo(1.5, 2);

    await expect(printPage.printButton).toBeEnabled();
    await printPage.printButton.click();
    expect(await lastPrintedPageSides(authedPage)).toEqual(['fold', 'fold']);
  });
});
