import type { Locator, Page } from '@playwright/test';
import { test, expect } from '../../fixtures';
import { CheckoutPage } from '../../pages/checkout.page';
import { SeatPickerPage } from '../../pages/seat-picker.page';
import { OrderPage } from '../../pages/order.page';
import { AttendeePage } from '../../pages/attendee.page';
import { BoxOfficePage } from '../../pages/box-office.page';
import {
  createAwaitingOfflineOrder,
  createSeatedEvent,
  createSeatedOrder,
  defaultBoxOffice,
  type SeatedEvent,
} from '../../api/factory';
import type { ApiClient } from '../../api/api-client';
import { uniqueEmail } from '../../utils/unique';

const A10 = 'e2.0.9';
const A11 = 'e2.0.10';

const publicSeat = (page: Page, uid: string): Locator => new SeatPickerPage(page).seat(uid);

async function openPublicMap(page: Page, event: SeatedEvent): Promise<void> {
  await new CheckoutPage(page).gotoPublicEvent(event.eventId, event.slug);
  await expect(new SeatPickerPage(page).root()).toBeVisible();
}

async function occupiedUids(api: ApiClient, event: SeatedEvent): Promise<string[]> {
  return (await api.occupiedSeats(event.eventId, event.occurrenceId)).map((seat) => seat.seat_uid);
}

async function pickSeatsAndContinue(page: Page, seatUids: string[]): Promise<void> {
  const picker = new SeatPickerPage(page);
  await picker.selectSeats(seatUids);
  await picker.continueToDetails();
}

async function seatedAttendee(api: ApiClient, event: SeatedEvent, email: string) {
  const attendee = (await api.listAttendees(event.eventId)).find((candidate) => candidate.email === email);
  expect(attendee).toBeDefined();
  return attendee!;
}

