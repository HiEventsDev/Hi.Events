import { test as base, expect } from '../../fixtures';
import { CheckoutPage } from '../../pages/checkout.page';
import { createLiveEventWithPaidTicket } from '../../api/factory';
import { IS_SAAS_MODE } from '../../utils/env';
import { uniqueCode, uniqueName } from '../../utils/unique';

const test = base.extend<{ feeOrganizerId: number }>({
  feeOrganizerId: async ({ api, adminApi }, use) => {
    const organizer = await api.createOrganizer(uniqueName('E2E Platform Fee Org'));
    const configuration = await adminApi.createConfiguration({
      name: uniqueName('E2E 1.5% Platform Fee'),
      application_fees: { percentage: 1.5, fixed: 0, currency: 'USD' },
    });
    await adminApi.assignOrganizerConfiguration(organizer.id, configuration.id);

    await use(organizer.id);

    const systemDefault = (await adminApi.listConfigurations()).find((config) => config.is_system_default);
    if (systemDefault) {
      await adminApi.assignOrganizerConfiguration(organizer.id, systemDefault.id);
      await adminApi.deleteConfiguration(configuration.id);
    }
  },
});

test.describe('promo code with a platform fee passed to the buyer', () => {
  test.skip(!IS_SAAS_MODE, 'The platform fee is only charged in SaaS mode.');

  test('the inclusive struck price is the fee-inclusive price the buyer saw before the promo', async ({ page, api, feeOrganizerId }) => {
    const event = await createLiveEventWithPaidTicket(api, feeOrganizerId, 20);
    await api.updateEventSettings(event.eventId, { price_display_mode: 'INCLUSIVE', pass_platform_fee_to_buyer: true });
    const code = uniqueCode();
    await api.createPromoCode(event.eventId, { code, discount_type: 'PERCENTAGE', discount: 10 });

    const checkout = new CheckoutPage(page);
    await checkout.gotoPublicEvent(event.eventId, event.slug);
    const productRow = page.locator('.hi-product-row').filter({ hasText: event.productTitle });
    const shownPrice = productRow.locator('.hi-price-amount').first();
    await expect(shownPrice).toHaveText('$20.30');
    await expect(productRow.locator('.hi-price-strike')).toHaveCount(0);

    await checkout.applyPromoCode(code);

    await expect(shownPrice).toHaveText('$18.27');
    await expect(productRow.locator('.hi-price-strike')).toHaveText('$20.30');
  });
});
