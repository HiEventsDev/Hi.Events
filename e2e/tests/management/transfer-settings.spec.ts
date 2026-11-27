import { test, expect } from '../../fixtures';
import { EventSettingsPage } from '../../pages/event-settings.page';
import { OrderSummaryPage } from '../../pages/order-summary.page';
import { ProductEditPage } from '../../pages/product-edit.page';
import { WebhookPage } from '../../pages/webhook.page';
import { createCompletedOrder, createFreshOrganizer, createLiveEventWithFreeTicket } from '../../api/factory';

test.describe('organizer controls for ticket transfers', () => {
  test('an organizer turns transfers off, sets a cutoff and a limit, and buyers stop being offered transfers', async ({ authedPage, page, api, account, publicApi }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    const order = await createCompletedOrder(publicApi, event);

    const settings = new EventSettingsPage(authedPage);
    await settings.goto(event.eventId, 'homepage-settings');
    const section = settings.section('homepage-settings');
    await expect(section.getByLabel('Let ticket holders transfer tickets')).toBeChecked();

    await section.getByTestId('ticket-transfer-cutoff-select').click();
    await authedPage.getByRole('option', { name: '1 day before' }).click();
    await section.getByLabel('Transfers per ticket').fill('2');
    await settings.saveSection('homepage-settings');
    await expect(authedPage.getByText('Successfully Updated Homepage Settings')).toBeVisible();

    await expect.poll(async () => (await api.getEventSettings(event.eventId)).ticket_transfer_cutoff_minutes).toBe(1440);
    expect((await api.getEventSettings(event.eventId)).max_transfers_per_ticket).toBe(2);

    const summary = new OrderSummaryPage(page);
    await summary.goto(event.eventId, order.orderShortId);
    await expect(summary.transferButton()).toBeVisible();

    await section.getByLabel('Let ticket holders transfer tickets').uncheck({ force: true });
    await expect(section.getByLabel('Transfers per ticket')).toBeDisabled();
    await settings.saveSection('homepage-settings');
    await expect.poll(async () => (await api.getEventSettings(event.eventId)).allow_ticket_transfer).toBe(false);

    await summary.goto(event.eventId, order.orderShortId);
    await expect(page.getByTestId('attendee-edit-button')).toBeVisible();
    await expect(summary.transferButton()).toHaveCount(0);
  });

  test('the organizer default decides whether a new event allows transfers', async ({ authedPage, api }) => {
    const organizer = await createFreshOrganizer(api);

    await authedPage.goto(`/manage/organizer/${organizer.id}/settings/event-defaults`);
    await authedPage.waitForLoadState('networkidle');
    await authedPage.getByLabel('Let ticket holders transfer tickets by default').uncheck({ force: true });
    await authedPage.getByRole('button', { name: 'Save' }).click();
    await expect.poll(async () => (await api.getOrganizerSettings(organizer.id)).default_allow_ticket_transfer).toBe(false);

    const event = await createLiveEventWithFreeTicket(api, organizer.id);
    expect((await api.getEventSettings(event.eventId)).allow_ticket_transfer).toBe(false);
  });

  test('a ticket type switched to not transferable stops offering transfers', async ({ authedPage, page, api, account, publicApi }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    const order = await createCompletedOrder(publicApi, event);

    const products = new ProductEditPage(authedPage);
    await products.goto(event.eventId);
    await products.openFirstProduct();
    await expect(products.ledgerRow('after-purchase')).toContainText('Transferable');
    await products.toggleSwitchInLedgerRow('after-purchase', 'Holders can transfer this ticket');
    await expect(authedPage.getByLabel('Holders can transfer this ticket')).not.toBeChecked();
    await products.submitEdit();

    const summary = new OrderSummaryPage(page);
    await summary.goto(event.eventId, order.orderShortId);
    await expect(page.getByTestId('attendee-edit-button')).toBeVisible();
    await expect(summary.transferButton()).toHaveCount(0);

    await products.openFirstProduct();
    await expect(products.ledgerRow('after-purchase')).toContainText('Not transferable');
  });

  test('a webhook can subscribe to ticket transfers', async ({ authedPage, account }) => {
    const webhooks = new WebhookPage(authedPage);
    await webhooks.goto(account.organizerId);
    const url = `https://example.com/hooks/transfer-${Date.now()}`;

    await webhooks.createWebhook(url, 'attendee.transferred');

    await expect(authedPage.getByText(url)).toBeVisible();
  });
});
