import { test, expect } from '../../fixtures';
import { EventSettingsPage } from '../../pages/event-settings.page';
import { createCompletedOrder, createDraftEvent, createLiveEventWithProduct, createRecurringLiveEvent } from '../../api/factory';

test.describe('event type', () => {
  test('an organizer turns a single event into a recurring event and back', async ({ authedPage, api, account }) => {
    const event = await createDraftEvent(api, account.organizerId);

    const settings = new EventSettingsPage(authedPage);
    await settings.goto(event.eventId);
    await expect(settings.section('event-type')).toContainText('This is a single event.');
    await expect(settings.section('event-type')).toContainText('while it has exactly one date and no orders');

    await settings.changeEventType('Change to recurring event');
    await expect(authedPage.getByText('Event changed to a recurring event')).toBeVisible();
    await expect(settings.section('event-type')).toContainText('This is a recurring event.');
    await expect(authedPage.getByRole('link', { name: 'Occurrence Schedule' })).toBeVisible();
    await expect.poll(async () => (await api.getEvent(event.eventId)).type).toBe('RECURRING');

    await settings.changeEventType('Change to single event');
    await expect(authedPage.getByText('Event changed to a single event')).toBeVisible();
    await expect(settings.section('event-type')).toContainText('This is a single event.');
    await expect(authedPage.getByRole('link', { name: 'Occurrence Schedule' })).toHaveCount(0);
    await expect.poll(async () => (await api.getEvent(event.eventId)).type).toBe('SINGLE');
  });

  test('an organizer is warned that a single event with orders cannot be changed back', async ({ authedPage, api, publicApi, account }) => {
    const event = await createLiveEventWithProduct(api, { organizerId: account.organizerId });
    await createCompletedOrder(publicApi, event);

    const settings = new EventSettingsPage(authedPage);
    await expect(async () => {
      await settings.goto(event.eventId);
      await expect(settings.section('event-type')).toContainText('already has orders', { timeout: 2_000 });
    }).toPass();

    await settings.eventTypeChangeButton.click();
    const dialog = authedPage.getByRole('dialog');
    await expect(dialog).toContainText("This event already has orders, so you won't be able to change it back to a single event.");
    await dialog.getByRole('button', { name: 'Change to recurring event' }).click();
    await expect(authedPage.getByText('Event changed to a recurring event')).toBeVisible();

    await settings.changeEventType('Change to single event');
    await expect(authedPage.getByText('A recurring event with orders cannot be changed to a single event.')).toBeVisible();
    expect((await api.getEvent(event.eventId)).type).toBe('RECURRING');
  });

  test('a recurring event with several dates stays recurring', async ({ authedPage, api, account }) => {
    const event = await createRecurringLiveEvent(api, account.organizerId, { count: 2 });

    const settings = new EventSettingsPage(authedPage);
    await settings.goto(event.eventId);

    await settings.changeEventType('Change to single event');
    await expect(authedPage.getByText('Only a recurring event with exactly one date can be changed to a single event.', { exact: false })).toBeVisible();
    await expect(settings.section('event-type')).toContainText('This is a recurring event.');
    expect((await api.getEvent(event.eventId)).type).toBe('RECURRING');
  });
});
