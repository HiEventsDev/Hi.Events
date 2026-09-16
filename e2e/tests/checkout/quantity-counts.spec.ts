import { expect } from '@playwright/test';
import { test } from '../../fixtures';
import { CheckoutPage } from '../../pages/checkout.page';
import { OccurrencePage } from '../../pages/occurrence.page';
import { ProductEditPage } from '../../pages/product-edit.page';
import { PublicDateView } from '../../pages/public-date.page';
import {
  createAwaitingOfflineOrder,
  createCompletedOrder,
  createLiveEventWithProduct,
  createRecurringLiveEvent,
} from '../../api/factory';
import type { Occurrence } from '../../api/types';
import { uniqueEmail } from '../../utils/unique';

type SeededRecurringEvent = Awaited<ReturnType<typeof createRecurringLiveEvent>>;

const sortedDates = (event: SeededRecurringEvent): Occurrence[] =>
  [...event.occurrences].sort((a, b) => a.start_date.localeCompare(b.start_date));

const buyer = (prefix: string) => ({ firstName: 'Count', lastName: 'Buyer', email: uniqueEmail(prefix) });

test.describe('remaining quantity counts', () => {
  test('a single event counts reservations, completed orders and cancellations', async ({ page, authedPage, api, publicApi, account }) => {
    const event = await createLiveEventWithProduct(api, {
      organizerId: account.organizerId,
      price: 0,
      quantityAvailable: 3,
      showQuantityRemaining: true,
    });
    const checkout = new CheckoutPage(page);
    const row = () => page.locator('.hi-product-row').filter({ hasText: event.productTitle });

    await checkout.gotoPublicEvent(event.eventId, event.slug);
    await expect(row().locator('.hi-scarcity-pill')).toHaveText('3 available');

    await checkout.setFirstProductQuantity(1);
    await checkout.continueToCheckout();
    await checkout.gotoPublicEventAfterAvailabilityCacheExpires(event.eventId, event.slug);
    await expect(row().locator('.hi-scarcity-pill')).toHaveText('2 available');

    const completed = await createCompletedOrder(publicApi, event);
    await checkout.gotoPublicEventAfterAvailabilityCacheExpires(event.eventId, event.slug);
    await expect(row().locator('.hi-scarcity-pill')).toHaveText('1 available');

    const products = new ProductEditPage(authedPage);
    await products.goto(event.eventId);
    await expect(products.salesCount()).toContainText('1');
    await expect(products.salesCount()).toContainText('/ 3');

    await api.cancelOrder(event.eventId, await api.findOrderIdByShortId(event.eventId, completed.orderShortId));
    await checkout.gotoPublicEventAfterAvailabilityCacheExpires(event.eventId, event.slug);
    await expect(row().locator('.hi-scarcity-pill')).toHaveText('2 available');
  });

  test('a per-date ticket counts reservations on that date only', async ({ page, api, account }) => {
    const event = await createRecurringLiveEvent(api, account.organizerId, { count: 2, quantityAvailable: 3, showQuantityRemaining: true });
    const [day1, day2] = sortedDates(event);

    const view = new PublicDateView(page, event);
    await view.expectRemaining(day1, 3);
    await view.reserveOne(day1);
    await view.expectRemaining(day1, 2);
    await view.expectRemaining(day2, 3);
  });

  test('a per-date ticket counts offline-pending and paid sales and frees cancelled attendees', async ({ page, authedPage, api, publicApi, account }) => {
    const event = await createRecurringLiveEvent(api, account.organizerId, { count: 2, price: 10, quantityAvailable: 3, showQuantityRemaining: true });
    const [day1, day2] = sortedDates(event);
    const offline = await createAwaitingOfflineOrder(api, publicApi, event, { eventOccurrenceId: day1.id });

    const view = new PublicDateView(page, event);
    await view.expectRemaining(day1, 2);
    await view.expectRemaining(day2, 3);

    await api.markOrderAsPaid(event.eventId, offline.orderId);
    await view.expectRemaining(day1, 2);

    const products = new ProductEditPage(authedPage);
    await products.goto(event.eventId);
    await expect(products.salesCount()).toContainText('1');
    await expect(products.salesCount()).toContainText('up to 3 per date');

    const [attendee] = await api.listAttendees(event.eventId);
    await api.updateAttendeeStatus(event.eventId, attendee.id, 'CANCELLED');
    await view.expectRemaining(day1, 3);

    await products.goto(event.eventId);
    await expect(products.salesCount()).toContainText('0');
  });

  test('a per-date general product counts order items and guards edits against the busiest date', async ({ page, authedPage, api, account }) => {
    const event = await createRecurringLiveEvent(api, account.organizerId, { count: 2 });
    const [day1, day2] = sortedDates(event);
    const categories = await api.listProductCategories(event.eventId);
    await api.createProduct(event.eventId, {
      title: 'Parking Pass',
      product_type: 'GENERAL',
      type: 'FREE',
      product_category_id: categories[0].id,
      show_quantity_remaining: true,
      prices: [{ price: 0, initial_quantity_available: 3, quantity_applies_to: 'OCCURRENCE' }],
    });

    const view = new PublicDateView(page, event);
    await view.completeFreeOrder(day1, 2, buyer('parking'), 'Parking Pass');
    await view.expectRemaining(day1, 1, 'Parking Pass');
    await view.expectRemaining(day2, 3, 'Parking Pass');

    const products = new ProductEditPage(authedPage);
    await products.goto(event.eventId);
    await expect(products.salesCount(1)).toContainText('2');
    await expect(products.salesCount(1)).toContainText('up to 3 per date');

    await products.openProductAt(1);
    await products.setQuantity(1);
    await products.clickSubmit();
    await expect(authedPage.getByText(/cannot be less than the number already sold on a single date \(2\)/)).toBeVisible();
  });

  test('an occurrence capacity counts reservations against the date', async ({ page, api, account }) => {
    const event = await createRecurringLiveEvent(api, account.organizerId, { count: 2, showQuantityRemaining: true });
    const [day1, day2] = sortedDates(event);
    await api.updateOccurrence(event.eventId, day1.id, { start_date: day1.start_date, end_date: day1.end_date, capacity: 2 });

    const view = new PublicDateView(page, event);
    await view.expectRemaining(day1, 2);
    await view.reserveOne(day1);
    await view.expectRemaining(day1, 1);

    await view.expectAvailable(day2);
    await expect(view.remainingPill()).toHaveCount(0);
  });

  test('an all-dates product shares one pool of sales and reservations across dates', async ({ page, authedPage, api, account }) => {
    const event = await createRecurringLiveEvent(api, account.organizerId, { count: 3 });
    const [day1, day2, day3] = sortedDates(event);
    const categories = await api.listProductCategories(event.eventId);
    await api.createProduct(event.eventId, {
      title: 'Tour T-shirt',
      product_type: 'GENERAL',
      type: 'FREE',
      product_category_id: categories[0].id,
      show_quantity_remaining: true,
      prices: [{ price: 0, initial_quantity_available: 3, quantity_applies_to: 'EVENT' }],
    });

    const view = new PublicDateView(page, event);
    await view.completeFreeOrder(day1, 1, buyer('shirt'), 'Tour T-shirt');
    await view.reserveOne(day2, 'Tour T-shirt');
    await view.expectRemaining(day3, 1, 'Tour T-shirt');

    const products = new ProductEditPage(authedPage);
    await products.goto(event.eventId);
    await expect(products.salesCount(1)).toContainText('1');
    await expect(products.salesCount(1)).toContainText('/ 3');
    await expect(products.salesCount(1)).toContainText('total');
  });

  test('the schedule shows booked against the lower of capacity and per-date allocations', async ({ authedPage, api, publicApi, account }) => {
    const event = await createRecurringLiveEvent(api, account.organizerId, { count: 2, quantityAvailable: 5 });
    const [day1, day2] = sortedDates(event);
    const categories = await api.listProductCategories(event.eventId);
    await api.createProduct(event.eventId, {
      title: 'VIP',
      product_type: 'TICKET',
      type: 'FREE',
      product_category_id: categories[0].id,
      prices: [{ price: 0, initial_quantity_available: 15, quantity_applies_to: 'OCCURRENCE' }],
    });
    await api.updateOccurrence(event.eventId, day1.id, { start_date: day1.start_date, end_date: day1.end_date, capacity: 22 });
    await api.updateOccurrence(event.eventId, day2.id, { start_date: day2.start_date, end_date: day2.end_date, capacity: 10 });
    await createCompletedOrder(publicApi, event, { eventOccurrenceId: day1.id, quantity: 2 });

    const occurrences = new OccurrencePage(authedPage);
    await occurrences.goto(event.eventId);
    const rows = occurrences.occurrenceRows();
    await expect(occurrences.bookedCell(rows.nth(0))).toHaveText('2 / 20');
    await expect(occurrences.bookedCell(rows.nth(1))).toHaveText('0 / 10');

    await occurrences.bookedCell(rows.nth(0)).hover();
    await expect(occurrences.breakdownRow('capacity')).toHaveText('22');
    await expect(occurrences.breakdownRow('allocation')).toHaveText('20');
    await expect(occurrences.breakdownTiers()).toHaveText(['Free Ticket5', 'VIP15']);
    await expect(occurrences.breakdownRow('sellable')).toHaveText('20');
    await expect(occurrences.breakdownLimitedBy()).toHaveText('Limited by ticket allocation');

    await occurrences.chooseRowAction(rows.nth(0), 'Products');
    await expect(occurrences.bookingSummary()).toHaveText('2 of 20 booked');
    await occurrences.openBookingDetails();
    await expect(occurrences.breakdownRow('booked')).toHaveText('2');
    await expect(occurrences.breakdownRow('sellable')).toHaveText('20');
    await occurrences.closeBookingDetails();
    await expect(occurrences.priceAvailability().nth(0)).toHaveText('2 sold · 3 left');
    await expect(occurrences.priceAvailability().nth(1)).toHaveText('0 sold · 15 left');
    await occurrences.closeModal();
    await expect(occurrences.dialog()).toBeHidden();

    await occurrences.chooseRowAction(rows.nth(1), 'Products');
    await expect(occurrences.bookingSummary()).toHaveText('0 of 10 booked');
    await occurrences.openBookingDetails();
    await expect(occurrences.breakdownLimitedBy()).toHaveText('Limited by capacity');
    await occurrences.closeBookingDetails();
    await expect(occurrences.priceAvailability().nth(0)).toHaveText('0 sold · 5 left');
    await occurrences.closeModal();
    await expect(occurrences.dialog()).toBeHidden();

    await occurrences.chooseRowAction(rows.nth(1), 'Edit');
    await expect(occurrences.bookingSummary()).toHaveText('0 of 10 booked');
    await occurrences.dialog().getByPlaceholder('Leave empty for unlimited').fill('30');
    await expect(occurrences.bookingSummary()).toHaveText('0 of 20 booked');
    await occurrences.openBookingDetails();
    await expect(occurrences.breakdownRow('capacity')).toHaveText('30');
    await expect(occurrences.breakdownLimitedBy()).toHaveText('Limited by ticket allocation');
  });

  test('a per-date cap stays visible next to unlimited and shared tiers', async ({ authedPage, api, account }) => {
    const event = await createRecurringLiveEvent(api, account.organizerId, { count: 2 });
    const [day1] = sortedDates(event);
    const categories = await api.listProductCategories(event.eventId);
    await api.createProduct(event.eventId, {
      title: 'VIP',
      product_type: 'TICKET',
      type: 'FREE',
      product_category_id: categories[0].id,
      prices: [{ price: 0, initial_quantity_available: 50, quantity_applies_to: 'OCCURRENCE' }],
    });
    await api.createProduct(event.eventId, {
      title: 'Season pass',
      product_type: 'TICKET',
      type: 'FREE',
      product_category_id: categories[0].id,
      prices: [{ price: 0, initial_quantity_available: 200, quantity_applies_to: 'EVENT' }],
    });

    const occurrences = new OccurrencePage(authedPage);
    await occurrences.goto(event.eventId);
    const row = occurrences.occurrenceRows().first();
    await expect(occurrences.bookedCell(row)).toHaveText('0');
    await occurrences.bookedCell(row).hover();
    await expect(occurrences.breakdownTiers()).toHaveText(['Free TicketUnlimited', 'VIP50', 'Season passAll dates · shared']);
    await expect(occurrences.breakdownRow('sellable')).toHaveText('Unlimited');

    await occurrences.chooseRowAction(row, 'Products');
    await expect(occurrences.bookingSummary()).toHaveText('0 booked · no date limit');
    await occurrences.openBookingDetails();
    await expect(occurrences.breakdownRow('allocation')).toHaveText('50 + unlimited');
    await expect(occurrences.breakdownTiers()).toHaveText(['Free TicketUnlimited', 'VIP50', 'Season passAll dates · shared']);
    await occurrences.closeBookingDetails();
    await expect(occurrences.priceAvailability().nth(1)).toHaveText('0 sold · 50 left');
    void day1;
  });
});
