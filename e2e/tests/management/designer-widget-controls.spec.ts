import type { Page } from '@playwright/test';
import { test, expect } from '../../fixtures';
import { createLiveEventWithFreeTicket } from '../../api/factory';
import { DesignerPage } from '../../pages/designer.page';
import { LineupPage } from '../../pages/lineup.page';
import { invitedMember } from '../../utils/team';
import { uniqueName } from '../../utils/unique';

const SECTION_ERROR = 'Use a YouTube or Vimeo link starting with https://';

const missingDescriptions = (page: Page, containerSelector: string) =>
  page.evaluate((selector) => {
    const elements = Array.from(document.querySelectorAll(`${selector} [aria-describedby]`));
    return {
      count: elements.length,
      missing: elements
        .map((element) => element.getAttribute('aria-describedby') ?? '')
        .filter((id) => !document.getElementById(id)),
    };
  }, containerSelector);

const rejectSettingsSave = (page: Page, path: RegExp) =>
  page.route(path, (route) => {
    if (route.request().method() !== 'PATCH') {
      return route.fallback();
    }
    return route.fulfill({
      status: 422,
      contentType: 'application/json',
      body: JSON.stringify({
        message: SECTION_ERROR,
        errors: { 'homepage_sections.2.video_url': [SECTION_ERROR] },
      }),
    });
  });

test.describe('designer and widget editor controls', () => {
  test('lineup and homepage section controls say which row they act on', async ({ authedPage: page, api, account }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    const name = uniqueName('Grip Named');
    const profile = await api.createLineupProfile(account.organizerId, { name });
    await api.addLineupEntry(event.eventId, { profile_id: profile.id, billing_tier: 'HEADLINER' });

    const lineup = new LineupPage(page);
    await lineup.goto(event.eventId);
    await expect(lineup.person(name).getByRole('button', { name: `Drag to reorder ${name}`, exact: true })).toBeVisible();
    const railDescriptions = await missingDescriptions(page, '[data-testid="lineup-people-list"]');
    expect(railDescriptions.count).toBeGreaterThan(0);
    expect(railDescriptions.missing).toEqual([]);

    const designer = new DesignerPage(page);
    await designer.gotoHomepageDesigner(event.eventId);
    const about = designer.sectionRow('about');
    await expect(about.getByRole('button', { name: 'Hide About', exact: true })).toBeVisible();
    await expect(about.getByRole('button', { name: 'Section settings for About', exact: true })).toBeVisible();
    await designer.toggleSection('about');
    await expect(about.getByRole('button', { name: 'Show About', exact: true })).toBeVisible();
  });

  test('the widget editor names its choices and keeps drag instructions after hydration', async ({ authedPage: page, api, account }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    await page.goto(`/manage/event/${event.eventId}/widget`);
    await expect(page.frameLocator('iframe[title="Widget preview"]').getByRole('heading', { name: event.productTitle })).toBeVisible();

    await page.getByTestId('widget-group-sections').click();
    await expect(page.getByTestId('widget-section-about-switch')).toBeVisible();
    const pickerDescriptions = await missingDescriptions(page, 'ul:has([data-testid="widget-section-about-switch"])');
    expect(pickerDescriptions.count).toBeGreaterThan(0);
    expect(pickerDescriptions.missing).toEqual([]);

    await page.getByTestId('widget-group-appearance').click();
    await expect(page.getByRole('radiogroup', { name: 'Colours and font' })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Edit design' })).toBeVisible();

    await page.getByTestId('widget-group-behaviour').click();
    await expect(page.getByRole('radiogroup', { name: 'Checkout opens in' })).toBeVisible();
  });

  test('the widget editor flags lineup sections the event page hides or has nothing for', async ({ authedPage: page, api, account }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    await api.updateEventSettings(event.eventId, {
      homepage_sections: [{ id: 'tickets', type: 'TICKETS', enabled: true }, { id: 'schedule', type: 'SCHEDULE', enabled: false }],
    });
    await page.goto(`/manage/event/${event.eventId}/widget`);
    await page.getByTestId('widget-group-sections').click();

    const sectionRow = (section: string) => page.locator('li', { has: page.getByTestId(`widget-section-${section}-switch`) });
    await expect(page.getByTestId('widget-section-schedule-switch')).toBeDisabled();
    await expect(sectionRow('schedule').getByText('Hidden', { exact: true })).toBeVisible();
    await expect(page.getByText("Schedule is hidden on your event page, so the widget can't show it.")).toBeVisible();
    await expect(page.getByRole('link', { name: 'Open homepage designer' })).toBeVisible();

    await expect(page.getByTestId('widget-section-lineup-switch')).toBeEnabled();
    await expect(sectionRow('lineup').getByText('Empty', { exact: true })).toBeVisible();
    await expect(page.getByTestId('widget-show-lineup-switch')).toBeEnabled();
  });

  test('a rejected homepage section shows the server message', async ({ authedPage: page, api, account }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    const designer = new DesignerPage(page);

    await designer.gotoHomepageDesigner(event.eventId);
    await rejectSettingsSave(page, new RegExp(`/events/${event.eventId}/settings$`));
    await designer.toggleSection('about');
    await designer.saveButton.click();
    await expect(page.getByText(SECTION_ERROR)).toBeVisible();
    await expect(page.getByText('Please check the provided information is correct')).toHaveCount(0);

    await designer.gotoOrganizerHomepageDesigner(account.organizerId);
    await rejectSettingsSave(page, new RegExp(`/organizers/${account.organizerId}/settings$`));
    await designer.toggleSection('about');
    await designer.saveButton.click();
    await expect(page.getByText(SECTION_ERROR)).toBeVisible();
    await expect(page.getByText('Please check the provided information is correct')).toHaveCount(0);
  });

  test('a viewer is not offered designer links or event creation', async ({ freshAccount, mailpit, publicApi, browser }) => {
    const event = await createLiveEventWithFreeTicket(freshAccount.api, freshAccount.organizerId);
    await freshAccount.api.updateEventSettings(event.eventId, {
      homepage_sections: [{ id: 'tickets', type: 'TICKETS', enabled: true }, { id: 'lineup', type: 'LINEUP', enabled: false }],
    });

    const { page } = await invitedMember(freshAccount, mailpit, publicApi, browser, { role: 'VIEWER' });

    await page.goto(`/manage/organizer/${freshAccount.organizerId}/events/archived`);
    await expect(page.getByText('No archived events to show.')).toBeVisible();
    await expect(page.getByTestId('create-event-blank-slate-button')).toHaveCount(0);

    await page.goto(`/manage/event/${event.eventId}/widget`);
    await page.getByTestId('widget-group-appearance').click();
    await expect(page.getByTestId('widget-style-control')).toBeVisible();
    await expect(page.getByRole('link', { name: 'Edit design' })).toHaveCount(0);

    await page.getByTestId('widget-type-lineup').click();
    await expect(page.getByText('Your lineup is hidden on your event page, so this widget will be empty.')).toBeVisible();
    await expect(page.getByRole('link', { name: 'Open homepage designer' })).toHaveCount(0);
  });
});
