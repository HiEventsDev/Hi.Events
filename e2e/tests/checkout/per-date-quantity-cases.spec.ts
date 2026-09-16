import { expect } from '@playwright/test';
import { test } from '../../fixtures';
import { OccurrencePage } from '../../pages/occurrence.page';
import { PublicDateView } from '../../pages/public-date.page';
import { ProductEditPage } from '../../pages/product-edit.page';
import { PublicEventPage } from '../../pages/public-event.page';
import { createCompletedOrder, createRecurringLiveEvent } from '../../api/factory';
import type { Occurrence } from '../../api/types';

type SeededRecurringEvent = Awaited<ReturnType<typeof createRecurringLiveEvent>>;

const sortedDates = (event: SeededRecurringEvent): Occurrence[] =>
  [...event.occurrences].sort((a, b) => a.start_date.localeCompare(b.start_date));

test.describe('per-date quantity edge cases', () => {
  test('a per-date override caps one date without touching the others', async ({ page, api, publicApi, account }) => {
    const event = await createRecurringLiveEvent(api, account.organizerId, { count: 2, quantityAvailable: 5 });
    const [day1, day2] = sortedDates(event);
    await api.setOccurrencePriceOverride(event.eventId, day1.id, { product_price_id: event.priceId, quantity_available: 1 });
    await createCompletedOrder(publicApi, event, { eventOccurrenceId: day1.id });

    const view = new PublicDateView(page, event);
    await view.expectSoldOut(day1);
    await view.expectAvailable(day2);
  });

  test('an occurrence capacity caps a per-date ticket below its quantity', async ({ page, api, publicApi, account }) => {
    const event = await createRecurringLiveEvent(api, account.organizerId, { count: 2, quantityAvailable: 5 });
    const [day1, day2] = sortedDates(event);
    await api.updateOccurrence(event.eventId, day1.id, { start_date: day1.start_date, end_date: day1.end_date, capacity: 1 });
    await createCompletedOrder(publicApi, event, { eventOccurrenceId: day1.id });

    const view = new PublicDateView(page, event);
    await view.open(day1);
    await expect(page.getByRole('button', { name: /Sold Out$/ })).toBeDisabled();
    await view.expectAvailable(day2);
  });

  test('tiers of one product can mix per-date and all-dates quantities', async ({ page, api, publicApi, account }) => {
    const event = await createRecurringLiveEvent(api, account.organizerId, { count: 2 });
    const [day1, day2] = sortedDates(event);
    const categories = await api.listProductCategories(event.eventId);
    const created = await api.createProduct(event.eventId, {
      title: 'Mixed Ticket',
      product_type: 'TICKET',
      type: 'TIERED',
      product_category_id: categories[0].id,
      prices: [
        { price: 0, label: 'Early bird', initial_quantity_available: 1, quantity_applies_to: 'OCCURRENCE' },
        { price: 0, label: 'General', initial_quantity_available: 1, quantity_applies_to: 'EVENT' },
      ],
    });
    const product = await api.getProduct(event.eventId, created.id);
    const earlyBirdPriceId = product.prices!.find((price) => price.label === 'Early bird')!.id;
    const generalPriceId = product.prices!.find((price) => price.label === 'General')!.id;
    await createCompletedOrder(publicApi, { eventId: event.eventId, productId: created.id, priceId: earlyBirdPriceId }, { eventOccurrenceId: day1.id });
    await createCompletedOrder(publicApi, { eventId: event.eventId, productId: created.id, priceId: generalPriceId }, { eventOccurrenceId: day1.id });

    const view = new PublicDateView(page, event);
    await view.open(day1);
    await expect(view.tierRow('Mixed Ticket', 'Early bird')).toHaveAttribute('data-unavailable', 'sold-out');
    await expect(view.tierRow('Mixed Ticket', 'General')).toHaveAttribute('data-unavailable', 'sold-out');

    await view.open(day2);
    await expect(view.tierRow('Mixed Ticket', 'Early bird')).not.toHaveAttribute('data-unavailable', /.+/);
    await expect(view.tierRow('Mixed Ticket', 'General')).toHaveAttribute('data-unavailable', 'sold-out');
  });

  test('a per-date general product sells out per date', async ({ page, api, account }) => {
    const event = await createRecurringLiveEvent(api, account.organizerId, { count: 2 });
    const [day1, day2] = sortedDates(event);
    const categories = await api.listProductCategories(event.eventId);
    await api.createProduct(event.eventId, {
      title: 'Parking Pass',
      product_type: 'GENERAL',
      type: 'FREE',
      product_category_id: categories[0].id,
      prices: [{ price: 0, initial_quantity_available: 1, quantity_applies_to: 'OCCURRENCE' }],
    });

    const view = new PublicDateView(page, event);
    await view.reserveOne(day1, 'Parking Pass');
    await view.expectSoldOut(day1, 'Parking Pass');
    await view.expectAvailable(day2, 'Parking Pass');
  });

  test('cancelling an attendee frees the slot on that date', async ({ page, api, publicApi, account }) => {
    const event = await createRecurringLiveEvent(api, account.organizerId, { count: 2, quantityAvailable: 1 });
    const [day1] = sortedDates(event);
    await createCompletedOrder(publicApi, event, { eventOccurrenceId: day1.id });

    const view = new PublicDateView(page, event);
    await view.expectSoldOut(day1);

    const [attendee] = await api.listAttendees(event.eventId);
    await api.updateAttendeeStatus(event.eventId, attendee.id, 'CANCELLED');
    await view.expectAvailable(day1);
  });

  test('manually adding an attendee respects the per-date cap', async ({ api, publicApi, account }) => {
    const event = await createRecurringLiveEvent(api, account.organizerId, { count: 2, quantityAvailable: 1 });
    const [day1, day2] = sortedDates(event);
    await createCompletedOrder(publicApi, event, { eventOccurrenceId: day1.id });

    const attendee = (occurrenceId: number) => ({
      product_id: event.productId,
      product_price_id: event.priceId,
      event_occurrence_id: occurrenceId,
      email: `manual+${occurrenceId}@hievents.test`,
      first_name: 'Manual',
      last_name: 'Attendee',
      amount_paid: 0,
      send_confirmation_email: false,
      locale: 'en',
    });

    await expect(api.createAttendee(event.eventId, attendee(day1.id))).rejects.toThrow(/no tickets available/i);
    const created = await api.createAttendee(event.eventId, attendee(day2.id));
    expect(created.id).toBeGreaterThan(0);
  });

  test('a sold-out date offers the waitlist while other dates keep selling', async ({ page, api, publicApi, account }) => {
    const event = await createRecurringLiveEvent(api, account.organizerId, { count: 2, quantityAvailable: 1, waitlistEnabled: true });
    const [day1, day2] = sortedDates(event);
    await createCompletedOrder(publicApi, event, { eventOccurrenceId: day1.id });

    const view = new PublicDateView(page, event);
    const publicPage = new PublicEventPage(page);
    await view.open(day1);
    await expect(publicPage.joinWaitlistButton()).toBeVisible();

    await view.expectAvailable(day2);
    await expect(publicPage.joinWaitlistButton()).toHaveCount(0);
  });

  test('lowering a per-date quantity below the busiest date is rejected', async ({ authedPage, api, publicApi, account }) => {
    const event = await createRecurringLiveEvent(api, account.organizerId, { count: 2, quantityAvailable: 2 });
    const [day1] = sortedDates(event);
    await createCompletedOrder(publicApi, event, { eventOccurrenceId: day1.id, quantity: 2 });

    const products = new ProductEditPage(authedPage);
    await products.goto(event.eventId);
    await products.openFirstProduct();
    await products.setQuantity(1);
    await products.clickSubmit();
    await expect(authedPage.getByText(/cannot be less than the number already sold on a single date \(2\)/)).toBeVisible();

    await products.setQuantity(2);
    await products.submitEdit();
    await expect(authedPage.getByText('up to 2 per date')).toBeVisible();
  });

  test('switching a tier to all dates below its total sales is rejected', async ({ authedPage, api, publicApi, account }) => {
    const event = await createRecurringLiveEvent(api, account.organizerId, { count: 2, quantityAvailable: 1 });
    const [day1, day2] = sortedDates(event);
    await createCompletedOrder(publicApi, event, { eventOccurrenceId: day1.id });
    await createCompletedOrder(publicApi, event, { eventOccurrenceId: day2.id });

    const products = new ProductEditPage(authedPage);
    await products.goto(event.eventId);
    await products.openFirstProduct();
    await products.chooseQuantityScope('EVENT');
    await products.clickSubmit();
    await expect(authedPage.getByText(/cannot be less than the number already sold \(2\)/)).toBeVisible();

    await products.setQuantity(2);
    await products.submitEdit();
    await expect(authedPage.getByText('total', { exact: true })).toBeVisible();
  });

  test('a per-date override below that date\'s sales is rejected', async ({ authedPage, api, publicApi, account }) => {
    const event = await createRecurringLiveEvent(api, account.organizerId, { count: 2, quantityAvailable: 5 });
    const [day1] = sortedDates(event);
    await createCompletedOrder(publicApi, event, { eventOccurrenceId: day1.id, quantity: 2 });

    const occurrences = new OccurrencePage(authedPage);
    await occurrences.goto(event.eventId);
    await occurrences.chooseRowAction(occurrences.occurrenceRows().first(), 'Products');
    await occurrences.quantityOverrideInput().fill('1');
    await occurrences.saveProductSettings();
    await expect(authedPage.getByText(/cannot be less than the number already sold \(2\)/)).toBeVisible();

    await occurrences.quantityOverrideInput().fill('2');
    await occurrences.saveProductSettings();
    await expect(authedPage.getByText('Product settings saved successfully')).toBeVisible();
  });

  test('the date tab has no quantity override for an all-dates tier', async ({ authedPage, api, account }) => {
    const event = await createRecurringLiveEvent(api, account.organizerId, { count: 2, quantityAvailable: 5, quantityAppliesTo: 'EVENT' });

    const occurrences = new OccurrencePage(authedPage);
    await occurrences.goto(event.eventId);
    await occurrences.chooseRowAction(occurrences.occurrenceRows().first(), 'Products');
    await expect(occurrences.productCard(event.productTitle)).toBeVisible();
    await expect(occurrences.overrideInput()).toBeVisible();
    await expect(occurrences.quantityOverrideInput()).toHaveCount(0);
  });
});
