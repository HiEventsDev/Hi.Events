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
  };
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
});
