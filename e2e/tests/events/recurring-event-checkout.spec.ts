import { test, expect } from '../../fixtures';
import { CheckoutPage } from '../../pages/checkout.page';
import { PublicOccurrenceSelector } from '../../pages/occurrence.page';
import { createRecurringLiveEvent } from '../../api/factory';
import { uniqueEmail } from '../../utils/unique';

const utcParts = (isoDate: string) => {
  const date = new Date(isoDate);
  return {
    weekday: date.toLocaleString('en-US', { weekday: 'long', timeZone: 'UTC' }),
    month: date.toLocaleString('en-US', { month: 'long', timeZone: 'UTC' }),
    day: date.getUTCDate(),
    year: date.getUTCFullYear(),
  };
};

const utcMonthsBetween = (fromIsoDate: string, toIsoDate: string) => {
  const from = new Date(fromIsoDate);
  const to = new Date(toIsoDate);
  return (to.getUTCFullYear() - from.getUTCFullYear()) * 12 + (to.getUTCMonth() - from.getUTCMonth());
};

test.describe('recurring event checkout', () => {
  test('a buyer picks a specific occurrence and completes a free order', async ({ page, api, account }) => {
    const event = await createRecurringLiveEvent(api, account.organizerId, { count: 3 });
    const sorted = [...event.occurrences].sort((a, b) => a.start_date.localeCompare(b.start_date));
    const { weekday, month, day } = utcParts(sorted[1].start_date);
    const buyer = { firstName: 'Recurring', lastName: 'Buyer', email: uniqueEmail('buyer') };

    const checkout = new CheckoutPage(page);
    const selector = new PublicOccurrenceSelector(page);
    await checkout.gotoPublicEvent(event.eventId, event.slug);
    await selector.selectDay(new RegExp(`^${weekday}, ${month} ${day},`));

    await expect(selector.slotHeaderDay()).toHaveText(`${weekday}, ${month} ${day}`);
    await expect(selector.productsLoadingOverlay()).toHaveCount(0);

    await checkout.setFirstProductQuantity(1);
    await checkout.continueToCheckout();
    await checkout.fillOrderDetails(buyer);
    await checkout.completeFreeOrder();

    await expect(page.getByText(`You're going to ${event.title}`)).toBeVisible();
    await expect(page.getByText(new RegExp(`${month} ${day}\\b`)).first()).toBeVisible();
  });

  test('a buyer navigates months beyond the embedded window and completes an order', async ({ page, api, account }) => {
    const event = await createRecurringLiveEvent(api, account.organizerId, { count: 20 });
    const sorted = [...event.occurrences].sort((a, b) => a.start_date.localeCompare(b.start_date));
    const target = sorted[sorted.length - 2];
    const monthsAhead = utcMonthsBetween(sorted[0].start_date, target.start_date);
    expect(monthsAhead).toBeGreaterThanOrEqual(3);
    const { weekday, month, day } = utcParts(target.start_date);
    const buyer = { firstName: 'FarMonth', lastName: 'Buyer', email: uniqueEmail('buyer') };

    const checkout = new CheckoutPage(page);
    const selector = new PublicOccurrenceSelector(page);
    await checkout.gotoPublicEvent(event.eventId, event.slug);
    await selector.calendar().waitFor();
    for (let i = 0; i < monthsAhead; i++) {
      await selector.nextMonthButton().click();
    }
    await selector.dayButton(new RegExp(`^${weekday}, ${month} ${day},`)).click();

    await expect(selector.slotHeaderDay()).toHaveText(`${weekday}, ${month} ${day}`);
    await expect(selector.productsLoadingOverlay()).toHaveCount(0);

    await checkout.setFirstProductQuantity(1);
    await checkout.continueToCheckout();
    await checkout.fillOrderDetails(buyer);
    await checkout.completeFreeOrder();

    await expect(page.getByText(`You're going to ${event.title}`)).toBeVisible();
    await expect(page.getByText(new RegExp(`${month} ${day}\\b`)).first()).toBeVisible();
  });

  test('a deep link to a far-out occurrence anchors the calendar on its month', async ({ page, api, account }) => {
    const event = await createRecurringLiveEvent(api, account.organizerId, { count: 20 });
    const sorted = [...event.occurrences].sort((a, b) => a.start_date.localeCompare(b.start_date));
    const target = sorted[sorted.length - 1];
    expect(utcMonthsBetween(sorted[0].start_date, target.start_date)).toBeGreaterThanOrEqual(3);
    const { weekday, month, day, year } = utcParts(target.start_date);

    const selector = new PublicOccurrenceSelector(page);
    await page.goto(`/event/${event.eventId}/${event.slug}?occurrence_id=${target.id}`);

    await expect(selector.monthHeader()).toHaveText(`${month} ${year}`);
    await expect(selector.slotHeaderDay()).toHaveText(`${weekday}, ${month} ${day}`);
  });
});
