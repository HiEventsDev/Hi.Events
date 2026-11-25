import { test, expect } from '../../fixtures';
import { EventSettingsPage } from '../../pages/event-settings.page';
import { ProductEditPage } from '../../pages/product-edit.page';
import { WebhookPage } from '../../pages/webhook.page';
import { createFreshOrganizer, createLiveEventWithFreeTicket } from '../../api/factory';

test.describe('organizer controls for ticket resale', () => {
  test('resale is off by default and an organizer turns it on with a cutoff', async ({ authedPage, api, account }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);

    const settings = new EventSettingsPage(authedPage);
    await settings.goto(event.eventId, 'homepage-settings');
    const section = settings.section('homepage-settings');
    await expect(section.getByLabel('Let buyers resell tickets')).not.toBeChecked();
    await expect(section.getByTestId('ticket-resale-cutoff-select')).toBeDisabled();
    await expect(section.getByText('that chargeback is at your risk, as with any sale.')).toBeVisible();

    await section.getByLabel('Let buyers resell tickets').check({ force: true });
    await section.getByTestId('ticket-resale-cutoff-select').click();
    await authedPage.getByRole('option', { name: '1 day before' }).click();
    await settings.saveSection('homepage-settings');
    await expect(authedPage.getByText('Successfully Updated Homepage Settings')).toBeVisible();

    await expect.poll(async () => (await api.getEventSettings(event.eventId)).allow_ticket_resale).toBe(true);
    expect((await api.getEventSettings(event.eventId)).ticket_resale_cutoff_minutes).toBe(1440);
  });

  test('the organizer default decides whether a new event allows resale', async ({ authedPage, api }) => {
    const organizer = await createFreshOrganizer(api);

    await authedPage.goto(`/manage/organizer/${organizer.id}/settings/event-defaults`);
    await authedPage.waitForLoadState('networkidle');
    await expect(authedPage.getByLabel('Let buyers resell tickets by default')).not.toBeChecked();
    await authedPage.getByLabel('Let buyers resell tickets by default').check({ force: true });
    await authedPage.getByRole('button', { name: 'Save' }).click();
    await expect.poll(async () => (await api.getOrganizerSettings(organizer.id)).default_allow_ticket_resale).toBe(true);

    const event = await createLiveEventWithFreeTicket(api, organizer.id);
    expect((await api.getEventSettings(event.eventId)).allow_ticket_resale).toBe(true);
  });

  test('a ticket type switched to not resellable says so when reopened', async ({ authedPage, api, account }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);

    const products = new ProductEditPage(authedPage);
    await products.goto(event.eventId);
    await products.openFirstProduct();
    await expect(products.ledgerRow('after-purchase')).toContainText('Resellable');
    await products.toggleSwitchInLedgerRow('after-purchase', 'Buyers can resell this ticket');
    await expect(authedPage.getByLabel('Buyers can resell this ticket')).not.toBeChecked();
    await products.submitEdit();

    await products.openFirstProduct();
    await expect(products.ledgerRow('after-purchase')).toContainText('Not resellable');
  });

  test('a webhook can subscribe to resold tickets', async ({ authedPage, account }) => {
    const webhooks = new WebhookPage(authedPage);
    await webhooks.goto(account.organizerId);
    const url = `https://example.com/hooks/resold-${Date.now()}`;

    await webhooks.createWebhook(url, 'attendee.resold');

    await expect(authedPage.getByText(url)).toBeVisible();
  });
});
