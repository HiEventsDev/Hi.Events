import { test, expect } from '../../fixtures';
import { EventSettingsPage } from '../../pages/event-settings.page';
import { createDraftEvent } from '../../api/factory';
import { uniqueName } from '../../utils/unique';

test.describe('event settings', () => {
  test('an organizer renames the event and updates the description', { tag: '@smoke' }, async ({ authedPage, api, account }) => {
    const event = await createDraftEvent(api, account.organizerId);
    const newTitle = uniqueName('E2E Renamed');
    const newDescription = uniqueName('An updated event description');

    const settings = new EventSettingsPage(authedPage);
    await settings.goto(event.eventId);

    await expect(settings.detailsNameInput).toHaveValue(event.title);
    await settings.detailsNameInput.fill(newTitle);
    await settings.detailsDescriptionEditor.fill(newDescription);
    await settings.saveSection('event-details');
    await expect(authedPage.getByText('Successfully Updated Event')).toBeVisible();

    await authedPage.reload();
    await authedPage.waitForLoadState('networkidle');
    await expect(settings.detailsNameInput).toHaveValue(newTitle);
    await expect(settings.detailsDescriptionEditor).toContainText(newDescription);
  });

  test('an organizer enables offline payments with instructions', async ({ authedPage, api, account }) => {
    const event = await createDraftEvent(api, account.organizerId);
    const instructions = uniqueName('Wire the funds to account');

    const settings = new EventSettingsPage(authedPage);
    await settings.goto(event.eventId);

    await settings.offlinePaymentsCheckbox.check();
    await settings.offlineInstructionsEditor.fill(instructions);
    await settings.saveSection('payment-settings');
    await expect(authedPage.getByText('Successfully Updated Payment & Invoicing Settings')).toBeVisible();

    await expect
      .poll(async () => (await api.getEventSettings(event.eventId)).payment_providers ?? [])
      .toContain('OFFLINE');
    const saved = await api.getEventSettings(event.eventId);
    expect(saved.offline_payment_instructions).toContain(instructions);
  });

  test('an organizer updates SEO and miscellaneous settings', async ({ authedPage, api, account }) => {
    const event = await createDraftEvent(api, account.organizerId);
    const seoTitle = uniqueName('E2E SEO Title');

    const settings = new EventSettingsPage(authedPage);
    await settings.goto(event.eventId);

    await settings.seoTitleInput.fill(seoTitle);
    await settings.saveSection('seo-settings');
    await expect(authedPage.getByText('Successfully Updated Seo Settings')).toBeVisible();

    await settings.selfServiceSwitch.check({ force: true });
    await settings.saveSection('misc-settings');
    await expect(authedPage.getByText('Successfully Updated Misc Settings')).toBeVisible();

    await expect
      .poll(async () => (await api.getEventSettings(event.eventId)).seo_title)
      .toBe(seoTitle);
    expect((await api.getEventSettings(event.eventId)).allow_attendee_self_edit).toBe(true);
  });
});
