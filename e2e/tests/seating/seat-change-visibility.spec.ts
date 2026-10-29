import type {APIRequestContext, Page} from '@playwright/test';
import {test, expect} from '../../fixtures';
import {createSeatedEvent, createSeatedOrder, type SeededOrder} from '../../api/factory';
import type {ApiClient} from '../../api/api-client';

const SEAT = 'e2.0.9';
const SEAT_LINE = 'Premium Seat · Stalls · A-10';

const seatedOrderWithSeatChange = async (api: ApiClient, publicApi: APIRequestContext, organizerId: number) => {
  const event = await createSeatedEvent(api, organizerId);
  await api.updateSeatMapRules(event.eventId, {prevent_orphan_seats: true, max_seats_per_order: null, allow_seat_change: true});
  const order = await createSeatedOrder(publicApi, event, [SEAT]);

  return {event, order};
};

const openOrderSummary = async (page: Page, eventId: number, order: SeededOrder) => {
  await page.goto(`/checkout/${eventId}/${order.orderShortId}/summary`);
  await expect(page.getByText(SEAT_LINE)).toBeVisible();
};

const expectNoChangeSeatButton = async (page: Page, eventId: number, order: SeededOrder) => {
  await openOrderSummary(page, eventId, order);
  await page.waitForLoadState('networkidle');
  await expect(page.getByTestId('order-change-seat-button')).toHaveCount(0);
};

test.describe('change seat button visibility', () => {
  test.beforeEach(async ({adminApi, account}) => {
    await adminApi.setAccountFeatureFlag(await adminApi.findAccountIdByEmail(account.email), 'seating', true);
  });

  test('is hidden when attendee self-service is off', async ({page, api, account, publicApi}) => {
    const {event, order} = await seatedOrderWithSeatChange(api, publicApi, account.organizerId);
    await openOrderSummary(page, event.eventId, order);
    await expect(page.getByTestId('order-change-seat-button')).toBeVisible();

    await api.updateEventSettings(event.eventId, {allow_attendee_self_edit: false});

    await expectNoChangeSeatButton(page, event.eventId, order);
  });

  test('is hidden once the attendee has checked in', async ({page, api, account, publicApi}) => {
    const {event, order} = await seatedOrderWithSeatChange(api, publicApi, account.organizerId);
    await openOrderSummary(page, event.eventId, order);
    await expect(page.getByTestId('order-change-seat-button')).toBeVisible();

    const list = await api.createCheckInList(event.eventId, {name: 'Door'});
    const checkIn = await publicApi.post(`public/check-in-lists/${list.short_id}/check-ins`, {
      data: {attendees: [{public_id: order.attendees[0].publicId, action: 'check-in'}]},
    });
    expect(checkIn.ok()).toBe(true);

    await expectNoChangeSeatButton(page, event.eventId, order);
  });

  test('is hidden once the date has started', async ({page, api, account, publicApi}) => {
    const {event, order} = await seatedOrderWithSeatChange(api, publicApi, account.organizerId);
    await openOrderSummary(page, event.eventId, order);
    await expect(page.getByTestId('order-change-seat-button')).toBeVisible();

    await api.updateOccurrence(event.eventId, event.occurrenceId, {
      start_date: new Date(Date.now() - 30 * 60 * 1000).toISOString(),
      end_date: new Date(Date.now() + 3 * 60 * 60 * 1000).toISOString(),
    });

    await expectNoChangeSeatButton(page, event.eventId, order);
  });
});
