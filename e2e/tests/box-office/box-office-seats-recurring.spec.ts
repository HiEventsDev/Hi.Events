import type { Page } from '@playwright/test';
import { test, expect } from '../../fixtures';
import { BoxOfficePage } from '../../pages/box-office.page';
import { createSeatedEvent, defaultBoxOffice } from '../../api/factory';
import type { ApiClient } from '../../api/api-client';

const A_10 = 'e2.0.9';

async function completeFreeSale(page: Page, door: BoxOfficePage): Promise<void> {
  await door.charge();
  await page.getByRole('button', { name: 'Complete sale' }).click();
  await expect(door.saleCompleteHeading()).toBeVisible();
}

async function seatLabelsByOccurrence(api: ApiClient, eventId: number): Promise<Map<number, string[]>> {
  const occurrenceByOrder = new Map(
    (await api.listOrders(eventId)).map((order) => [order.id, order.order_items?.[0]?.event_occurrence_id ?? null]),
  );
  const result = new Map<number, string[]>();
  for (const attendee of await api.listAttendees(eventId)) {
    const occurrenceId = occurrenceByOrder.get(attendee.order_id)!;
    result.set(occurrenceId, [...(result.get(occurrenceId) ?? []), attendee.seat_label!]);
  }
  return result;
}

async function openDoorOnFirstDate(page: Page, api: ApiClient, eventId: number): Promise<BoxOfficePage> {
  const boxOffice = await defaultBoxOffice(api, eventId);
  const door = new BoxOfficePage(page);
  await door.goto(boxOffice.short_id);
  await door.chooseStartDate(0);
  await door.startSession('Sam', boxOffice.pin);
  await expect(door.seatMap()).toBeVisible();
  return door;
}

test.describe('box office reserved seating on a recurring event', () => {
  test.beforeEach(async ({ adminApi, account }) => {
    await adminApi.setAccountFeatureFlag(await adminApi.findAccountIdByEmail(account.email), 'seating', true);
  });

  test('a seat sold at the door on one date is free after switching to another date', async ({ page, api, account }) => {
    const event = await createSeatedEvent(api, account.organizerId, { recurringCount: 2 });
    const [firstDate, secondDate] = event.occurrences;
    const door = await openDoorOnFirstDate(page, api, event.eventId);

    await expect(door.seat(A_10)).toHaveAttribute('data-state', 'free');
    await door.seat(A_10).click();
    await expect(door.cartSeats(event.priceId)).toContainText('Stalls · A-10');
    await completeFreeSale(page, door);
    await door.newSale();

    await door.switchDate(1);
    await expect(door.seat(A_10)).toHaveAttribute('data-state', 'free');
    await door.seat(A_10).click();
    await expect(door.cartSeats(event.priceId)).toContainText('Stalls · A-10');
    await completeFreeSale(page, door);

    const seatsByDate = await seatLabelsByOccurrence(api, event.eventId);
    expect(seatsByDate.get(firstDate.id)).toEqual(['Stalls · A-10']);
    expect(seatsByDate.get(secondDate.id)).toEqual(['Stalls · A-10']);
  });

  test('a seat sold at the door shows as taken when the next sale starts', async ({ page, api, account }) => {
    const event = await createSeatedEvent(api, account.organizerId, { recurringCount: 2 });
    const door = await openDoorOnFirstDate(page, api, event.eventId);

    await door.seat(A_10).click();
    await completeFreeSale(page, door);
    await door.newSale();

    await expect(door.seat(A_10)).toHaveAttribute('data-state', 'unavailable', { timeout: 3_000 });
  });
});
