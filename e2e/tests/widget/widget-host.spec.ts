import { test, expect } from '../../fixtures';
import { CheckoutPage, setWidgetQuantity } from '../../pages/checkout.page';
import {
  EMBED_HOST_URL,
  checkoutModal,
  checkoutModalDialog,
  openEmbedHost,
  openModalCheckout,
  resolveCheckoutFrame,
  resolveWidgetFrame,
  widgetIframe,
  widgetMarkup,
} from '../../pages/embedded-widget';
import { createFreshOrganizer, createLiveEventWithFreeTicket, createReservedOrder } from '../../api/factory';
import { uniqueCode, uniqueEmail } from '../../utils/unique';

type HostWindow = Window & { hieventsLog: { name: string; detail: Record<string, unknown> }[] };

const recordHostEvents = (page: import('@playwright/test').Page) => page.evaluate(() => {
  (window as unknown as HostWindow).hieventsLog = [];
  ['hievents:checkout-opened', 'hievents:checkout-step', 'hievents:checkout-closed', 'hievents:order-completed'].forEach((name) => {
    window.addEventListener(name, (event) => {
      (window as unknown as HostWindow).hieventsLog.push({ name, detail: (event as CustomEvent).detail });
    });
  });
});

const hostEvents = (page: import('@playwright/test').Page) =>
  page.evaluate(() => (window as unknown as HostWindow).hieventsLog.map((entry) => entry.name));

