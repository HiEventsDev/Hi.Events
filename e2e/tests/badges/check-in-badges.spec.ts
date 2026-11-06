import { devices, type APIRequestContext } from '@playwright/test';
import { test, expect } from '../../fixtures';
import { CheckInPage } from '../../pages/check-in.page';
import { lastPrintContext, lastPrintedPageSides, printCalls, stubBrowserPrinting } from '../../pages/badges.page';
import { createCompletedOrder, createLiveEventWithFreeTicket } from '../../api/factory';
import type { ApiClient } from '../../api/api-client';
import { uniqueName, uniqueShort } from '../../utils/unique';

async function seedBadgeList(
  api: ApiClient,
  publicApi: APIRequestContext,
  organizerId: number,
  options: { kiosk?: boolean } = {},
) {
  const event = await createLiveEventWithFreeTicket(api, organizerId);
  await api.enableBadges(event.eventId);
  const order = await createCompletedOrder(publicApi, event, {
    quantity: 2,
    buyerFirstName: 'Casey',
    buyerLastName: uniqueShort('Lee').replace(' ', ''),
  });
  const list = await api.createCheckInList(event.eventId, {
    name: uniqueName('Registration desk'),
    badge_printing_enabled: true,
    kiosk_mode: options.kiosk ?? false,
  });
  return { event, order, list };
}

