import { test, expect } from '../../fixtures';
import { CheckoutPage } from '../../pages/checkout.page';
import { occurrenceDayLabel, PublicOccurrenceSelector } from '../../pages/occurrence.page';
import { ProductEditPage } from '../../pages/product-edit.page';
import { createRecurringLiveEvent } from '../../api/factory';

test.describe('per-date ticket quantity', () => {
  test('a per-date ticket cap applies to each date and an all-dates cap is shared @smoke', async ({ page, authedPage, api, account }) => {
    const event = await createRecurringLiveEvent(api, account.organizerId, { count: 3 });
    const [day1, day2, day3] = [...event.occurrences].sort((a, b) => a.start_date.localeCompare(b.start_date));

    const products = new ProductEditPage(authedPage);
    await products.goto(event.eventId);
    await products.openFirstProduct();
    await products.setQuantity(1);
    await products.submitEdit();
    await expect(authedPage.getByText('up to 1 per date')).toBeVisible();

    const checkout = new CheckoutPage(page);
    const selector = new PublicOccurrenceSelector(page);

    const openDate = async (isoDate: string): Promise<void> => {
      await checkout.gotoPublicEvent(event.eventId, event.slug);
      await selector.selectDay(occurrenceDayLabel(isoDate));
      await expect(selector.productsLoadingOverlay()).toHaveCount(0);
      await expect(page.locator('.hi-product-row').filter({ hasText: event.productTitle })).toBeVisible();
    };

    const reserveOne = async (isoDate: string): Promise<void> => {
      await openDate(isoDate);
      await checkout.setFirstProductQuantity(1);
      await checkout.continueToCheckout();
      await expect(page.getByLabel(/^First Name/).first()).toBeVisible();
    };

    const expectSoldOut = async (isoDate: string): Promise<void> => {
      await openDate(isoDate);
      await expect(page.locator('.hi-product-row').filter({ hasText: event.productTitle }).getByText(/sold out/i)).toBeVisible();
    };

    await reserveOne(day1.start_date);
    await expectSoldOut(day1.start_date);
    await reserveOne(day2.start_date);

    await products.goto(event.eventId);
    await products.openFirstProduct();
    await products.chooseQuantityScope('EVENT');
    await products.submitEdit();
    await expect(authedPage.getByText('total', { exact: true })).toBeVisible();

    await expectSoldOut(day3.start_date);
  });
});