test.describe('widget on a host page', () => {
  test('reloading the host page reopens a checkout that is still in progress', async ({ page, api, account, baseURL }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    const widget = await resolveWidgetFrameAfterOpen(page, baseURL!, event.eventId);
    await setWidgetQuantity(widget, 1);
    const checkoutFrame = await openModalCheckout(page, widget);
    const orderPath = new URL(checkoutFrame.url()).pathname;
    await expect.poll(() => page.evaluate(() => window.sessionStorage.getItem('hievents.checkout.resume'))).not.toBeNull();

    await page.reload();

    await expect(checkoutModalDialog(page)).toBeVisible();
    const resumed = await resolveCheckoutFrame(page);
    await resumed.waitForURL(/\/details/);
    expect(new URL(resumed.url()).pathname).toBe(orderPath);
  });

  test('returning from a payment provider reopens the order in the pop-up and cleans the host URL', async ({ page, api, account, publicApi, baseURL }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    const order = await createReservedOrder(publicApi, event);

    await openEmbedHost(
      page,
      baseURL!,
      widgetMarkup({ id: String(event.eventId) }),
      `?hievents_event=${event.eventId}&hievents_order=${order.orderShortId}&hievents_session=${order.sessionId}`,
    );

    const checkoutFrame = await resolveCheckoutFrame(page);
    await expect.poll(() => checkoutFrame.url()).toContain(`/checkout/${event.eventId}/${order.orderShortId}/`);
    await expect.poll(() => page.url()).toBe(EMBED_HOST_URL);
  });

  test('the embed remounts when its attributes change, survives a second script and cleans up when removed', async ({ page, api, account, baseURL }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    const categories = await api.listProductCategories(event.eventId);
    const second = await api.createProduct(event.eventId, {
      title: 'Second Ticket',
      product_type: 'TICKET',
      type: 'FREE',
      product_category_id: categories[0].id,
      prices: [{ price: 0 }],
    });

    await openEmbedHost(page, baseURL!, `<hievents-widget event-id="${event.eventId}" products="${event.productId}"></hievents-widget>`);
    let widget = await resolveWidgetFrame(page);
    await expect(widget.getByRole('heading', { name: event.productTitle })).toBeVisible();
    await expect(widgetIframe(page)).toHaveAttribute('loading', 'lazy');

    await page.evaluate((productId) => document.querySelector('hievents-widget')!.setAttribute('products', String(productId)), second.id);
    await expect(widgetIframe(page)).toHaveCount(1);
    widget = await resolveWidgetFrame(page);
    await expect(widget.getByRole('heading', { name: 'Second Ticket' })).toBeVisible();
    await expect(widget.getByRole('heading', { name: event.productTitle })).toHaveCount(0);

    await page.evaluate((src) => new Promise((resolve, reject) => {
      const script = document.createElement('script');
      script.src = src;
      script.onload = resolve;
      script.onerror = reject;
      document.head.appendChild(script);
    }), `${baseURL}/widget.js`);
    await page.evaluate(() => new Promise(requestAnimationFrame));
    await expect(widgetIframe(page)).toHaveCount(1);

    await page.evaluate(() => document.querySelector('hievents-widget')!.remove());
    await expect(widgetIframe(page)).toHaveCount(0);
  });

  test('a widget div added after the page loads is picked up', async ({ page, api, account, baseURL }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);

    await openEmbedHost(page, baseURL!, '<div id="later"></div>');
    await page.waitForFunction(() => 'HiEventsWidget' in window);
    await page.evaluate((eventId) => {
      document.getElementById('later')!.innerHTML = `<section><div class="hievents-widget" data-hievents-id="${eventId}"></div></section>`;
    }, event.eventId);

    const widget = await resolveWidgetFrame(page);
    await expect(widget.getByRole('heading', { name: event.productTitle })).toBeVisible();
  });

  test('pop-ups close with Escape and the backdrop, and the host hears each checkout step', async ({ page, api, account, baseURL }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    await openEmbedHost(page, baseURL!, widgetMarkup({ id: String(event.eventId), 'widget-type': 'button', 'button-text': 'Book now' }));
    await recordHostEvents(page);

    const bookNow = page.getByRole('button', { name: 'Book now' });
    await bookNow.click();
    await expect(checkoutModal(page)).toHaveCount(1);
    await page.keyboard.press('Escape');
    await expect(checkoutModal(page)).toHaveCount(0);
    await expect(bookNow).toBeFocused();

    await bookNow.click();
    await resolveWidgetFrame(page);
    await checkoutModal(page).click({ position: { x: 4, y: 4 } });
    await expect(checkoutModal(page)).toHaveCount(0);

    await page.getByRole('button', { name: 'Book now' }).click();
    const widget = await resolveWidgetFrame(page);
    await expect(widget.getByTestId('widget-event-header')).toContainText(event.title);
    await setWidgetQuantity(widget, 1);
    await widget.getByTestId('checkout-continue-button').click();
    const checkoutFrame = await resolveCheckoutFrame(page);
    await checkoutFrame.waitForURL(/\/details/);
    await expect.poll(() => hostEvents(page)).toEqual(expect.arrayContaining(['hievents:checkout-opened', 'hievents:checkout-step']));
    expect(await hostEvents(page)).not.toContain('hievents:checkout-closed');

    await page.getByRole('button', { name: 'Close checkout' }).click();
    await checkoutFrame.getByRole('button', { name: 'Yes, cancel my order' }).click();
    await expect(checkoutModal(page)).toHaveCount(0);
    await expect.poll(() => hostEvents(page)).toContain('hievents:checkout-closed');
  });

  test('on a phone the ticket pop-up is a bottom sheet sized to its content', async ({ page, api, account, baseURL }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    await openEmbedHost(page, baseURL!, widgetMarkup({ id: String(event.eventId), 'widget-type': 'button', 'button-text': 'Book now' }));

    await page.getByRole('button', { name: 'Book now' }).click();
    const widget = await resolveWidgetFrame(page);
    await expect(widget.getByRole('heading', { name: event.productTitle })).toBeVisible();

    const dialog = page.getByRole('dialog', { name: 'Book now' });
    await expect.poll(async () => {
      const box = await dialog.boundingBox();
      return box ? Math.round(box.y + box.height) : 0;
    }).toBe(844);
    expect((await dialog.boundingBox())!.height).toBeLessThan(800);
    expect((await dialog.boundingBox())!.width).toBe(390);
  });

  test('host page affiliate links and prefilled details reach the order', async ({ page, api, account, baseURL }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    const affiliateCode = uniqueCode('HOSTAFF');
    await api.createAffiliate(event.eventId, { name: 'Partner site', code: affiliateCode });
    const email = uniqueEmail('prefill');

    await openEmbedHost(
      page,
      baseURL!,
      widgetMarkup({ id: String(event.eventId), 'prefill-email': email, 'prefill-first-name': 'Pre', 'prefill-last-name': 'Filled' }),
      `?aff=${affiliateCode}&utm_source=newsletter`,
    );
    const widget = await resolveWidgetFrame(page);
    await setWidgetQuantity(widget, 1);
    const checkoutFrame = await openModalCheckout(page, widget);
    expect(new URL(checkoutFrame.url()).searchParams.get('utm_source')).toBe('newsletter');

    const checkout = new CheckoutPage(page, checkoutFrame);
    await expect(checkoutFrame.getByLabel(/^Email Address/).first()).toHaveValue(email);
    await checkout.fillFirstAttendee({ firstName: 'Pre', lastName: 'Filled', email });
    await checkout.completeFreeOrder();
    await expect(checkoutFrame.getByText(`You're going to ${event.title}`)).toBeVisible();

    await expect.poll(async () => (await api.listAffiliates(event.eventId)).find((affiliate) => affiliate.code === affiliateCode)?.total_sales)
      .toBe(1);
  });

  test('widget routes render their content on the server', { tag: '@smoke' }, async ({ request, api }) => {
    const organizer = await createFreshOrganizer(api);
    const event = await createLiveEventWithFreeTicket(api, organizer.id);

    const html = await (await request.get(`/widget/${event.eventId}?Sections=header,tickets`)).text();
    expect(html).toContain(event.title);
    expect(html).toContain(event.productTitle);

    const listing = await (await request.get(`/widget/organizer/${organizer.id}`)).text();
    expect(listing).toContain(event.title);
  });
});

async function resolveWidgetFrameAfterOpen(page: import('@playwright/test').Page, baseURL: string, eventId: number) {
  await openEmbedHost(page, baseURL, widgetMarkup({ id: String(eventId) }));
  return resolveWidgetFrame(page);
}