test.describe('badge printing at check-in', () => {
  test('door staff print a badge from the attendee sheet', { tag: '@smoke' }, async ({ page, api, publicApi, account }) => {
    await stubBrowserPrinting(page.context());
    const { event, order, list } = await seedBadgeList(api, publicApi, account.organizerId);
    const attendee = order.attendees[0];

    const checkIn = new CheckInPage(page);
    await checkIn.goto(list.short_id);
    await checkIn.openSearchTab();
    await checkIn.search(order.buyerLastName);
    await checkIn.openAttendee(attendee.publicId);

    await expect(checkIn.printBadgeButton()).toHaveText('Print badge');
    await checkIn.printBadgeButton().click();

    await expect(checkIn.badgePrintNote()).toHaveText('Badge printed once');
    await expect(checkIn.printBadgeButton()).toHaveText('Reprint badge');
    expect(await printCalls(page)).toBe(1);
    expect((await lastPrintContext(page))?.path).toBe('/');

    const attendees = await api.listAttendees(event.eventId);
    expect(attendees.find((record) => record.public_id === attendee.publicId)?.badge_print_count).toBe(1);
  });

  test('badges print automatically when auto-print is on', async ({ page, api, publicApi, account }) => {
    await stubBrowserPrinting(page.context());
    const { event, order, list } = await seedBadgeList(api, publicApi, account.organizerId);
    const attendee = order.attendees[0];

    const checkIn = new CheckInPage(page);
    await checkIn.goto(list.short_id);
    await checkIn.enableAutoPrint();
    await checkIn.openSearchTab();
    await checkIn.search(order.buyerLastName);
    await checkIn.checkInButton(attendee.publicId).click();
    await expect(checkIn.checkOutButton(attendee.publicId)).toBeVisible();

    await expect.poll(() => printCalls(page)).toBe(1);
    await expect.poll(async () => (await api.getBadgeStats(event.eventId)).printed).toBe(1);
  });

  test('door printing follows a fold-over design', async ({ page, api, publicApi, account }) => {
    await stubBrowserPrinting(page.context());
    const { event, order, list } = await seedBadgeList(api, publicApi, account.organizerId);
    await api.updateBadgeDesign(event.eventId, { back_mode: 'SAME', fold_over: true });

    const checkIn = new CheckInPage(page);
    await checkIn.goto(list.short_id);
    await checkIn.openSearchTab();
    await checkIn.search(order.buyerLastName);
    await checkIn.openAttendee(order.attendees[0].publicId);
    await checkIn.printBadgeButton().click();

    await expect.poll(() => printCalls(page)).toBe(1);
    expect(await lastPrintedPageSides(page)).toEqual(['fold']);
  });

  test('the back prints at the door only on a device set up for two-sided printing', async ({ page, api, publicApi, account }) => {
    await stubBrowserPrinting(page.context());
    const { event, order, list } = await seedBadgeList(api, publicApi, account.organizerId);
    await api.updateBadgeDesign(event.eventId, { back_mode: 'SAME', fold_over: false });

    const checkIn = new CheckInPage(page);
    await checkIn.goto(list.short_id);
    await checkIn.openSearchTab();
    await checkIn.search(order.buyerLastName);
    await checkIn.openAttendee(order.attendees[0].publicId);
    await checkIn.printBadgeButton().click();
    await expect.poll(() => printCalls(page)).toBe(1);
    expect(await lastPrintedPageSides(page)).toEqual(['front']);
    await page.keyboard.press('Escape');

    await checkIn.chooseBackPrinting('Two-sided printer');
    await checkIn.openAttendee(order.attendees[0].publicId);
    await expect(checkIn.printBadgeButton()).toHaveText('Reprint badge');
    await checkIn.printBadgeButton().click();
    await expect.poll(() => printCalls(page)).toBe(2);
    expect(await lastPrintedPageSides(page)).toEqual(['front', 'back']);
  });

  test('the setup guide gives this computer the Chrome command for silent printing', async ({ page, api, publicApi, account }) => {
    const { list } = await seedBadgeList(api, publicApi, account.organizerId);

    const checkIn = new CheckInPage(page);
    await checkIn.goto(list.short_id);
    await checkIn.openPrinterSettings();
    const pageUrl = `${new URL(page.url()).origin}/check-in/${list.short_id}`;

    const command = page.getByTestId('device-setup-command');
    await page.getByTestId('device-setup-platform').getByText('Mac', { exact: true }).click();
    await expect(command).toHaveText(`open -a "Google Chrome" --args --kiosk-printing '${pageUrl}'`);

    await page.getByTestId('device-setup-platform').getByText('Windows', { exact: true }).click();
    await expect(command).toHaveText(`chrome --kiosk-printing "${pageUrl}"`);
  });

  test('a black-only label printer prints every colour as solid black', async ({ page, api, publicApi, account }) => {
    await stubBrowserPrinting(page.context());
    const { order, list } = await seedBadgeList(api, publicApi, account.organizerId);

    const checkIn = new CheckInPage(page);
    await checkIn.goto(list.short_id);
    await checkIn.openSearchTab();
    await checkIn.search(order.buyerLastName);
    await checkIn.openAttendee(order.attendees[0].publicId);
    await checkIn.printBadgeButton().click();
    await expect.poll(() => printCalls(page)).toBe(1);
    expect((await lastPrintContext(page))?.ink).toBeNull();
    await page.keyboard.press('Escape');

    await checkIn.enableBlackInkOnly();
    await checkIn.openAttendee(order.attendees[0].publicId);
    await checkIn.printBadgeButton().click();
    await expect.poll(() => printCalls(page)).toBe(2);
    expect((await lastPrintContext(page))?.ink).toBe('black');
  });

  test('lists without badge printing show no print controls', async ({ page, api, publicApi, account }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    const order = await createCompletedOrder(publicApi, event, { buyerLastName: uniqueShort('Plain').replace(' ', '') });
    const list = await api.createCheckInList(event.eventId, { name: uniqueName('Side door') });

    const checkIn = new CheckInPage(page);
    await checkIn.goto(list.short_id);
    await checkIn.openSearchTab();
    await checkIn.search(order.buyerLastName);
    await checkIn.openAttendee(order.attendees[0].publicId);

    await expect(page.getByRole('button', { name: /^Check in$/ }).last()).toBeVisible();
    await expect(checkIn.printBadgeButton()).toHaveCount(0);
    await expect(page.getByTestId('check-in-badge-printer-button')).toHaveCount(0);
  });

  test('attendees check themselves in at a kiosk and their badge prints', async ({ page, api, publicApi, account }) => {
    await stubBrowserPrinting(page.context());
    const { order, list } = await seedBadgeList(api, publicApi, account.organizerId, { kiosk: true });
    const attendee = order.attendees[0];

    const checkIn = new CheckInPage(page);
    await checkIn.goto(list.short_id);
    await expect(checkIn.kiosk()).toContainText('Scan your ticket to check in');
    await expect(page.getByRole('navigation', { name: 'Check-in navigation' })).toHaveCount(0);
    await expect(page.getByTestId('kiosk-setup-button')).toHaveCount(0);

    await checkIn.holdKioskListName();
    await expect(checkIn.kioskStaffControls()).toBeVisible();
    await page.getByTestId('kiosk-setup-button').click();
    await expect(page.getByTestId('device-setup-command')).toContainText('--kiosk-printing --kiosk');
    await expect(page.getByTestId('device-setup-command')).toContainText(`/check-in/${list.short_id}`);
    await page.getByRole('button', { name: 'Done' }).click();

    await checkIn.scanWithUsbScanner(attendee.publicId);

    await expect(page.getByTestId('kiosk-result-success')).toContainText('Welcome, Casey!');
    await expect.poll(() => printCalls(page)).toBe(1);
    await expect(page.getByTestId('kiosk-print-status')).toHaveText('Your badge is ready. Collect it from the printer.');
    expect((await lastPrintContext(page))?.scale).toBe(1);

    await page.getByTestId('kiosk-next-button').click();
    await expect(checkIn.kiosk()).toContainText('Scan your ticket to check in');
    await page.waitForTimeout(3_100);
    await checkIn.scanWithUsbScanner(attendee.publicId);

    await expect(page.getByTestId('kiosk-result-duplicate')).toContainText("You're already checked in, Casey");
    expect(await printCalls(page)).toBe(1);
  });
});

