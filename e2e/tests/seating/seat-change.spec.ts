import type {Page} from '@playwright/test';
import {test, expect} from '../../fixtures';
import {createSeatedEvent, createSeatedOrder} from '../../api/factory';

const HELD_SEAT = 'e2.0.9';
const STRANDING_SEAT = 'e2.0.1';
const FREE_SEAT = 'e2.0.7';
const FAR_SEAT = 'e2.0.13';

const chooserSeat = (page: Page, uid: string) => page.getByTestId('seat-chooser-map').locator(`[data-uid="${uid}"]`);

const openChooser = async (page: Page) => {
  await page.getByTestId('order-change-seat-button').click();
  await expect(page.getByTestId('seat-chooser-map')).toBeVisible();
};

const chooseSeat = async (page: Page, uid: string) => {
  await chooserSeat(page, uid).click();
  await page.getByTestId('seat-chooser-confirm-button').click();
};

test.describe('buyer seat changes', () => {
  test.beforeEach(async ({adminApi, account}) => {
    await adminApi.setAccountFeatureFlag(await adminApi.findAccountIdByEmail(account.email), 'seating', true);
  });

  test('a seat change that would strand a single seat is refused', async ({page, api, account, publicApi}) => {
    const event = await createSeatedEvent(api, account.organizerId);
    await api.updateSeatMapRules(event.eventId, {prevent_orphan_seats: true, max_seats_per_order: null, allow_seat_change: true});
    const order = await createSeatedOrder(publicApi, event, [HELD_SEAT]);

    await page.goto(`/checkout/${event.eventId}/${order.orderShortId}/summary`);
    await expect(page.getByText('Premium Seat · Stalls · A-10')).toBeVisible();

    await openChooser(page);
    await chooseSeat(page, STRANDING_SEAT);

    await expect(page.getByText('Please do not leave a single empty seat: Stalls · A-1')).toBeVisible();
    await expect(page.getByText('Premium Seat · Stalls · A-10')).toBeVisible();

    expect(await api.listAttendees(event.eventId)).toEqual([expect.objectContaining({seat_label: 'Stalls · A-10'})]);
    expect((await api.occupiedSeats(event.eventId, event.occurrenceId)).map(seat => seat.seat_uid)).toEqual([HELD_SEAT]);
  });

  test('the seat map reflects a completed change without a reload', {tag: '@smoke'}, async ({page, api, account, publicApi}) => {
    const event = await createSeatedEvent(api, account.organizerId);
    await api.updateSeatMapRules(event.eventId, {prevent_orphan_seats: true, max_seats_per_order: null, allow_seat_change: true});
    const order = await createSeatedOrder(publicApi, event, [HELD_SEAT]);

    await page.goto(`/checkout/${event.eventId}/${order.orderShortId}/summary`);
    await openChooser(page);
    await expect(chooserSeat(page, HELD_SEAT)).not.toHaveAttribute('data-state', 'free');
    await chooseSeat(page, FREE_SEAT);

    await expect(page.getByText('Your seat has been changed')).toBeVisible();
    await expect(page.getByText('Premium Seat · Stalls · A-8')).toBeVisible();

    await openChooser(page);
    await expect(chooserSeat(page, HELD_SEAT)).toHaveAttribute('data-state', 'free');
    await expect(chooserSeat(page, FREE_SEAT)).not.toHaveAttribute('data-state', 'free');
  });

  test('the seat left behind goes back on sale', async ({page, api, account, publicApi}) => {
    const event = await createSeatedEvent(api, account.organizerId);
    await api.updateSeatMapRules(event.eventId, {prevent_orphan_seats: true, max_seats_per_order: null, allow_seat_change: true});
    const order = await createSeatedOrder(publicApi, event, [HELD_SEAT]);

    await page.goto(`/checkout/${event.eventId}/${order.orderShortId}/summary`);
    await openChooser(page);
    await chooseSeat(page, FAR_SEAT);
    await expect(page.getByText('Your seat has been changed')).toBeVisible();

    expect((await api.occupiedSeats(event.eventId, event.occurrenceId)).map(seat => seat.seat_uid)).toEqual([FAR_SEAT]);

    const next = await createSeatedOrder(publicApi, event, [HELD_SEAT]);
    const seatLabels = (await api.listAttendees(event.eventId)).map(attendee => attendee.seat_label).sort();
    expect(seatLabels).toEqual(['Stalls · A-10', 'Stalls · A-14']);
    expect(next.orderShortId).not.toBe(order.orderShortId);
  });
});
