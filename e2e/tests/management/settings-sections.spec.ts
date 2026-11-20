import { test, expect } from '../../fixtures';
import { createDraftEvent } from '../../api/factory';

test.describe('Settings sections', () => {
  test('event settings open one section per page and keep old anchor links working', async ({ authedPage, api, account }) => {
    const event = await createDraftEvent(api, account.organizerId);
    const settingsPath = `/manage/event/${event.eventId}/settings`;

    await authedPage.goto(settingsPath);
    await expect(authedPage).toHaveURL(new RegExp(`${settingsPath}/details$`));
    await expect(authedPage.getByRole('heading', { name: 'Event Details' })).toBeVisible();
    await expect(authedPage.getByRole('heading', { name: 'Waitlist' })).toHaveCount(0);

    const sections = authedPage.getByRole('navigation', { name: 'Event Settings' });
    await sections.getByRole('link', { name: 'Waitlist' }).click();
    await expect(authedPage).toHaveURL(new RegExp(`${settingsPath}/waitlist$`));
    await expect(authedPage.getByRole('heading', { name: 'Waitlist' })).toBeVisible();
    await expect(sections.getByRole('link', { name: 'Waitlist' })).toHaveAttribute('aria-current', 'page');
    await expect(authedPage.getByRole('heading', { name: 'Event Details' })).toHaveCount(0);

    await authedPage.goto(`${settingsPath}#payment-settings`);
    await expect(authedPage).toHaveURL(new RegExp(`${settingsPath}/payments$`));
    await expect(authedPage.getByRole('heading', { name: 'Payment & Invoicing' })).toBeVisible();

    await authedPage.goto(`${settingsPath}/not-a-section`);
    await expect(authedPage).toHaveURL(new RegExp(`${settingsPath}/details$`));
  });

  test('on a narrow screen the section list becomes a picker', async ({ authedPage, api, account }) => {
    const event = await createDraftEvent(api, account.organizerId);
    const settingsPath = `/manage/event/${event.eventId}/settings`;
    await authedPage.setViewportSize({ width: 700, height: 900 });

    await authedPage.goto(`${settingsPath}/details`);
    await expect(authedPage.getByRole('navigation', { name: 'Event Settings' })).toBeHidden();
    const picker = authedPage.getByRole('combobox', { name: 'Settings section' });
    await expect(picker).toHaveValue('Event Details');

    await picker.click();
    await authedPage.getByRole('option', { name: 'Waitlist' }).click();
    await expect(authedPage).toHaveURL(new RegExp(`${settingsPath}/waitlist$`));
    await expect(authedPage.getByRole('heading', { name: 'Waitlist' })).toBeVisible();
    await expect(picker).toHaveValue('Waitlist');
  });

  test('organizer settings open one section per page and keep old anchor links working', async ({ authedPage, account }) => {
    const settingsPath = `/manage/organizer/${account.organizerId}/settings`;

    await authedPage.goto(`${settingsPath}#event-defaults`);
    await expect(authedPage).toHaveURL(new RegExp(`${settingsPath}/event-defaults$`));
    await expect(authedPage.getByRole('heading', { name: 'Event Defaults' })).toBeVisible();
    await expect(authedPage.getByRole('heading', { name: 'Basic Information' })).toHaveCount(0);

    await authedPage.getByRole('navigation', { name: 'Organizer Settings' }).getByRole('link', { name: 'Basic Information' }).click();
    await expect(authedPage).toHaveURL(new RegExp(`${settingsPath}/basic$`));
    await expect(authedPage.getByRole('heading', { name: 'Basic Information' })).toBeVisible();
  });
});
