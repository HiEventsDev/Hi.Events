import { test, expect } from '../../fixtures';
import { CheckoutPage } from '../../pages/checkout.page';
import { OrderPage } from '../../pages/order.page';
import {
  createAwaitingOfflineOrder,
  createLiveEventWithPaidTicket,
  enableOfflinePayments,
  OFFLINE_PAYMENT_INSTRUCTIONS,
} from '../../api/factory';
import { uniqueEmail } from '../../utils/unique';

test.describe('payment links and payment reporting', () => {
  test('a buyer sends a payment link, someone else pays offline and reports it, and the organizer confirms', { tag: '@smoke' }, async ({ page, browser, authedPage, api, account, mailpit }) => {
    const event = await createLiveEventWithPaidTicket(api, account.organizerId);
    await api.updateEventSettings(event.eventId, {
      payment_providers: ['STRIPE', 'OFFLINE'],
      offline_payment_instructions: OFFLINE_PAYMENT_INSTRUCTIONS,
      payment_links_enabled: true,
      payment_link_expiry_hours: 48,
    });
    const buyerEmail = uniqueEmail('linkbuyer');
    const buyer = { firstName: 'Ray', lastName: 'Registrant', email: buyerEmail };

    const checkout = new CheckoutPage(page);
    await checkout.gotoPublicEvent(event.eventId, event.slug);
    await checkout.setFirstProductQuantity(1);
    await checkout.continueToCheckout();
    await checkout.fillOrderDetails(buyer);
    await checkout.fillFirstAttendee(buyer);
    await checkout.continueToPayment();

    await page.getByTestId('open-payment-link-modal').click();
    const modal = page.getByRole('dialog');
    await expect(modal.getByText('Send a payment link')).toBeVisible();
    await expect(modal.getByText('Your places are held for up to 2 days')).toBeVisible();
    await modal.getByTestId('create-payment-link-button').click();

    await expect(modal.getByText('Your payment link is ready')).toBeVisible();
    const paymentLink = await modal.getByLabel('Payment link').inputValue();
    expect(paymentLink).toMatch(/\/checkout\/\d+\/[^/]+\/payment\?session_identifier=[a-f0-9]{64}$/);
    await modal.getByTestId('payment-link-done-button').click();

    await expect(page.getByText('Payment link created')).toBeVisible();
    await expect(page.getByTestId('checkout-pay-by')).toBeVisible();
    await expect(page.getByTestId('checkout-timer')).toBeHidden();
    await expect(page.getByTestId('open-payment-link-modal')).toBeHidden();

    const linkEmail = await mailpit.waitForMessage(buyerEmail, { subjectContains: 'Payment link for your' });
    expect(await mailpit.getLinks(linkEmail.ID)).toContain(paymentLink);

    const payerContext = await browser.newContext({ ignoreHTTPSErrors: true });
    try {
      const payerPage = await payerContext.newPage();
      await payerPage.goto(paymentLink);

      await expect(payerPage.getByText('Ray Registrant has asked you to pay for their order')).toBeVisible();
      await expect(payerPage.getByText('Payment request')).toBeVisible();
      await expect(payerPage.getByRole('heading', { name: 'Pay for this order' })).toBeVisible();
      await expect(payerPage.getByTestId('open-payment-link-modal')).toBeHidden();

      await new CheckoutPage(payerPage).chooseOfflinePayment();

      await expect(payerPage.getByText('Order placed, payment pending')).toBeVisible();
      await expect(payerPage.getByText(OFFLINE_PAYMENT_INSTRUCTIONS)).toBeVisible();
      await expect(payerPage.getByTestId('payment-reference')).toContainText(/O-[A-Z0-9]+/);

      await payerPage.getByLabel('Note for the organizer (optional)').fill('Paid by Acme Ltd, PO 4471');
      await payerPage.getByTestId('report-offline-payment-button').click();
      await expect(payerPage.getByText("The organizer knows you've paid")).toBeVisible();
    } finally {
      await payerContext.close();
    }

    const orders = new OrderPage(authedPage);
    await orders.goto(event.eventId);
    const row = orders.rowByEmail(buyerEmail);
    await expect(row.getByText('Payment reported')).toBeVisible();

    await orders.chooseRowAction(buyerEmail, 'Mark as paid');
    await expect(row.getByText('Completed')).toBeVisible();
  });

  test('on an offline-only event, a buyer places the order and shares the payment details with whoever is paying', async ({ page, browser, authedPage, api, account }) => {
    const event = await createLiveEventWithPaidTicket(api, account.organizerId);
    await enableOfflinePayments(api, event.eventId);
    await api.updateEventSettings(event.eventId, { payment_links_enabled: true });
    const buyerEmail = uniqueEmail('sharebuyer');
    const buyer = { firstName: 'Ray', lastName: 'Registrant', email: buyerEmail };

    const checkout = new CheckoutPage(page);
    await checkout.gotoPublicEvent(event.eventId, event.slug);
    await checkout.setFirstProductQuantity(1);
    await checkout.continueToCheckout();
    await checkout.fillOrderDetails(buyer);
    await checkout.fillFirstAttendee(buyer);
    await checkout.continueToPayment();

    await expect(page.getByTestId('offline-payment-button')).toHaveText('Place order');
    await expect(page.getByTestId('open-payment-link-modal')).toBeHidden();
    await checkout.chooseOfflinePayment();

    await expect(page.getByText('Your order is awaiting payment')).toBeVisible();
    await page.getByTestId('share-payment-details-button').click();
    const modal = page.getByRole('dialog');
    await expect(modal.getByText('Share the payment details')).toBeVisible();
    const shareLink = modal.getByLabel('Payment link');
    await expect(shareLink).toHaveValue(/\/checkout\/\d+\/[^/]+\/summary\?session_identifier=[a-f0-9]{64}$/);
    const paymentDetailsUrl = await shareLink.inputValue();
    await modal.getByTestId('share-payment-details-done-button').click();

    const payerContext = await browser.newContext({ ignoreHTTPSErrors: true });
    try {
      const payerPage = await payerContext.newPage();
      await payerPage.goto(paymentDetailsUrl);

      await expect(payerPage.getByText('Order placed, payment pending')).toBeVisible();
      await expect(payerPage.getByTestId('payment-amount-due')).toBeVisible();
      await expect(payerPage.getByTestId('payment-reference')).toBeVisible();
      await expect(payerPage.getByText(OFFLINE_PAYMENT_INSTRUCTIONS)).toBeVisible();
      await expect(payerPage.getByRole('heading', { name: 'Guests' })).toBeHidden();
      await expect(payerPage.getByTestId('share-payment-details-button')).toBeHidden();

      await payerPage.getByLabel('Note for the organizer (optional)').fill('Paid by Acme Ltd, PO 4471');
      await payerPage.getByTestId('report-offline-payment-button').click();
      await expect(payerPage.getByText("The organizer knows you've paid")).toBeVisible();
    } finally {
      await payerContext.close();
    }

    const orders = new OrderPage(authedPage);
    await orders.goto(event.eventId);
    await expect(orders.rowByEmail(buyerEmail).getByText('Payment reported')).toBeVisible();
  });

  test('a buyer paying offline tells the organizer they have paid', async ({ page, authedPage, api, account, publicApi }) => {
    const event = await createLiveEventWithPaidTicket(api, account.organizerId);
    const order = await createAwaitingOfflineOrder(api, publicApi, event, { buyerEmail: uniqueEmail('reporter') });

    await page.goto(`/checkout/${event.eventId}/${order.orderShortId}/summary`);
    await expect(page.getByText('Your order is awaiting payment')).toBeVisible();
    const placedOrder = (await api.listOrders(event.eventId)).find((candidate) => candidate.short_id === order.orderShortId);
    const reference = page.getByTestId('payment-reference');
    await expect(reference).toContainText(placedOrder!.public_id);
    await expect(reference.getByRole('button', { name: 'Copy payment reference' })).toBeVisible();
    await page.getByTestId('report-offline-payment-button').click();
    await expect(page.getByText("The organizer knows you've paid")).toBeVisible();

    await page.reload();
    await expect(page.getByText("The organizer knows you've paid")).toBeVisible();
    await expect(page.getByTestId('report-offline-payment-button')).toBeHidden();

    const orders = new OrderPage(authedPage);
    await orders.goto(event.eventId);
    await expect(orders.rowByEmail(order.buyerEmail).getByText('Payment reported')).toBeVisible();

    await orders.chooseRowAction(order.buyerEmail, 'Manage order');
    const drawer = orders.detailsDrawer();
    await expect(drawer.getByText('Payment reported by buyer')).toBeVisible();
    await expect(drawer.getByText('Payment reported', { exact: true })).toBeVisible();

    await orders.chooseDrawerAction('Mark as paid');
    await expect(drawer.getByText('Completed', { exact: true })).toBeVisible();
  });

  test('buyers cannot report payments or create links when the organizer has not allowed it', async ({ page, api, account, publicApi }) => {
    const event = await createLiveEventWithPaidTicket(api, account.organizerId);
    const order = await createAwaitingOfflineOrder(api, publicApi, event, { buyerEmail: uniqueEmail('noreport') });
    await api.updateEventSettings(event.eventId, {
      offline_payment_reporting_enabled: false,
      offline_payment_order_reference_enabled: false,
      payment_links_enabled: false,
    });

    await page.goto(`/checkout/${event.eventId}/${order.orderShortId}/summary`);
    await expect(page.getByText(OFFLINE_PAYMENT_INSTRUCTIONS)).toBeVisible();
    await expect(page.getByTestId('payment-reference')).toBeHidden();
    await expect(page.getByTestId('share-payment-details-button')).toBeHidden();
    await expect(page.getByTestId('report-offline-payment-button')).toBeHidden();

    const checkout = new CheckoutPage(page);
    await checkout.gotoPublicEvent(event.eventId, event.slug);
    await checkout.setFirstProductQuantity(1);
    await checkout.continueToCheckout();
    const buyer = { firstName: 'No', lastName: 'Link', email: uniqueEmail('nolink') };
    await checkout.fillOrderDetails(buyer);
    await checkout.fillFirstAttendee(buyer);
    await checkout.continueToPayment();
    await expect(page.getByTestId('offline-payment-button')).toBeVisible();
    await expect(page.getByTestId('open-payment-link-modal')).toBeHidden();
  });
});
