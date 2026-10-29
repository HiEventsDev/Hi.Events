import { test, expect } from '../../fixtures';
import { createFreshOrganizer, createLiveEventWithProduct } from '../../api/factory';
import { uniqueName } from '../../utils/unique';

test.describe('embed parent url', () => {
  test('a widget page opened outside an iframe ignores the parentUrl in its link', async ({ page, api }) => {
    const organizer = await createFreshOrganizer(api, uniqueName('E2E Parent Url Org'));
    const event = await createLiveEventWithProduct(api, { organizerId: organizer.id });

    await page.goto(`/widget/${event.eventId}?parentUrl=${encodeURIComponent('https://attacker.example/')}`);
    await page.waitForLoadState('networkidle');
    await expect(page.getByText(event.productTitle).first()).toBeVisible();

    expect(await page.evaluate(() => sessionStorage.getItem('hievents.checkout.parentUrl'))).toBeNull();
  });
});