test.describe('badge printing at check-in on an iPad', () => {
  const iPad = devices['iPad Pro 11'];
  test.use({ userAgent: iPad.userAgent, viewport: iPad.viewport, hasTouch: true });

  test('staff print from a tap because the iPad blocks automatic printing', async ({ page, api, publicApi, account }) => {
    await stubBrowserPrinting(page.context());
    const { event, order, list } = await seedBadgeList(api, publicApi, account.organizerId);
    const attendee = order.attendees[0];

    const checkIn = new CheckInPage(page);
    await checkIn.goto(list.short_id);
    await checkIn.openPrinterSettings();
    await expect(page.getByText('Each badge then takes two taps', { exact: false })).toBeVisible();
    await expect(page.getByTestId('check-in-auto-print-switch')).toHaveCount(0);
    await page.getByRole('button', { name: 'Done' }).click();

    await checkIn.openSearchTab();
    await checkIn.search(order.buyerLastName);
    await checkIn.openAttendee(attendee.publicId);
    await checkIn.printBadgeButton().click();

    await expect(checkIn.badgePrintNote()).toHaveText('Badge printed once');
    expect(await printCalls(page)).toBe(1);
    expect(await lastPrintContext(page)).toMatchObject({ path: '/', trigger: 'click' });

    const attendees = await api.listAttendees(event.eventId);
    expect(attendees.find((record) => record.public_id === attendee.publicId)?.badge_print_count).toBe(1);
  });

  test('kiosk attendees tap to print their badge', async ({ page, api, publicApi, account }) => {
    await stubBrowserPrinting(page.context());
    const { event, order, list } = await seedBadgeList(api, publicApi, account.organizerId, { kiosk: true });

    const checkIn = new CheckInPage(page);
    await checkIn.goto(list.short_id);
    await expect(page.getByTestId('kiosk-scan-mode-button')).toHaveCount(0);
    await checkIn.holdKioskListName();
    await expect(page.getByTestId('kiosk-scan-mode-button')).toHaveText('Use a USB scanner');
    await page.getByTestId('kiosk-scan-mode-button').click();
    await checkIn.scanWithUsbScanner(order.attendees[0].publicId);

    await expect(page.getByTestId('kiosk-result-success')).toContainText('Welcome, Casey!');
    await expect(page.getByTestId('kiosk-print-status')).toHaveText('Tap Print my badge, then Print.');
    expect(await printCalls(page)).toBe(0);

    await page.getByTestId('kiosk-print-badge-button').click();

    await expect(page.getByTestId('kiosk-print-status')).toHaveText('Your badge is ready. Collect it from the printer.');
    expect(await printCalls(page)).toBe(1);
    const printed = await lastPrintContext(page);
    expect(printed).toMatchObject({ path: '/', trigger: 'click' });
    expect(printed?.scale).toBeGreaterThan(0.4);
    expect(printed?.scale).toBeLessThan(1);

    await page.getByTestId('kiosk-print-again-button').click();
    expect(await printCalls(page)).toBe(2);
    expect(await lastPrintContext(page)).toMatchObject({ path: '/', trigger: 'click' });
    await expect.poll(async () => (await api.getBadgeStats(event.eventId)).printed).toBe(1);
  });
});
