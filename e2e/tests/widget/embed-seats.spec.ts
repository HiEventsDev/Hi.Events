import { test, expect } from '../../fixtures';
import { createSeatedEvent } from '../../api/factory';
import { checkoutModal, openEmbeddedWidget, resolveCheckoutFrame } from '../../pages/embedded-widget';
import { CheckoutPage } from '../../pages/checkout.page';
import { uniqueCode, uniqueEmail } from '../../utils/unique';

test.describe('embedded widget reserved seating', () => {
  test.beforeEach(async ({ adminApi, account }) => {
    await adminApi.setAccountFeatureFlag(await adminApi.findAccountIdByEmail(account.email), 'seating', true);
  });

  test('a buyer picks a seat in the host page modal and completes the order there', { tag: '@smoke' }, async ({ page, api, account, baseURL }) => {
    const event = await createSeatedEvent(api, account.organizerId);

    const widgetFrame = await openEmbeddedWidget(page, baseURL!, event.eventId);
    await widgetFrame.getByTestId('choose-seats-button').click();

    await expect(checkoutModal(page)).toBeVisible();
    const seatFrame = await resolveCheckoutFrame(page);
    await seatFrame.waitForURL(/\/checkout\/\d+\/seats/);

    await seatFrame.getByTestId('seat-picker-map').locator('[data-uid="e2.0.9"]').click();
    await seatFrame.getByTestId('seat-picker-continue-button').click();

    await seatFrame.waitForURL(/\/checkout\/\d+\/[^/]+\/details/);
    await expect(seatFrame.getByText('Stalls · A-10').first()).toBeVisible();

    const checkout = new CheckoutPage(page, seatFrame);
    const email = uniqueEmail('embedseat');
    await checkout.fillOrderAndAttendees({ firstName: 'Emma', lastName: 'Embed', email }, 1);
    await checkout.completeFreeOrder();

    await expect(seatFrame.getByText("You're going to", { exact: false }).first()).toBeVisible();
    await expect(seatFrame.getByText('Stalls · A-10').filter({ visible: true }).first()).toBeVisible();
    const [attendee] = await api.listAttendees(event.eventId);
    expect(attendee.email).toBe(email);
    expect(attendee.seat_label).toBe('Stalls · A-10');
  });

  test('a promo code applied in the widget carries into the host page seat picker', async ({ page, api, account, baseURL }) => {
    const event = await createSeatedEvent(api, account.organizerId, { price: 30 });
    await api.updateEventSettings(event.eventId, { pass_platform_fee_to_buyer: false });
    const code = uniqueCode('SEATS');
    await api.createPromoCode(event.eventId, { code, discount_type: 'PERCENTAGE', discount: 20, applicable_product_ids: [] });

    const widgetFrame = await openEmbeddedWidget(page, baseURL!, event.eventId);
    await new CheckoutPage(page, widgetFrame).applyPromoCode(code);
    await expect(widgetFrame.getByText(code, { exact: true })).toBeVisible();
    await widgetFrame.getByTestId('choose-seats-button').click();

    const seatFrame = await resolveCheckoutFrame(page);
    await seatFrame.waitForURL(new RegExp(`/checkout/\\d+/seats\\?.*promo_code=${code}`));
    const picker = seatFrame.getByRole('dialog', { name: 'Choose your seats' });
    await picker.getByTestId('seat-picker-map').locator('[data-uid="e2.0.9"]').click();
    await expect(picker.getByText('Premium Seat · $24.00')).toBeVisible();
    await picker.getByTestId('seat-picker-continue-button').click();

    await seatFrame.waitForURL(/\/checkout\/\d+\/[^/]+\/details/);
    await expect(seatFrame.getByTestId('inline-order-summary')).toContainText('$24.00');
  });
});
