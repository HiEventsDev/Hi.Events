import { test, expect } from '../../fixtures';
import { CheckoutPage, setWidgetQuantity } from '../../pages/checkout.page';
import {
  EMBED_HOST_URL,
  checkoutModal,
  checkoutModalDialog,
  openEmbeddedWidget,
  openModalCheckout,
  resolveCheckoutFrame,
  widgetIframe,
} from '../../pages/embedded-widget';
import { createDraftEvent, createLiveEventWithFreeTicket } from '../../api/factory';
import {
  arrangeKitchenSinkEvent,
  runKitchenSinkCheckout,
  type KitchenSinkTotals,
} from '../checkout/kitchen-sink.shared';
import { uniqueEmail } from '../../utils/unique';

const KITCHEN_SINK_TOTALS: KitchenSinkTotals = {
  standardBase: '$25.00',
  standardInclusive: '$30.25',
  subtotal: '$127.50',
  fees: '$5.00',
  taxes: '$4.00',
  total: '$136.50',
};

test.describe('embedded widget', () => {
  test('widget.js boots the widget in an iframe, applies embed attributes, and auto-resizes', async ({ page, api, account, baseURL }) => {
    const event = await createDraftEvent(api, account.organizerId);
    const categories = await api.listProductCategories(event.eventId);
    await api.createProduct(event.eventId, {
      title: 'Embedded Ticket',
      product_type: 'TICKET',
      type: 'FREE',
      product_category_id: categories[0].id,
      prices: [{ price: 0 }],
      description: '<p>General admission for the embedded event.</p><p>Doors open one hour before the show starts.</p><p>All sales are final and tickets are non-transferable.</p>',
    });
    await api.publishEvent(event.eventId);

    const widget = await openEmbeddedWidget(page, baseURL!, event.eventId, {
      'continue-button-text': 'Grab tickets',
    });

    await expect(widget.getByRole('heading', { name: 'Embedded Ticket' })).toBeVisible();
    await expect(widget.getByTestId('checkout-continue-button')).toContainText('Grab tickets');

    const iframe = widgetIframe(page);
    await expect.poll(async () => (await iframe.boundingBox())?.height ?? 0).toBeGreaterThan(100);
    const collapsedHeight = (await iframe.boundingBox())!.height;

    await widget.getByRole('button', { name: 'Details' }).click();
    await expect(widget.getByText('Doors open one hour before the show starts.')).toBeVisible();
    await expect.poll(async () => (await iframe.boundingBox())!.height).toBeGreaterThan(collapsedHeight);
  });

  test('a buyer completes checkout in the popup modal without leaving the host page', async ({ page, api, account, baseURL }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    const buyer = { firstName: 'Embed', lastName: 'Buyer', email: uniqueEmail('embed-buyer') };

    const widget = await openEmbeddedWidget(page, baseURL!, event.eventId);
    await expect(widget.getByRole('heading', { name: event.productTitle })).toBeVisible();
    await setWidgetQuantity(widget, 1);

    const checkoutFrame = await openModalCheckout(page, widget);
    const dialogBox = (await checkoutModalDialog(page).boundingBox())!;
    expect(dialogBox.width).toBeGreaterThan(700);
    expect(dialogBox.width).toBeLessThan(800);

    const checkout = new CheckoutPage(page, checkoutFrame);
    await expect(checkoutFrame.getByRole('heading', { name: 'Your Details' })).toBeVisible();
    await checkout.fillOrderDetails(buyer);
    await checkout.fillFirstAttendee(buyer);
    await checkout.completeFreeOrder();

    await expect(checkoutFrame.getByText(`You're going to ${event.title}`)).toBeVisible();
    expect(page.url()).toBe(EMBED_HOST_URL);

    await page.getByRole('button', { name: 'Close checkout' }).click();
    await expect(checkoutModal(page)).toHaveCount(0);
    await expect(widget.getByRole('heading', { name: event.productTitle })).toBeVisible();
  });

  test('the checkout modal is full-screen on mobile and closing it asks before abandoning the order', async ({ page, api, account, baseURL }) => {
    await page.setViewportSize({ width: 390, height: 844 });

    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    const widget = await openEmbeddedWidget(page, baseURL!, event.eventId);
    await setWidgetQuantity(widget, 1);

    const checkoutFrame = await openModalCheckout(page, widget);
    await expect(checkoutFrame.getByRole('heading', { name: 'Your Details' })).toBeVisible();

    const dialogBox = (await checkoutModalDialog(page).boundingBox())!;
    expect(dialogBox.width).toBe(390);
    expect(dialogBox.height).toBeGreaterThan(800);

    await page.getByRole('button', { name: 'Close checkout' }).click();
    await expect(checkoutFrame.getByText('Are you sure you want to leave?')).toBeVisible();
    await checkoutFrame.getByRole('button', { name: 'Yes, cancel my order' }).click();
    await expect(checkoutModal(page)).toHaveCount(0);
    await expect(widget.getByRole('heading', { name: event.productTitle })).toBeVisible();
  });

  test('a buyer completes the kitchen-sink checkout inside the embedded widget', async ({ page, api, account, publicApi, mailpit, baseURL }) => {
    test.slow();

    const scenario = await arrangeKitchenSinkEvent(api, account.organizerId);
    const widget = await openEmbeddedWidget(page, baseURL!, scenario.eventId);

    await runKitchenSinkCheckout(page, scenario, { api, publicApi, mailpit }, {
      paymentMode: 'offline',
      attendeeCollection: 'PER_ATTENDEE',
      totals: KITCHEN_SINK_TOTALS,
      surfaces: {
        select: widget,
        resolveCheckout: () => resolveCheckoutFrame(page),
      },
    });
  });
});
