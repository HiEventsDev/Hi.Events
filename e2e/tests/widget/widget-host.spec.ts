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
import { waitForHydration } from '../../utils/hydration';

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

    await page.evaluate((productId) => {
      const element = document.querySelector<HTMLElement>('hievents-widget')!;
      element.style.display = 'none';
      element.setAttribute('products', String(productId));
    }, event.productId);
    await expect(widgetIframe(page)).toHaveCount(1);
    await expect(widgetIframe(page)).toHaveAttribute('src', new RegExp(`Products=${event.productId}(&|$)`));
    expect(await page.evaluate(() => document.querySelector<HTMLElement>('hievents-widget')!.style.display)).toBe('none');
    await page.evaluate(() => { document.querySelector<HTMLElement>('hievents-widget')!.style.display = ''; });

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

  test('switching the custom element from a button to tickets shows the ticket widget', async ({ page, api, account, baseURL }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    await openEmbedHost(page, baseURL!, `<hievents-widget event-id="${event.eventId}" widget-type="button" button-text="Book now"></hievents-widget>`);
    await expect(page.getByRole('button', { name: 'Book now' })).toBeVisible();

    await page.evaluate(() => document.querySelector('hievents-widget')!.removeAttribute('widget-type'));

    await expect(page.getByRole('button', { name: 'Book now' })).toHaveCount(0);
    const widget = await resolveWidgetFrame(page);
    await expect(widget.getByRole('heading', { name: event.productTitle })).toBeVisible();
    await expect(widgetIframe(page)).toBeVisible();
    expect((await widgetIframe(page).boundingBox())!.height).toBeGreaterThan(100);
  });

  test('the button keeps its colours on a host page that blocks inline styles', async ({ page, api, account, baseURL }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    const blockedStyles: string[] = [];
    page.on('console', (message) => {
      if (message.text().includes('style-src')) blockedStyles.push(message.text());
    });

    await openStrictCspHost(page, baseURL!, widgetMarkup({
      id: String(event.eventId),
      'widget-type': 'button',
      'button-text': 'Book now',
      'secondary-color': '#0f766e',
    }));

    const button = page.getByRole('button', { name: 'Book now' });
    await expect(button).toBeVisible();
    await expect(button).toHaveCSS('background-color', 'rgb(15, 118, 110)');
    await expect(button).toHaveCSS('min-height', '44px');
    expect(blockedStyles).toEqual([]);
  });

  test('with autoresize off the widget fills the height the host gives it, and host iframe rules still win', async ({ page, api, account, baseURL }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    const fixedHeightWidget = `<style>.booking { height: 500px; }</style>${widgetMarkup({ id: String(event.eventId), autoresize: 'false' }).replace('class="hievents-widget"', 'class="hievents-widget booking"')}`;

    await openEmbedHost(page, baseURL!, fixedHeightWidget);
    await expect(widgetIframe(page)).toBeVisible();
    expect(Math.round((await widgetIframe(page).boundingBox())!.height)).toBe(500);

    await page.unrouteAll();
    await openEmbedHost(page, baseURL!, `<style>iframe { height: 300px; }</style>${fixedHeightWidget}`);
    await expect(widgetIframe(page)).toBeVisible();
    expect(Math.round((await widgetIframe(page).boundingBox())!.height)).toBe(300);
  });

  test('a missing event or organizer shows its message at once, without hydration errors', async ({ page }) => {
    const pageErrors: string[] = [];
    page.on('pageerror', (error) => pageErrors.push(error.message));

    await page.goto('/widget/999999999');
    await waitForHydration(page, 'main.hi-widget-tokens');
    await expect(page.getByText('This event is not available')).toBeVisible();
    await expect(page.locator('.hi-widget-loading')).toHaveCount(0);
    expect(pageErrors).toEqual([]);

    await page.goto('/widget/organizer/999999999');
    await waitForHydration(page, 'main.hi-widget-tokens');
    await expect(page.getByText('These events are not available')).toBeVisible({ timeout: 2_000 });
    await expect(page.locator('.hi-widget-loading')).toHaveCount(0, { timeout: 2_000 });

    const lineupRequests: string[] = [];
    page.on('request', (request) => {
      if (request.url().includes('/events/999999999/lineup')) {
        lineupRequests.push(request.url());
      }
    });
    for (const path of ['/widget/999999999/lineup', '/widget/999999999/schedule']) {
      await page.goto(path);
      await waitForHydration(page, 'main.hi-widget-tokens');
      await expect(page.getByText('This event is not available')).toBeVisible();
      await expect(page.locator('.hi-widget-loading')).toHaveCount(0);
    }
    expect(lineupRequests).toEqual([]);

    expect(pageErrors).toEqual([]);
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

async function openStrictCspHost(page: import('@playwright/test').Page, baseURL: string, body: string) {
  await page.context().grantPermissions(['local-network-access'], { origin: new URL(EMBED_HOST_URL).origin });
  await page.route(`${EMBED_HOST_URL}**`, (route) => route.fulfill({
    contentType: 'text/html',
    headers: { 'Content-Security-Policy': "style-src 'self'" },
    body: `<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Strict host</title><script async src="${baseURL}/widget.js"></script></head><body>${body}</body></html>`,
  }));
  await page.goto(EMBED_HOST_URL);
}

async function resolveWidgetFrameAfterOpen(page: import('@playwright/test').Page, baseURL: string, eventId: number) {
  await openEmbedHost(page, baseURL, widgetMarkup({ id: String(eventId) }));
  return resolveWidgetFrame(page);
}