test.describe('releasing reserved seats', () => {
  test.beforeEach(async ({ adminApi, account }) => {
    await adminApi.setAccountFeatureFlag(await adminApi.findAccountIdByEmail(account.email), 'seating', true);
  });

  test('cancelling a seated order frees the seat for the next buyer', { tag: '@smoke' }, async ({ authedPage, page, api, publicApi, account }) => {
    const event = await createSeatedEvent(api, account.organizerId);
    const order = await createSeatedOrder(publicApi, event, [A10], { buyerEmail: uniqueEmail('first') });

    await openPublicMap(page, event);
    await expect(publicSeat(page, A10)).toHaveAttribute('data-state', 'unavailable');

    const orders = new OrderPage(authedPage);
    await orders.goto(event.eventId);
    await orders.chooseRowAction(order.buyerEmail, 'Cancel order');
    await orders.confirmCancelOrder();
    await expect(orders.rowByEmail(order.buyerEmail).getByText('Cancelled')).toBeVisible();

    await openPublicMap(page, event);
    await expect(publicSeat(page, A10)).toHaveAttribute('data-state', 'free');

    const secondEmail = uniqueEmail('second');
    await createSeatedOrder(publicApi, event, [A10], { buyerEmail: secondEmail });
    expect((await seatedAttendee(api, event, secondEmail)).seat_label).toBe('Stalls · A-10');
  });

  test('cancelling a seated attendee names and frees the seat, and reactivating retakes it', async ({ authedPage, page, api, publicApi, account }) => {
    const event = await createSeatedEvent(api, account.organizerId);
    const order = await createSeatedOrder(publicApi, event, [A10], { buyerEmail: uniqueEmail('seatholder') });

    const attendees = new AttendeePage(authedPage);
    await attendees.goto(event.eventId);
    await attendees.openRowAction(order.buyerEmail, 'Cancel ticket');
    const confirm = authedPage.getByRole('dialog');
    await expect(confirm).toContainText('release seat Stalls · A-10');
    await confirm.getByRole('button', { name: 'Confirm' }).click();
    await expect(authedPage.getByText('Successfully cancelled attendee')).toBeVisible();
    await expect(attendees.rowByText(order.buyerEmail).getByText('Cancelled', { exact: true })).toBeVisible();

    await openPublicMap(page, event);
    await expect(publicSeat(page, A10)).toHaveAttribute('data-state', 'free');
    expect(await occupiedUids(api, event)).not.toContain(A10);

    await attendees.openRowAction(order.buyerEmail, 'Activate');
    await authedPage.getByRole('dialog').getByRole('button', { name: 'Confirm' }).click();
    await expect(authedPage.getByText('Successfully activated attendee')).toBeVisible();

    expect((await seatedAttendee(api, event, order.buyerEmail)).status).toBe('ACTIVE');
    expect(await occupiedUids(api, event)).toContain(A10);
    await openPublicMap(page, event);
    await expect(publicSeat(page, A10)).toHaveAttribute('data-state', 'unavailable');
  });

  test('an organizer is told the seat was given away when reactivating a cancelled attendee', async ({ authedPage, api, publicApi, account }) => {
    const event = await createSeatedEvent(api, account.organizerId);
    const order = await createSeatedOrder(publicApi, event, [A10], { buyerEmail: uniqueEmail('seatholder') });
    const attendee = await seatedAttendee(api, event, order.buyerEmail);
    await api.updateAttendeeStatus(event.eventId, attendee.id, 'CANCELLED');
    await createSeatedOrder(publicApi, event, [A10], { buyerEmail: uniqueEmail('newcomer') });

    const attendees = new AttendeePage(authedPage);
    await attendees.goto(event.eventId);
    await attendees.openRowAction(order.buyerEmail, 'Activate');
    await authedPage.getByRole('dialog').getByRole('button', { name: 'Confirm' }).click();

    await expect(authedPage.getByText('Seat Stalls · A-10 has since been given to someone else')).toBeVisible();
    await expect(attendees.rowByText(order.buyerEmail).getByText('Cancelled', { exact: true })).toBeVisible();
  });

  test('a buyer who cancels their order at checkout gives the seats back', async ({ page, api, account }) => {
    const event = await createSeatedEvent(api, account.organizerId);
    await openPublicMap(page, event);
    await pickSeatsAndContinue(page, [A10, A11]);
    expect(await occupiedUids(api, event)).toEqual(expect.arrayContaining([A10, A11]));

    await page.getByRole('button', { name: 'Back to event page' }).click();
    await page.getByRole('button', { name: 'Yes, cancel my order' }).click();
    await expect(page.getByText('Your order has been cancelled.')).toBeVisible();

    await expect(new SeatPickerPage(page).root()).toBeVisible();
    await expect(publicSeat(page, A10)).toHaveAttribute('data-state', 'free');
    await expect(publicSeat(page, A11)).toHaveAttribute('data-state', 'free');
    expect(await occupiedUids(api, event)).toEqual([]);
  });

  test('a buyer who comes back and picks another seat no longer holds the first one', async ({ page, api, account }) => {
    const event = await createSeatedEvent(api, account.organizerId);
    await openPublicMap(page, event);
    await pickSeatsAndContinue(page, [A10]);
    expect(await occupiedUids(api, event)).toEqual([A10]);

    await openPublicMap(page, event);
    await pickSeatsAndContinue(page, [A11]);
    await expect(page.getByTestId('inline-order-summary')).toContainText('Stalls · A-11');

    expect(await occupiedUids(api, event)).toEqual([A11]);
    await openPublicMap(page, event);
    await expect(publicSeat(page, A10)).toHaveAttribute('data-state', 'free');
    await expect(publicSeat(page, A11)).toHaveAttribute('data-state', 'unavailable');
  });

  test('cancelling an order awaiting offline payment frees its seat', async ({ page, api, publicApi, account }) => {
    const event = await createSeatedEvent(api, account.organizerId, { price: 25 });
    const order = await createAwaitingOfflineOrder(api, publicApi, event, {
      seatUids: [A10],
      eventOccurrenceId: event.occurrenceId,
      buyerEmail: uniqueEmail('offline'),
    });

    await api.cancelOrder(event.eventId, order.orderId);

    expect(await occupiedUids(api, event)).toEqual([]);
    await openPublicMap(page, event);
    await expect(publicSeat(page, A10)).toHaveAttribute('data-state', 'free');
  });

  test('voiding a seated door sale frees the seat on the door map', async ({ page, api, account }) => {
    const event = await createSeatedEvent(api, account.organizerId);
    const {door, boxOfficeId} = await sellA10AtTheDoor(page, api, event);
    await door.newSale();
    await page.reload();
    await expect(door.seat(A10)).toHaveAttribute('data-state', 'unavailable');

    await voidDoorSale(page, api, event, door, boxOfficeId);
    expect(await occupiedUids(api, event)).toEqual([]);

    await page.reload();
    await expect(door.seat(A10)).toHaveAttribute('data-state', 'free');
  });

  test('the door map reflects its own sales and voids without waiting for a refresh', async ({ page, api, account }) => {
    const event = await createSeatedEvent(api, account.organizerId);
    const {door, boxOfficeId} = await sellA10AtTheDoor(page, api, event);

    await door.newSale();
    await expect(door.seat(A10)).toHaveAttribute('data-state', 'unavailable', { timeout: 3000 });

    await voidDoorSale(page, api, event, door, boxOfficeId);
    await door.openSellTab();
    await expect(door.seat(A10)).toHaveAttribute('data-state', 'free', { timeout: 3000 });
  });
});

async function sellA10AtTheDoor(page: Page, api: ApiClient, event: SeatedEvent): Promise<{ door: BoxOfficePage; boxOfficeId: number }> {
  const boxOffice = await defaultBoxOffice(api, event.eventId);
  const door = new BoxOfficePage(page);
  await door.goto(boxOffice.short_id);
  await door.startSession('Sam', boxOffice.pin);
  await expect(door.seatMap()).toBeVisible();

  await door.seat(A10).click();
  await expect(door.cartSeats(event.priceId)).toContainText('Stalls · A-10');
  await door.charge();
  await page.getByRole('button', { name: 'Complete sale' }).click();
  await expect(door.saleCompleteHeading()).toBeVisible();
  return { door, boxOfficeId: boxOffice.id };
}

async function voidDoorSale(page: Page, api: ApiClient, event: SeatedEvent, door: BoxOfficePage, boxOfficeId: number): Promise<void> {
  const sale = (await api.listOrders(event.eventId)).find((order) => order.box_office_id === boxOfficeId);
  expect(sale, 'no door sale found for this box office').toBeDefined();
  await door.openOrdersTab();
  await door.searchOrders(sale!.public_id);
  await door.openOrder(sale!.public_id);
  await door.voidSale();
  await expect(page.getByText('This sale was voided')).toBeVisible();
  await page.keyboard.press('Escape');
}
