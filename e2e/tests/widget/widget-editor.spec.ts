import { test, expect } from '../../fixtures';
import { CheckoutPage, setWidgetQuantity } from '../../pages/checkout.page';
import { openEmbedHost, openModalCheckout, resolveWidgetFrame, widgetMarkup } from '../../pages/embedded-widget';
import { createLiveEventWithFreeTicket } from '../../api/factory';
import { uniqueEmail, uniqueName } from '../../utils/unique';
import { WidgetApi } from './widget-api';

test.describe('widget editor', () => {
  test('a saved widget keeps its settings wherever it is embedded, its edits go live and its sales show up', async ({ authedPage: page, api, account, baseURL, browser }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);

    await page.goto(`/manage/event/${event.eventId}/widget`);
    const preview = page.frameLocator('iframe[title="Widget preview"]');
    await expect(preview.getByRole('heading', { name: event.productTitle })).toBeVisible();

    await expect(preview.getByText('Have a promo code?')).toBeVisible();
    await page.getByTestId('widget-group-promo').click();
    await page.getByTestId('widget-hide-promo-code-switch').click();
    await expect(preview.getByText('Have a promo code?')).toHaveCount(0);

    await page.getByTestId('widget-save-button').click();
    await page.getByRole('dialog', { name: 'Save widget' }).getByLabel('Name').fill('Homepage');
    await page.getByTestId('widget-name-submit-button').click();
    await expect(page.getByTestId('widget-stats')).toBeVisible();
    await expect(page.getByText('Copy the new code to your site')).toBeVisible();
    await page.getByTestId('widget-copy-code-button').click();
    await expect(page.getByText('Saved', { exact: true })).toBeVisible();

    const code = await page.getByTestId('widget-embed-code').textContent();
    const shortId = code?.match(/data-hievents-widget="(wgt_[^"]+)"/)?.[1];
    expect(shortId).toBeTruthy();

    const visitorContext = await browser.newContext({ ignoreHTTPSErrors: true });
    const host = await visitorContext.newPage();
    await openEmbedHost(host, baseURL!, widgetMarkup({ id: String(event.eventId), widget: shortId! }));
    let widget = await resolveWidgetFrame(host);
    await expect(widget.getByRole('heading', { name: event.productTitle })).toBeVisible();
    await expect(widget.getByText('Have a promo code?')).toHaveCount(0);

    const buyer = { firstName: 'Saved', lastName: 'Widget', email: uniqueEmail('saved-widget') };
    await setWidgetQuantity(widget, 1);
    const checkoutFrame = await openModalCheckout(host, widget);
    const checkout = new CheckoutPage(host, checkoutFrame);
    await checkout.fillOrderDetails(buyer);
    await checkout.fillFirstAttendee(buyer);
    await checkout.completeFreeOrder();
    await expect(checkoutFrame.getByText(`You're going to ${event.title}`)).toBeVisible();

    await page.reload();
    await page.getByTestId('widget-switcher').click();
    await page.getByRole('option', { name: 'Homepage' }).click();
    await expect(page.getByTestId('widget-stats')).toContainText(/Orders\s*1/);

    await page.getByTestId('widget-group-behaviour').click();
    await page.getByLabel('Continue button text').fill('Grab a spot');
    await page.getByTestId('widget-save-button').click();
    await expect(page.getByText('Widget saved')).toBeVisible();

    await host.reload();
    widget = await resolveWidgetFrame(host);
    await expect(widget.getByTestId('checkout-continue-button')).toContainText('Grab a spot');
    await visitorContext.close();
  });

  test('organizers pick a widget type, install it anywhere and manage saved widgets', async ({ authedPage: page, api, account }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);

    await page.goto(`/manage/event/${event.eventId}/widget`);
    await page.getByTestId('widget-type-button').click();
    await page.getByRole('button', { name: 'Get tickets', exact: true }).click();
    const popup = page.frameLocator('iframe[title="Widget preview"]');
    await expect(popup.getByRole('heading', { name: event.productTitle })).toBeVisible();
    await expect(page.getByTestId('widget-embed-code')).toContainText('data-hievents-widget-type="button"');

    await page.getByTestId('widget-platform-select').click();
    await page.getByRole('option', { name: 'React / Next.js' }).click();
    await expect(page.getByTestId('widget-embed-code')).toContainText('<hievents-widget');
    await page.getByTestId('widget-platform-select').click();
    await page.getByRole('option', { name: 'WordPress' }).click();
    await expect(page.getByText('Custom HTML block')).toBeVisible();
    await expect(page.getByTestId('widget-copy-agent-instructions-button')).toBeVisible();

    await page.getByTestId('widget-group-appearance').click();
    await page.getByTestId('widget-style-control').getByText('Custom').click();
    await page.getByLabel('Buttons', { exact: true }).fill('not-a-colour');
    await expect(page.getByTestId('widget-copy-code-button')).toBeDisabled();
    await page.getByLabel('Buttons', { exact: true }).fill('#0f766e');
    await expect(page.getByTestId('widget-copy-code-button')).toBeEnabled();

    await page.getByTestId('widget-save-button').click();
    await page.getByRole('dialog', { name: 'Save widget' }).getByLabel('Name').fill('Promo button');
    await page.getByTestId('widget-name-submit-button').click();
    await expect(page.getByTestId('widget-stats')).toBeVisible();

    await page.getByTestId('widget-options-button').click();
    await page.getByRole('menuitem', { name: 'Rename' }).click();
    await page.getByRole('dialog', { name: 'Rename widget' }).getByLabel('Name').fill('Footer button');
    await page.getByTestId('widget-name-submit-button').click();
    await expect(page.getByTestId('widget-switcher')).toHaveValue('Footer button');

    await page.getByTestId('widget-options-button').click();
    await page.getByTestId('widget-delete-menu-item').click();
    await page.getByRole('button', { name: 'Delete' }).last().click();
    await expect(page.getByTestId('widget-switcher')).toHaveCount(0);
    await expect(page.getByTestId('widget-stats')).toHaveCount(0);
  });

  test('a saved widget whose ticket was deleted drops it, and can still be renamed', async ({ authedPage: page, api, account, playwright }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    const categories = await api.listProductCategories(event.eventId);
    const vip = await api.createProduct(event.eventId, {
      title: 'VIP Pass',
      product_type: 'TICKET',
      type: 'FREE',
      product_category_id: categories[0].id,
      prices: [{ price: 0 }],
    });
    const widgetName = uniqueName('VIP only');
    await api.createEventWidget(event.eventId, { name: widgetName, type: 'TICKETS', settings: { products: [vip.id] } });
    const widgets = await WidgetApi.create(playwright, account.token);
    await widgets.deleteProduct(event.eventId, vip.id);

    await page.goto(`/manage/event/${event.eventId}/widget`);
    await page.getByTestId('widget-switcher').click();
    await page.getByRole('option', { name: widgetName }).click();
    await expect(page.getByTestId('widget-group-tickets')).toContainText('All tickets');
    await expect(page.getByText('Unsaved changes')).toBeVisible();

    await page.getByTestId('widget-options-button').click();
    await page.getByRole('menuitem', { name: 'Rename' }).click();
    await page.getByRole('dialog', { name: 'Rename widget' }).getByLabel('Name').fill(`${widgetName} renamed`);
    await page.getByTestId('widget-name-submit-button').click();
    await expect(page.getByTestId('widget-switcher')).toHaveValue(`${widgetName} renamed`);
    await expect(page.getByText('Saved', { exact: true })).toBeVisible();

    const [saved] = await widgets.listEventWidgets(event.eventId);
    expect(saved.settings).toEqual({});
    await widgets.dispose();
  });

  test('a ticket deleted while it is being added to a widget is refused with the reason', async ({ authedPage: page, api, account, playwright }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    const categories = await api.listProductCategories(event.eventId);
    const vip = await api.createProduct(event.eventId, {
      title: 'VIP Pass',
      product_type: 'TICKET',
      type: 'FREE',
      product_category_id: categories[0].id,
      prices: [{ price: 0 }],
    });
    const widgetName = uniqueName('Tickets');
    await api.createEventWidget(event.eventId, { name: widgetName, type: 'TICKETS', settings: {} });
    const widgets = await WidgetApi.create(playwright, account.token);

    await page.goto(`/manage/event/${event.eventId}/widget`);
    await page.getByTestId('widget-switcher').click();
    await page.getByRole('option', { name: widgetName }).click();
    await page.getByTestId('widget-group-tickets').click();
    await page.getByRole('combobox', { name: 'Only these tickets' }).fill('VIP');
    await page.keyboard.press('ArrowDown');
    await page.keyboard.press('Enter');
    await expect(page.getByTestId('widget-group-tickets')).toContainText('Selected tickets');
    await expect(page.getByText('Unsaved changes')).toBeVisible();

    await widgets.deleteProduct(event.eventId, vip.id);
    await page.getByTestId('widget-save-button').click();
    await expect(page.getByText('One or more selected products do not belong to this event.')).toBeVisible();
    await expect(page.getByText('Unsaved changes')).toBeVisible();

    const [saved] = await widgets.listEventWidgets(event.eventId);
    expect(saved.settings).toEqual({});
    await widgets.dispose();
  });

  test('organizers embed their events list from the organizer area', async ({ authedPage: page, api }) => {
    const organizer = await api.createOrganizer(uniqueName('E2E Events Widget Org'));
    const event = await createLiveEventWithFreeTicket(api, organizer.id);

    await page.goto(`/manage/organizer/${organizer.id}`);
    await page.getByRole('link', { name: 'Widget Embed' }).click();
    await expect(page.getByRole('heading', { name: 'Events Widget' })).toBeVisible();

    await page.getByTestId('widget-layout-control').getByText('Compact').click();
    const preview = page.locator('iframe[title="Widget preview"]').first().contentFrame();
    await expect(preview.locator('ul[data-layout="compact"]')).toBeVisible();
    await expect(preview.getByText(event.title)).toBeVisible();
    await expect(page.getByTestId('widget-embed-code')).toContainText(`data-hievents-organizer-id="${organizer.id}"`);
    await expect(page.getByTestId('widget-embed-code')).toContainText('data-hievents-layout="compact"');

    await preview.getByTestId('widget-event-item').filter({ hasText: event.title }).click();
    const popup = page.getByTestId('widget-preview-modal').locator('iframe').contentFrame();
    await expect(popup.getByRole('heading', { name: event.productTitle })).toBeVisible();
    await page.getByTestId('widget-preview-modal').getByRole('button', { name: 'Close' }).click();
    await expect(page.getByTestId('widget-preview-modal')).toHaveCount(0);
  });
});
