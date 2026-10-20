import { test, expect } from '../../fixtures';
import { CheckoutPage, setWidgetQuantity } from '../../pages/checkout.page';
import {
  checkoutModalDialog,
  openEmbedHost,
  resolveCheckoutFrame,
  resolveWidgetFrame,
  widgetMarkup,
} from '../../pages/embedded-widget';
import {
  createFreshOrganizer,
  createLiveEventWithFreeTicket,
  createLiveEventWithPaidTicket,
  createPastEventWithCoverImage,
  enableOfflinePayments,
} from '../../api/factory';
import { uniqueCode, uniqueEmail, uniqueName } from '../../utils/unique';
import { WidgetApi } from './widget-api';

type HostOrdersWindow = Window & { hieventsOrders: unknown[] };

const recordHostOrders = (page: import('@playwright/test').Page) => page.evaluate(() => {
  const log: unknown[] = [];
  (window as unknown as HostOrdersWindow).hieventsOrders = log;
  window.addEventListener('hievents:order-completed', (event) => log.push((event as CustomEvent).detail));
});

const hostOrders = (page: import('@playwright/test').Page) =>
  page.evaluate(() => (window as unknown as HostOrdersWindow).hieventsOrders);

test.describe('widget modes', () => {
  test('the ticket widget shows the chosen sections and only the selected tickets', async ({ page, api, account, baseURL }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    const categories = await api.listProductCategories(event.eventId);
    await api.createProduct(event.eventId, {
      title: 'Backstage Pass',
      product_type: 'TICKET',
      type: 'FREE',
      product_category_id: categories[0].id,
      prices: [{ price: 0 }],
    });

    await openEmbedHost(page, baseURL!, widgetMarkup({
      id: String(event.eventId),
      sections: 'header,tickets',
      products: String(event.productId),
      'hide-promo-code': 'true',
    }));
    const widget = await resolveWidgetFrame(page);

    await expect(widget.getByTestId('widget-event-header')).toContainText(event.title);
    await expect(widget.getByRole('heading', { name: event.productTitle })).toBeVisible();
    await expect(widget.getByRole('heading', { name: 'Backstage Pass' })).toHaveCount(0);
    await expect(widget.getByText('Have a promo code?')).toHaveCount(0);
  });

  test('a promo code in the host page link is applied in the widget', async ({ page, api, account, baseURL }) => {
    const event = await createLiveEventWithPaidTicket(api, account.organizerId, 20);
    await api.updateEventSettings(event.eventId, { pass_platform_fee_to_buyer: false });
    const code = uniqueName('HOSTPROMO').replace(/[^A-Za-z0-9]/g, '').slice(0, 20).toUpperCase();
    await api.createPromoCode(event.eventId, { code, discount_type: 'PERCENTAGE', discount: 10, applicable_product_ids: [] });

    await openEmbedHost(page, baseURL!, widgetMarkup({ id: String(event.eventId) }), `?promo_code=${code}`);
    const widget = await resolveWidgetFrame(page);

    await expect(widget.getByText(code, { exact: false }).first()).toBeVisible();
    await expect(widget.locator('.hi-product-row').filter({ hasText: event.productTitle }).locator('.hi-price-strike')).toContainText('20.00');
  });

  test('the ticket button opens a pop-up, checkout continues in it and the host page hears about the order', { tag: '@smoke' }, async ({ page, api, account, baseURL }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    const buyer = { firstName: 'Button', lastName: 'Buyer', email: uniqueEmail('button-buyer') };

    await openEmbedHost(page, baseURL!, widgetMarkup({
      id: String(event.eventId),
      'widget-type': 'button',
      'button-text': 'Book now',
    }));
    await page.evaluate(() => {
      const log: unknown[] = [];
      (window as unknown as { hieventsOrders: unknown[] }).hieventsOrders = log;
      window.addEventListener('hievents:order-completed', (event) => log.push((event as CustomEvent).detail));
    });

    await page.getByRole('button', { name: 'Book now' }).click();
    const widget = await resolveWidgetFrame(page);
    await setWidgetQuantity(widget, 1);
    await widget.getByTestId('checkout-continue-button').click();

    await expect(checkoutModalDialog(page)).toBeVisible();
    const checkoutFrame = await resolveCheckoutFrame(page);
    await checkoutFrame.waitForURL(/\/checkout\/\d+\/[^/]+\/details/);
    const checkout = new CheckoutPage(page, checkoutFrame);
    await checkout.fillOrderDetails(buyer);
    await checkout.fillFirstAttendee(buyer);
    await checkout.completeFreeOrder();

    await expect(checkoutFrame.getByText(`You're going to ${event.title}`)).toBeVisible();
    await expect.poll(() => page.evaluate(() => (window as unknown as { hieventsOrders: unknown[] }).hieventsOrders))
      .toEqual([expect.objectContaining({ eventId: String(event.eventId), quantity: 1, status: 'COMPLETED' })]);
  });

  test('a paid order through the ticket button tells the host page its total and currency', async ({ page, api, account, baseURL }) => {
    const event = await createLiveEventWithPaidTicket(api, account.organizerId, 20);
    await api.updateEventSettings(event.eventId, { pass_platform_fee_to_buyer: false });
    await enableOfflinePayments(api, event.eventId);
    const buyer = { firstName: 'Paid', lastName: 'Button', email: uniqueEmail('paid-button') };

    await openEmbedHost(page, baseURL!, widgetMarkup({ id: String(event.eventId), 'widget-type': 'button', 'button-text': 'Book now' }));
    await recordHostOrders(page);

    await page.getByRole('button', { name: 'Book now' }).click();
    const widget = await resolveWidgetFrame(page);
    await setWidgetQuantity(widget, 1);
    await widget.getByTestId('checkout-continue-button').click();
    const checkoutFrame = await resolveCheckoutFrame(page);
    await checkoutFrame.waitForURL(/\/checkout\/\d+\/[^/]+\/details/);
    const checkout = new CheckoutPage(page, checkoutFrame);
    await checkout.fillOrderDetails(buyer);
    await checkout.fillFirstAttendee(buyer);
    await checkout.continueToPayment();
    await checkout.chooseOfflinePayment();

    await expect.poll(() => hostOrders(page)).toContainEqual(expect.objectContaining({
      eventId: String(event.eventId),
      currency: 'USD',
      total: 20,
      quantity: 1,
      status: 'AWAITING_OFFLINE_PAYMENT',
    }));
    const [order] = await api.listOrders(event.eventId);
    expect(Number(order.total_gross)).toBe(20);
  });

  test('a saved events widget credits the host page affiliate and counts the order it sold', async ({ page, api, account, baseURL, playwright }) => {
    const organizer = await createFreshOrganizer(api);
    const event = await createLiveEventWithFreeTicket(api, organizer.id);
    const affiliateCode = uniqueCode('EVENTSAFF');
    await api.createAffiliate(event.eventId, { name: 'Events partner', code: affiliateCode });
    const widgets = await WidgetApi.create(playwright, account.token);
    const saved = await widgets.createOrganizerWidget(organizer.id, { name: 'Listing', type: 'EVENTS', settings: { layout: 'list' } });
    const buyer = { firstName: 'Listing', lastName: 'Buyer', email: uniqueEmail('events-widget') };

    await openEmbedHost(
      page,
      baseURL!,
      `<div class="hievents-widget" data-hievents-organizer-id="${organizer.id}" data-hievents-widget-type="events" data-hievents-widget="${saved.short_id}"></div>`,
      `?aff=${affiliateCode}`,
    );
    const list = await resolveWidgetFrame(page, 'Hi.Events Events');
    await list.getByTestId('widget-event-item').filter({ hasText: event.title }).click();
    const widget = await resolveWidgetFrame(page);
    await setWidgetQuantity(widget, 1);
    await widget.getByTestId('checkout-continue-button').click();
    const checkoutFrame = await resolveCheckoutFrame(page);
    await checkoutFrame.waitForURL(/\/checkout\/\d+\/[^/]+\/details/);
    const checkout = new CheckoutPage(page, checkoutFrame);
    await checkout.fillOrderDetails(buyer);
    await checkout.fillFirstAttendee(buyer);
    await checkout.completeFreeOrder();
    await expect(checkoutFrame.getByText(`You're going to ${event.title}`)).toBeVisible();

    await expect.poll(async () => (await api.listAffiliates(event.eventId)).find((affiliate) => affiliate.code === affiliateCode)?.total_sales)
      .toBe(1);
    await expect.poll(async () => (await widgets.listOrganizerWidgets(organizer.id)).find((candidate) => candidate.id === saved.id)?.stats.orders)
      .toBe(1);
    await widgets.dispose();
  });

  test('an events widget that opens event pages passes on the host page affiliate, promo code and campaign', async ({ page, api, baseURL }) => {
    const organizer = await createFreshOrganizer(api);
    const event = await createLiveEventWithFreeTicket(api, organizer.id);
    const affiliateCode = uniqueCode('PAGEAFF');
    await api.createAffiliate(event.eventId, { name: 'Page partner', code: affiliateCode });
    const promoCode = uniqueCode('PAGEPROMO');
    await api.createPromoCode(event.eventId, { code: promoCode, discount_type: 'PERCENTAGE', discount: 10, applicable_product_ids: [] });
    const buyer = { firstName: 'Page', lastName: 'Buyer', email: uniqueEmail('events-page') };

    await openEmbedHost(
      page,
      baseURL!,
      `<div class="hievents-widget" data-hievents-organizer-id="${organizer.id}" data-hievents-widget-type="events" data-hievents-open-in="page"></div>`,
      `?aff=${affiliateCode}&promo_code=${promoCode}&utm_source=newsletter`,
    );
    const list = await resolveWidgetFrame(page, 'Hi.Events Events');
    const [eventPage] = await Promise.all([
      page.context().waitForEvent('page'),
      list.getByTestId('widget-event-item').filter({ hasText: event.title }).click(),
    ]);
    await eventPage.waitForLoadState('networkidle');

    const opened = new URL(eventPage.url());
    expect(opened.pathname).toContain(`/event/${event.eventId}/`);
    expect(opened.searchParams.get('aff')).toBe(affiliateCode);
    expect(opened.searchParams.get('promo_code')).toBe(promoCode);
    expect(opened.searchParams.get('utm_source')).toBe('newsletter');

    const checkout = new CheckoutPage(eventPage);
    await checkout.setFirstProductQuantity(1);
    await checkout.continueToCheckout();
    await checkout.fillOrderDetails(buyer);
    await checkout.fillFirstAttendee(buyer);
    await checkout.completeFreeOrder();

    await expect.poll(async () => (await api.listAffiliates(event.eventId)).find((affiliate) => affiliate.code === affiliateCode)?.total_sales)
      .toBe(1);
  });

  test('the events widget lists an organizer’s events and opens tickets in a pop-up', async ({ page, api, baseURL }) => {
    const organizer = await createFreshOrganizer(api);
    const event = await createLiveEventWithFreeTicket(api, organizer.id);

    await openEmbedHost(page, baseURL!, `<div class="hievents-widget" data-hievents-organizer-id="${organizer.id}" data-hievents-widget-type="events"></div>`);
    const list = await resolveWidgetFrame(page, 'Hi.Events Events');

    await list.getByTestId('widget-event-item').filter({ hasText: event.title }).click();
    const widget = await resolveWidgetFrame(page);
    await expect(widget.getByRole('heading', { name: event.productTitle })).toBeVisible();
  });

  test('the lineup widget shows the people on the bill', async ({ page, api, account, baseURL }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    const headliner = uniqueName('Velvet Harbour');
    await api.importLineup(event.eventId, { people: [{ name: headliner, billing_tier: 'HEADLINER' }], slots: [] });

    await openEmbedHost(page, baseURL!, widgetMarkup({ id: String(event.eventId), 'widget-type': 'lineup' }));
    const lineup = await resolveWidgetFrame(page, 'Hi.Events Lineup');

    await expect(lineup.getByTestId('event-lineup-section')).toContainText(headliner);
  });

  test('a widget added after the page loads is picked up', async ({ page, api, account, baseURL }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);

    await openEmbedHost(page, baseURL!, '<div id="later"></div>');
    await page.waitForFunction(() => 'HiEventsWidget' in window);
    await page.evaluate((eventId) => {
      document.getElementById('later')!.innerHTML = `<hievents-widget event-id="${eventId}"></hievents-widget>`;
    }, event.eventId);

    const widget = await resolveWidgetFrame(page);
    await expect(widget.getByRole('heading', { name: event.productTitle })).toBeVisible();
  });

  test('sections follow the chosen order, and categories and their titles can be filtered', async ({ page, api, account, baseURL }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    const extras = await api.createProductCategory(event.eventId, { name: 'Extras', is_hidden: false });
    await api.createProduct(event.eventId, {
      title: 'Parking Pass',
      product_type: 'GENERAL',
      type: 'FREE',
      product_category_id: extras.id,
      prices: [{ price: 0 }],
    });
    await api.updateEventSettings(event.eventId, {
      homepage_sections: [{ id: 'faq', type: 'FAQ', enabled: true, heading: 'Good to know', faqs: [{ question: 'Is there parking?', answer: '<p>Yes.</p>' }] }],
    });

    await openEmbedHost(page, baseURL!, widgetMarkup({
      id: String(event.eventId),
      sections: 'custom,header,tickets',
      categories: String(extras.id),
      'hide-category-titles': 'true',
    }));
    const widget = await resolveWidgetFrame(page);

    await expect(widget.getByRole('heading', { name: 'Parking Pass' })).toBeVisible();
    await expect(widget.getByRole('heading', { name: event.productTitle })).toHaveCount(0);
    await expect(widget.getByRole('heading', { name: 'Extras' })).toHaveCount(0);

    const faqBox = await widget.getByText('Good to know').boundingBox();
    const headerBox = await widget.getByTestId('widget-event-header').boundingBox();
    const ticketsBox = await widget.getByRole('heading', { name: 'Parking Pass' }).boundingBox();
    expect(faqBox!.y).toBeLessThan(headerBox!.y);
    expect(headerBox!.y).toBeLessThan(ticketsBox!.y);
  });

  test('a language attribute translates the widget, and old snippets keep following the visitor', async ({ browser, api, account, baseURL }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    const context = await browser.newContext({ locale: 'de-DE', ignoreHTTPSErrors: true });
    const page = await context.newPage();

    await openEmbedHost(page, baseURL!, widgetMarkup({ id: String(event.eventId), locale: 'en', 'widget-version': '1.0' }));
    let widget = await resolveWidgetFrame(page);
    await expect(widget.getByTestId('checkout-continue-button')).toContainText('Weitermachen');

    await page.unrouteAll();
    await openEmbedHost(page, baseURL!, widgetMarkup({ id: String(event.eventId), locale: 'fr' }));
    widget = await resolveWidgetFrame(page);
    await expect(widget.getByTestId('checkout-continue-button')).toContainText('Continuer');
    await setWidgetQuantity(widget, 1);
    await widget.getByTestId('checkout-continue-button').click();
    const checkoutFrame = await resolveCheckoutFrame(page);
    await expect(checkoutFrame.getByRole('heading', { name: 'Vos détails' })).toBeVisible();

    await context.close();
  });

  test('the events widget shows a grid, more events on demand, a past tab and can open event pages', async ({ page, api, baseURL }) => {
    const organizer = await createFreshOrganizer(api);
    const first = await createLiveEventWithFreeTicket(api, organizer.id);
    const second = await createLiveEventWithFreeTicket(api, organizer.id);
    const past = await createPastEventWithCoverImage(api, organizer.id);

    await openEmbedHost(page, baseURL!, `<div class="hievents-widget" data-hievents-organizer-id="${organizer.id}" data-hievents-widget-type="events" data-hievents-layout="grid" data-hievents-limit="1" data-hievents-show-past="true" data-hievents-open-in="page"></div>`);
    const list = await resolveWidgetFrame(page, 'Hi.Events Events');

    await expect(list.locator('ul[data-layout="grid"]')).toBeVisible();
    await expect(list.getByTestId('widget-event-item')).toHaveCount(1);
    await list.getByRole('button', { name: 'Show more events' }).click();
    await expect(list.getByTestId('widget-event-item')).toHaveCount(2);
    await expect(list.getByText(first.title)).toBeVisible();
    await expect(list.getByText(second.title)).toBeVisible();

    await list.getByText('Past', { exact: true }).click();
    await expect(list.getByText(past.title)).toBeVisible();

    const [eventPage] = await Promise.all([
      page.context().waitForEvent('page'),
      list.getByTestId('widget-event-item').filter({ hasText: past.title }).click(),
    ]);
    await eventPage.waitForLoadState();
    expect(eventPage.url()).toContain(`/event/${past.eventId}/`);
  });

  test('the schedule widget opens sessions where they were clicked, and an empty lineup says it is coming soon', async ({ page, api, account, baseURL }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    const slots = Array.from({ length: 12 }, (_, index) => ({
      starts_at: `2030-06-14 ${String(12 + Math.floor(index / 2)).padStart(2, '0')}:${index % 2 ? '30' : '00'}:00`,
      ends_at: `2030-06-14 ${String(12 + Math.floor(index / 2)).padStart(2, '0')}:${index % 2 ? '55' : '25'}:00`,
      stage: 'Main Stage',
      people: [`Act ${index + 1}`],
    }));
    await api.importLineup(event.eventId, {
      people: slots.map((slot) => ({ name: slot.people[0], billing_tier: 'STANDARD' })),
      slots,
    });
    const empty = await createLiveEventWithFreeTicket(api, account.organizerId);

    await openEmbedHost(page, baseURL!, widgetMarkup({ id: String(event.eventId), 'widget-type': 'schedule' }));
    const schedule = await resolveWidgetFrame(page, 'Hi.Events Schedule');
    const lastSession = schedule.getByTestId('event-schedule-item').last().getByRole('button', { name: 'Act 12' });
    await lastSession.scrollIntoViewIfNeeded();
    await lastSession.click();

    const dialog = schedule.getByTestId('session-modal');
    await expect(dialog).toBeVisible();
    const dialogBox = (await dialog.boundingBox())!;
    const hostViewport = page.viewportSize()!;
    expect(dialogBox.height).toBeGreaterThan(60);
    expect(dialogBox.y).toBeGreaterThanOrEqual(0);
    expect(dialogBox.y + 60).toBeLessThan(hostViewport.height);

    await page.unrouteAll();
    await openEmbedHost(page, baseURL!, widgetMarkup({ id: String(empty.eventId), 'widget-type': 'lineup' }));
    const lineup = await resolveWidgetFrame(page, 'Hi.Events Lineup');
    await expect(lineup.getByText('Coming soon')).toBeVisible();
  });

  test('a saved widget applies its language and settings without changing the embed code', async ({ page, api, account, baseURL }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    const widget = await api.createEventWidget(event.eventId, {
      name: 'French site',
      type: 'TICKETS',
      settings: { locale: 'fr', continue_button_text: 'Réserver', hide_promo_code: true },
    });

    await openEmbedHost(page, baseURL!, widgetMarkup({ id: String(event.eventId), widget: widget.short_id }));
    const frame = await resolveWidgetFrame(page);

    await expect(frame.getByTestId('checkout-continue-button')).toContainText('Réserver');
    await expect(frame.getByText('Have a promo code?')).toHaveCount(0);
    await expect(frame.getByText('Propulsé par')).toBeVisible();
  });
});

