import { test, expect } from '../../fixtures';
import { EventSettingsPage } from '../../pages/event-settings.page';
import { createDraftEvent } from '../../api/factory';
import { uniqueName } from '../../utils/unique';
import { expectDateTime, pickDateTime, pickerWeekdayHeaders } from '../../utils/datePicker';

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

  test('an organizer reschedules the event with a calendar that starts on the organizer\'s first day of the week', async ({ authedPage, api }) => {
    const organizer = await api.createOrganizer(uniqueName('E2E Sunday Organizer'));
    await api.updateOrganizerSettings(organizer.id, { first_day_of_week: 0 });
    const event = await createDraftEvent(api, organizer.id);
    const newStart = '2030-06-14T19:30';
    const newEnd = '2030-06-14T23:00';

    const settings = new EventSettingsPage(authedPage);
    await settings.goto(event.eventId);

    await settings.detailsStartDate.click();
    await expect(pickerWeekdayHeaders(authedPage).first()).toHaveText('Su');
    await authedPage.keyboard.press('Escape');

    await pickDateTime(authedPage, settings.detailsEndDate, newEnd);
    await pickDateTime(authedPage, settings.detailsStartDate, newStart);
    await settings.saveSection('event-details');
    await expect(authedPage.getByText('Successfully Updated Event')).toBeVisible();

    await expect.poll(async () => (await api.getEvent(event.eventId)).start_date).toMatch(/^2030-06-14T19:30:00/);
    expect((await api.getEvent(event.eventId)).end_date).toMatch(/^2030-06-14T23:00:00/);
    await authedPage.reload();
    await authedPage.waitForLoadState('networkidle');
    await expectDateTime(authedPage, settings.detailsStartDate, newStart);
  });

  test.describe('from a browser in another timezone', () => {
    test.use({ timezoneId: 'Europe/Dublin' });

    test('a time inside the browser\'s DST gap is saved as the event\'s wall-clock time', async ({ authedPage, api, account }) => {
      const event = await api.createEvent({
        title: uniqueName('E2E New York Event'),
        type: 'SINGLE',
        organizer_id: account.organizerId,
        start_date: '2030-06-01T12:00:00Z',
        category: 'MUSIC',
        currency: 'USD',
        timezone: 'America/New_York',
      });

      const settings = new EventSettingsPage(authedPage);
      await settings.goto(event.id);
      await pickDateTime(authedPage, settings.detailsStartDate, '2026-03-29T01:30');
      await pickDateTime(authedPage, settings.detailsEndDate, '2026-03-29T03:00');
      await settings.saveSection('event-details');
      await expect(authedPage.getByText('Successfully Updated Event')).toBeVisible();

      await expect.poll(async () => (await api.getEvent(event.id)).start_date).toMatch(/^2026-03-29T05:30:00/);
      await authedPage.reload();
      await authedPage.waitForLoadState('networkidle');
      await expectDateTime(authedPage, settings.detailsStartDate, '2026-03-29T01:30');
    });
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
