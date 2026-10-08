import type { APIRequestContext, Locator, Page } from '@playwright/test';
import { test, expect } from '../../fixtures';
import { CheckoutPage } from '../../pages/checkout.page';
import { SeatPickerPage } from '../../pages/seat-picker.page';
import { createSeatedEvent, type SeatedEvent } from '../../api/factory';
import { uniqueEmail } from '../../utils/unique';

const NO_RULES = { prevent_orphan_seats: false, max_seats_per_order: null, allow_seat_change: false };

const openPicker = (page: Page): Promise<SeatPickerPage> => new SeatPickerPage(page, { touch: true }).openFullScreen();

const isTopmost = (locator: Locator): Promise<boolean> => locator.evaluate((element) => {
  const box = element.getBoundingClientRect();
  const hit = document.elementFromPoint(box.left + box.width / 2, box.top + box.height / 2);
  return hit !== null && element.contains(hit);
});

async function expectRefusalOnTop(page: Page, message: string | RegExp, retryTrigger: () => Promise<void>): Promise<void> {
  await expect(async () => {
    await retryTrigger();
    const toast = page.getByText(message).last();
    await expect(toast).toBeVisible({ timeout: 2_000 });
    expect(await isTopmost(toast)).toBe(true);
  }).toPass({ timeout: 30_000 });
}

async function takeSeatsOnline(publicApi: APIRequestContext, event: SeatedEvent, seatUids: string[]): Promise<void> {
  const response = await publicApi.post(`public/events/${event.eventId}/order`, {
    headers: { Accept: 'application/json' },
    data: {
      products: [{
        product_id: event.productId,
        event_occurrence_id: event.occurrenceId,
        quantities: [{ price_id: event.priceId, quantity: seatUids.length, seat_uids: seatUids }],
      }],
    },
  });
  expect(response.status()).toBe(201);
}

async function completeFreeOrder(checkout: CheckoutPage, attendeeCount: number): Promise<void> {
  await checkout.fillOrderAndAttendees({ firstName: 'Guest', lastName: 'Seated', email: uniqueEmail('seat-mobile') }, attendeeCount);
  await checkout.completeFreeOrder();
}

test.describe('reserved seating picker on a phone', () => {
  test.use({ viewport: { width: 390, height: 844 }, hasTouch: true, isMobile: true });

  test.beforeEach(async ({ adminApi, account }) => {
    await adminApi.setAccountFeatureFlag(await adminApi.findAccountIdByEmail(account.email), 'seating', true);
  });

  test('every refusal reaches the buyer over the full-screen picker and the sale still completes', async ({ page, api, account, publicApi }) => {
    const event = await createSeatedEvent(api, account.organizerId);
    await api.updateSeatMapRules(event.eventId, { ...NO_RULES, max_seats_per_order: 2 });
    const checkout = new CheckoutPage(page);
    await checkout.gotoPublicEvent(event.eventId, event.slug);

    const picker = await openPicker(page);
    await picker.selectSeats(['e2.0.9', 'e2.0.10']);
    await expect(picker.seatTotal(2)).toBeVisible();

    await expectRefusalOnTop(page, 'You can choose up to 2 seats per order', () => picker.seat('e2.0.11').tap());
    await expect(picker.seat('e2.0.11')).not.toHaveAttribute('data-state', 'selected');
    await expect(picker.basketLine('Stalls · A-12')).toHaveCount(0);
    await expect(picker.seatTotal(2)).toBeVisible();

    await takeSeatsOnline(publicApi, event, ['e2.0.10']);
    await picker.continueButton().tap();

    const conflict = picker.conflictNotice();
    await expect(conflict).toContainText('Stalls · A-11');
    expect(await isTopmost(conflict)).toBe(true);
    await expect(picker.root()).toBeVisible();
    await expect(page).not.toHaveURL(/\/checkout\//);
    expect(await picker.chosenLabels()).toEqual(['Stalls · A-10']);

    await picker.selectSeat('e2.0.11');
    await picker.continueToDetails();
    await expect(page.getByText('Premium Seat · Stalls · A-10')).toBeVisible();
    await expect(page.getByText('Premium Seat · Stalls · A-12')).toBeVisible();

    await completeFreeOrder(checkout, 2);

    const labels = (await api.listAttendees(event.eventId)).map((attendee) => attendee.seat_label);
    expect(labels.sort()).toEqual(['Stalls · A-10', 'Stalls · A-12']);
  });

  test('the orphan warning shows inside the picker and blocks continue until the gap is filled', async ({ page, api, account }) => {
    const event = await createSeatedEvent(api, account.organizerId);
    await api.updateSeatMapRules(event.eventId, { ...NO_RULES, prevent_orphan_seats: true });
    const checkout = new CheckoutPage(page);
    await checkout.gotoPublicEvent(event.eventId, event.slug);

    const picker = await openPicker(page);
    await picker.selectSeat('e2.0.1');

    const warning = picker.root().getByText("Please don't leave a single empty seat: Stalls · A-1");
    await expect(warning).toBeVisible();
    expect(await isTopmost(warning)).toBe(true);
    await expect(picker.continueButton()).toBeDisabled();

    await picker.selectSeat('e2.0.0');
    await expect(picker.root().getByText(/single empty seat/)).toHaveCount(0);
    await expect(picker.continueButton()).toBeEnabled();

    await picker.continueToDetails();
    await expect(page.getByText('Premium Seat · Stalls · A-1')).toBeVisible();
    await expect(page.getByText('Premium Seat · Stalls · A-2')).toBeVisible();
  });

  test('best available fills the requested count and the buyer completes the order', async ({ page, api, account }) => {
    const event = await createSeatedEvent(api, account.organizerId);
    const checkout = new CheckoutPage(page);
    await checkout.gotoPublicEvent(event.eventId, event.slug);

    const picker = await openPicker(page);
    await picker.bestAvailable(3);

    await expect(picker.seatTotal(3)).toBeVisible();
    const labels = await picker.chosenLabels();
    expect(labels).toHaveLength(3);

    await picker.continueToDetails();
    for (const label of labels) {
      await expect(page.getByText(`Premium Seat · ${label}`)).toBeVisible();
    }

    await completeFreeOrder(checkout, 3);
    await expect(page.getByText(labels[0]).filter({ visible: true }).first()).toBeVisible();

    const booked = (await api.listAttendees(event.eventId)).map((attendee) => attendee.seat_label);
    expect(booked.sort()).toEqual([...labels].sort());
  });
});
