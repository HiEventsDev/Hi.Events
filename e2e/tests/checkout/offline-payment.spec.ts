import { test, expect } from '../../fixtures';
import { CheckoutPage } from '../../pages/checkout.page';
import {
  createLiveEventWithPaidTicket,
  enableOfflinePayments,
  OFFLINE_PAYMENT_INSTRUCTIONS,
} from '../../api/factory';
import { uniqueEmail } from '../../utils/unique';

test.describe('offline payment checkout', () => {
  test('a buyer completes an offline-payment order and it is marked as paid', { tag: '@smoke' }, async ({ page, api, account, mailpit }) => {
    const event = await createLiveEventWithPaidTicket(api, account.organizerId);
    await enableOfflinePayments(api, event.eventId);
    const buyerEmail = uniqueEmail('offlinebuyer');
    const buyer = { firstName: 'Offline', lastName: 'Buyer', email: buyerEmail };

    const checkout = new CheckoutPage(page);
    await checkout.gotoPublicEvent(event.eventId, event.slug);
    await checkout.setFirstProductQuantity(1);
    await checkout.continueToCheckout();
    await checkout.fillOrderDetails(buyer);
    await checkout.fillFirstAttendee(buyer);
    await checkout.continueToPayment();

    await expect(page.getByTestId('pay-later-steps')).toBeVisible();
    await expect(page.getByText(OFFLINE_PAYMENT_INSTRUCTIONS)).toBeHidden();
    await expect(page.getByTestId('offline-payment-button')).toHaveText('Place order');
    await expect(page.getByTestId('pay-later-amount')).toContainText('due after you place your order');
    await checkout.chooseOfflinePayment();

    await expect(page.getByText('Your order is awaiting payment')).toBeVisible();
    await expect(page.getByRole('heading', { name: 'How to pay' })).toBeVisible();
    await expect(page.getByTestId('payment-amount-due')).toBeVisible();
    await expect(page.getByTestId('payment-reference')).toBeVisible();
    await expect(page.getByText(OFFLINE_PAYMENT_INSTRUCTIONS)).toBeVisible();
    await mailpit.waitForMessage(buyerEmail, { subjectContains: 'Order placed: payment needed' });

    const emailsBeforePayment = (await mailpit.search(buyerEmail)).length;
    const orderShortId = page.url().match(/\/checkout\/\d+\/([^/?]+)\/summary/)![1];
    const orderId = await api.findOrderIdByShortId(event.eventId, orderShortId);
    await api.markOrderAsPaid(event.eventId, orderId);

    await page.reload();
    await page.waitForLoadState('networkidle');

    await expect(page.getByText(`You're going to ${event.title}`)).toBeVisible();
    await expect(page.getByText('Confirmation sent to')).toBeVisible();
    await expect(page.getByText('Your order is awaiting payment')).toBeHidden();

    await expect
      .poll(async () => (await mailpit.search(buyerEmail)).length, { timeout: 15_000 })
      .toBeGreaterThan(emailsBeforePayment);
    await mailpit.waitForMessage(buyerEmail, { subjectContains: 'Your Order is Confirmed' });
  });
});
