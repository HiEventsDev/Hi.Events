import { test, expect } from '../../fixtures';
import { setWidgetQuantity } from '../../pages/checkout.page';
import { createLiveEventWithFreeTicket } from '../../api/factory';
import { uniqueName } from '../../utils/unique';

test.describe('widget playground', () => {
  test('the playground renders a widget with the configured settings', async ({ page, api, account }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);

    await page.goto(`/widget-test?id=${event.eventId}&continueButtonText=Playground+continue&hostBg=%2318181b`);
    await page.waitForLoadState('networkidle');

    const widget = page.frameLocator('iframe[title="Hi.Events Widget"]');
    await expect(widget.getByRole('heading', { name: event.productTitle })).toBeVisible();
    await expect(widget.getByTestId('checkout-continue-button')).toContainText('Playground continue');

    await expect(page.locator('#embed-code')).toContainText(`data-hievents-id="${event.eventId}"`);
    await expect(page.locator('#embed-code')).toContainText('data-hievents-continue-button-text="Playground continue"');

    await setWidgetQuantity(widget, 1);
    await widget.getByTestId('checkout-continue-button').click();
    await expect(page.getByRole('dialog', { name: 'Checkout' })).toBeVisible();
    const checkout = page.frameLocator('iframe[title="Hi.Events Checkout"]');
    await expect(checkout.getByRole('heading', { name: 'Your Details' })).toBeVisible();

    await expect(page.locator('#message-log')).toContainText('resize');
    await expect(page.locator('#message-log')).toContainText('hievents:open-checkout');
  });

  test('the playground embeds an organizer events list as a custom element', async ({ page, api }) => {
    const organizer = await api.createOrganizer(uniqueName('E2E Events Widget Org'));
    const event = await createLiveEventWithFreeTicket(api, organizer.id);

    await page.goto(`/widget-test?type=events&organizerId=${organizer.id}&markup=element&layout=compact`);
    const list = page.frameLocator('iframe[title="Hi.Events Events"]');
    await expect(list.getByText(event.title)).toBeVisible();
    await expect(page.locator('#embed-code')).toContainText(`<hievents-widget`);
    await expect(page.locator('#embed-code')).toContainText(`organizer-id="${organizer.id}"`);
  });
});

