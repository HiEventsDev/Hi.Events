import { test, expect } from '../../fixtures';
import { WebhookPage } from '../../pages/webhook.page';
import { createDraftEvent } from '../../api/factory';

const hookUrl = (prefix: string): string =>
  `https://example.com/${prefix}-${Math.random().toString(36).slice(2, 8)}`;

test.describe('event webhooks', () => {
  test('an organizer creates an event webhook and sees it in the table', async ({ authedPage, api, account }) => {
    const event = await createDraftEvent(api, account.organizerId);
    const url = hookUrl('hook');

    const webhooks = new WebhookPage(authedPage);
    await webhooks.gotoEvent(event.eventId);
    await webhooks.createEventWebhook(url, ['order.created', 'attendee.created']);

    const row = webhooks.rowByUrl(url);
    await expect(row).toBeVisible();
    await expect(row.getByText('ENABLED')).toBeVisible();
    await expect(row.getByText('2 events')).toBeVisible();
  });

  test('an organizer edits an event webhook URL', async ({ authedPage, api, account }) => {
    const event = await createDraftEvent(api, account.organizerId);
    const url = hookUrl('hook');
    await api.createWebhook(event.eventId, { url, event_types: ['order.created'], status: 'ENABLED' });

    const webhooks = new WebhookPage(authedPage);
    await webhooks.gotoEvent(event.eventId);
    await webhooks.chooseRowAction(url, 'Edit webhook');

    await expect(authedPage.getByRole('heading', { name: 'Edit Webhook' })).toBeVisible();
    await expect(webhooks.editUrlInput()).toHaveValue(url);

    const newUrl = hookUrl('edit');
    await webhooks.editUrlInput().fill(newUrl);
    await webhooks.submitEditForm();

    await expect(webhooks.rowByUrl(newUrl)).toBeVisible();
    await expect(webhooks.rowByUrl(url)).toBeHidden();
  });

  test('an organizer pauses an event webhook', async ({ authedPage, api, account }) => {
    const event = await createDraftEvent(api, account.organizerId);
    const url = hookUrl('hook');
    await api.createWebhook(event.eventId, { url, event_types: ['order.created'], status: 'ENABLED' });

    const webhooks = new WebhookPage(authedPage);
    await webhooks.gotoEvent(event.eventId);
    await webhooks.chooseRowAction(url, 'Edit webhook');

    await expect(webhooks.editUrlInput()).toHaveValue(url);
    await webhooks.selectPausedStatus();
    await webhooks.submitEditForm();

    await expect(webhooks.rowByUrl(url).getByText(/^PAUSED$/)).toBeVisible();
  });

  test('an organizer deletes an event webhook', async ({ authedPage, api, account }) => {
    const event = await createDraftEvent(api, account.organizerId);
    const url = hookUrl('hook');
    await api.createWebhook(event.eventId, { url, event_types: ['order.created'], status: 'ENABLED' });

    const webhooks = new WebhookPage(authedPage);
    await webhooks.gotoEvent(event.eventId);
    await webhooks.chooseRowAction(url, 'Delete webhook');
    await webhooks.confirmDeletion();

    await expect(webhooks.rowByUrl(url)).toBeHidden();
    await expect(authedPage.getByRole('heading', { name: 'No Webhooks' })).toBeVisible();
  });

  test('an organizer views the logs for an event webhook', async ({ authedPage, api, account }) => {
    const event = await createDraftEvent(api, account.organizerId);
    const url = hookUrl('hook');
    await api.createWebhook(event.eventId, { url, event_types: ['order.created'], status: 'ENABLED' });

    const webhooks = new WebhookPage(authedPage);
    await webhooks.gotoEvent(event.eventId);
    await webhooks.chooseRowAction(url, 'View logs');

    await expect(authedPage.getByRole('heading', { name: 'Webhook Logs' })).toBeVisible();
    await expect(authedPage.getByRole('heading', { name: 'No logs found' })).toBeVisible();
  });
});
