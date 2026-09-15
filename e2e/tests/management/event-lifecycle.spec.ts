import { test, expect } from '../../fixtures';
import { EventSettingsPage } from '../../pages/event-settings.page';
import { createDraftEvent, createDraftEventWithTicket, createLiveEventWithFreeTicket } from '../../api/factory';
import { uniqueName } from '../../utils/unique';
import { nonSaasOnly, saasOnly } from '../../utils/mode';

test.describe('event lifecycle', () => {
  test('an organizer publishes a draft event and reverts it to draft', { tag: '@smoke' }, async ({ authedPage, api, account }) => {
    const event = await createDraftEvent(api, account.organizerId);

    await authedPage.goto(`/manage/event/${event.eventId}/dashboard`);
    await authedPage.waitForLoadState('networkidle');

    const statusToggle = authedPage.getByTestId('event-status-toggle');
    await expect(statusToggle).toContainText('Draft');

    await statusToggle.click();
    await expect(authedPage.getByText('Ready to go live?')).toBeVisible();
    await expect(authedPage.getByText('No tickets to sell')).toBeVisible();
    await authedPage.getByTestId('publish-event-confirm-button').click();

    await expect(authedPage.getByText('Your event is live!')).toBeVisible();
    await authedPage.getByRole('button', { name: 'Done' }).click();
    await expect(statusToggle).toContainText('Live');

    await statusToggle.click();
    await authedPage.getByRole('button', { name: 'Confirm' }).click();
    await expect(statusToggle).toContainText('Draft');
  });

  test('a self-hosted organizer publishes a paid event without connecting Stripe', { tag: '@smoke' }, async ({ authedPage, api, account }) => {
    nonSaasOnly();
    const event = await createDraftEventWithTicket(api, account.organizerId, { price: 25 });

    await authedPage.goto(`/manage/event/${event.eventId}/dashboard`);
    await authedPage.waitForLoadState('networkidle');

    const statusToggle = authedPage.getByTestId('event-status-toggle');
    await statusToggle.click();
    await expect(authedPage.getByText('Ready to go live?')).toBeVisible();

    const publishButton = authedPage.getByTestId('publish-event-confirm-button');
    await expect(publishButton).toHaveText('Publish Event');
    await expect(authedPage.getByText('Connect Stripe to accept payments')).toHaveCount(0);
    await publishButton.click();

    await expect(authedPage.getByText('Your event is live!')).toBeVisible();
    await authedPage.getByRole('button', { name: 'Done' }).click();
    await expect(statusToggle).toContainText('Live');
  });

  test('a SaaS organizer without Stripe is blocked from publishing a paid event', async ({ authedPage, api, account }) => {
    saasOnly();
    const event = await createDraftEventWithTicket(api, account.organizerId, { price: 25 });

    await authedPage.goto(`/manage/event/${event.eventId}/dashboard`);
    await authedPage.waitForLoadState('networkidle');

    await authedPage.getByTestId('event-status-toggle').click();
    await expect(authedPage.getByText('Ready to go live?')).toBeVisible();

    await expect(authedPage.getByText('Connect Stripe to accept payments')).toBeVisible();
    const publishButton = authedPage.getByTestId('publish-event-confirm-button');
    await expect(publishButton).toHaveText('Fix issues to publish');
    await expect(publishButton).toBeDisabled();
  });

  test('an organizer duplicates an event and sees the copy as a draft', async ({ authedPage, api, account }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    const duplicateTitle = uniqueName('E2E Duplicate');

    await authedPage.goto(`/manage/organizer/${account.organizerId}/events`);
    await authedPage.waitForLoadState('networkidle');
    await authedPage.getByPlaceholder('Search by event name...').fill(event.title);
    await authedPage.waitForURL(/query=/);
    await authedPage.waitForLoadState('networkidle');

    const sourceCard = authedPage
      .locator('a')
      .filter({ has: authedPage.getByRole('heading', { name: event.title }) });
    await sourceCard.getByRole('button').click();
    await authedPage.getByTestId('event-duplicate-menu-item').click();

    const nameInput = authedPage.getByRole('dialog').getByLabel(/^Name/);
    await expect(nameInput).toHaveValue(event.title);
    await nameInput.fill(duplicateTitle);
    await authedPage.getByRole('button', { name: 'Duplicate Event' }).click();

    await authedPage.waitForURL(/\/manage\/event\/\d+/);

    await authedPage.goto(`/manage/organizer/${account.organizerId}/events`);
    await authedPage.waitForLoadState('networkidle');
    await authedPage.getByPlaceholder('Search by event name...').fill(duplicateTitle);

    const duplicateCard = authedPage
      .locator('a')
      .filter({ has: authedPage.getByRole('heading', { name: duplicateTitle }) });
    await expect(duplicateCard).toBeVisible();
    await expect(duplicateCard.getByText('Draft')).toBeVisible();
  });

  test('an organizer deletes a draft event from the danger zone', async ({ authedPage, api, account }) => {
    const event = await createDraftEvent(api, account.organizerId);

    const settings = new EventSettingsPage(authedPage);
    await settings.goto(event.eventId);

    await settings.deleteConfirmationInput.fill('delete');
    await settings.deleteButton.click();

    await authedPage.waitForURL(new RegExp(`/manage/organizer/${account.organizerId}/events`));
    await expect(authedPage.getByRole('heading', { name: 'Events' })).toBeVisible();
    await expect(authedPage.getByRole('heading', { name: event.title })).toHaveCount(0);
  });
});
