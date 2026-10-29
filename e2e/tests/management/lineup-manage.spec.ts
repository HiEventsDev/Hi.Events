import { writeFile } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';
import type { Page } from '@playwright/test';
import { test, expect } from '../../fixtures';
import { createLiveEventWithFreeTicket, createRecurringLiveEvent } from '../../api/factory';
import { LineupPage } from '../../pages/lineup.page';
import { PeoplePage } from '../../pages/people.page';
import { pickDate } from '../../utils/datePicker';
import { uniqueName } from '../../utils/unique';

const COVER_PHOTO = fileURLToPath(new URL('../../fixtures/assets/event-cover.png', import.meta.url));
const GALLERY_PHOTO = fileURLToPath(new URL('../../fixtures/assets/gallery-photo.png', import.meta.url));

const addDays = (day: string, days: number): string => {
  const date = new Date(`${day}T00:00:00Z`);
  date.setUTCDate(date.getUTCDate() + days);
  return date.toISOString().slice(0, 10);
};

const chipLabel = (day: string): string =>
  new Date(`${day}T12:00:00Z`).toLocaleDateString('en-US', { month: 'short', day: 'numeric', timeZone: 'UTC' });

const utcStamp = (date: Date): string => date.toISOString().slice(0, 16).replace('T', ' ') + ':00';

const dayChips = (page: Page) => page.getByRole('group', { name: 'Days' }).getByRole('button');

const selectedDay = (page: Page) => page.getByRole('group', { name: 'Days' }).getByRole('button', { pressed: true });

const currentTimeIndicator = (page: Page) => page.locator('.mantine-ResourcesDayView-resourcesDayViewCurrentTimeIndicator');

const addTimeSlot = async (page: Page, details: { person: string; date: string; starts: string; ends: string }) => {
  await page.getByTestId('lineup-add-slot-button').click();
  const dialog = page.getByRole('dialog');
  await page.getByTestId('lineup-slot-people').click();
  await page.getByRole('option', { name: details.person }).click();
  await pickDate(page, dialog.getByRole('button', { name: /^Date/ }), details.date);
  await dialog.getByLabel('Starts').fill(details.starts);
  await dialog.getByLabel('Ends').fill(details.ends);
  await page.getByTestId('lineup-slot-save-button').click();
};

test.describe('managing a lineup', () => {
  test('a recurring event flags slots on days it does not run instead of counting them as scheduled', async ({ authedPage: page, api, account }) => {
    const event = await createRecurringLiveEvent(api, account.organizerId, { count: 3 });
    const firstDate = event.occurrences[0].start_date.slice(0, 10);
    const secondDate = event.occurrences[1].start_date.slice(0, 10);
    const offDay = addDays(firstDate, 2);
    const name = uniqueName('Wrong Night');
    await api.importLineup(event.eventId, {
      people: [{ name, billing_tier: 'HEADLINER' }],
      slots: [{ starts_at: `${offDay} 20:00:00`, ends_at: `${offDay} 21:00:00`, people: [name] }],
    });

    const lineup = new LineupPage(page);
    await lineup.goto(event.eventId);

    const stats = page.getByTestId('lineup-stats');
    await expect(stats).toContainText('0/1');
    await expect(stats).toContainText('1 not on an event date');
    await expect(stats).not.toContainText('Everyone has a time');

    await lineup.openView('Timetable');
    await expect(selectedDay(page)).toContainText(chipLabel(offDay));
    await expect(selectedDay(page)).toContainText('No event date on this day');
    await expect(page.getByText("Your event has no date on this day, so slots here won't show on your event page.")).toBeVisible();

    await lineup.stepTimetable('Next');
    await expect(selectedDay(page)).toContainText(chipLabel(secondDate));
    await expect(selectedDay(page)).not.toContainText('No event date on this day');
    await expect(page.getByText("Your event has no date on this day, so slots here won't show on your event page.")).toHaveCount(0);

    await lineup.stepTimetable('Previous');
    await expect(selectedDay(page)).toContainText(chipLabel(firstDate));

    await lineup.openView('List');
    await page.getByTestId('lineup-agenda').getByRole('button', { name: new RegExp(name) }).click();
    await expect(page.getByRole('dialog')).toContainText("Your event has no date on this day, so this slot won't show on your event page.");
    await page.getByRole('dialog').getByRole('button', { name: 'Cancel' }).click();

    await lineup.openImport();
    await lineup.pasteRows([
      ['Date', 'Start', 'End', 'Title'],
      [offDay, '19:00', '20:00', 'Wrong night talk'],
      [secondDate, '19:00', '20:00', 'Right night talk'],
    ]);
    await expect(lineup.importRow('Wrong night talk')).toContainText("No event date on this day, so it won't show on your event page");
    await expect(lineup.importRow('Right night talk')).not.toContainText('No event date on this day');
  });

  test('the timetable only shows the current time on today', async ({ authedPage: page, api, account }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    const name = uniqueName('Right Now');
    const now = new Date(Math.floor(Date.now() / 60_000) * 60_000);
    await api.importLineup(event.eventId, {
      people: [{ name }],
      slots: [{ starts_at: utcStamp(now), ends_at: utcStamp(new Date(now.getTime() + 60 * 60_000)), people: [name] }],
    });
    await page.clock.setFixedTime(now);

    const lineup = new LineupPage(page);
    await lineup.goto(event.eventId);
    await lineup.openView('Timetable');
    await expect(currentTimeIndicator(page)).toHaveCount(1);
    const today = (await selectedDay(page).textContent()) ?? '';

    await lineup.stepTimetable('Next');
    await expect(selectedDay(page)).not.toHaveText(today);
    await expect(currentTimeIndicator(page)).toHaveCount(0);
  });

  test('a slot added after midnight stays on the evening it belongs to', async ({ authedPage: page, api, account }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    const opener = uniqueName('Opener');
    const closer = uniqueName('Closer');
    await api.importLineup(event.eventId, {
      people: [{ name: opener }, { name: closer }],
      slots: [{ starts_at: '2030-06-14 22:00:00', ends_at: '2030-06-14 23:30:00', people: [opener] }],
    });

    const lineup = new LineupPage(page);
    await lineup.goto(event.eventId);
    await lineup.openView('Timetable');
    await expect(dayChips(page)).toHaveCount(1);
    await expect(selectedDay(page)).toContainText('Jun 14');

    await addTimeSlot(page, { person: closer, date: '2030-06-15', starts: '01:00', ends: '02:00' });
    await expect(page.getByText('Time slot added')).toBeVisible();

    await expect(selectedDay(page)).toContainText('Jun 14');
    await expect(selectedDay(page)).toContainText('2 slots');
    const block = page.getByText(closer, { exact: true }).locator('xpath=ancestor::*[contains(@class, "calendarEventText")]');
    await expect(block).toContainText('1:00 AM–2:00 AM');
  });

  test('profile photos can be reordered and removed from the keyboard', async ({ authedPage: page, api, account }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    const name = uniqueName('Photo Ready');
    const profile = await api.createLineupProfile(account.organizerId, { name });
    await api.addLineupEntry(event.eventId, { profile_id: profile.id, billing_tier: 'HEADLINER' });

    const lineup = new LineupPage(page);
    await lineup.goto(event.eventId);
    await lineup.person(name).getByRole('button', { name: new RegExp(name) }).filter({ hasText: name }).click();
    await lineup.uploadPhoto(COVER_PHOTO);
    await expect(lineup.drawer().getByRole('button', { name: 'Remove photo 1' })).toBeVisible();
    await lineup.uploadPhoto(GALLERY_PHOTO);
    await expect(lineup.drawer().getByRole('button', { name: 'Move photo 2 of 2' })).toBeVisible();

    const photos = lineup.drawer().getByTestId('lineup-profile-photos').locator('[data-testid^="lineup-profile-photo-"]:not([data-testid$="-dropzone"])');
    await expect(photos).toHaveCount(2);
    const firstTile = photos.nth(0);
    const secondId = await photos.nth(1).getAttribute('data-testid');
    await expect(firstTile).not.toHaveAttribute('role', 'button');

    const sortRequest = page.waitForRequest(request => /\/profiles\/\d+\/images\/sort/.test(request.url()));
    await lineup.drawer().getByRole('button', { name: 'Move photo 2 of 2' }).focus();
    await page.keyboard.press('Space');
    await expect(photos.nth(1)).toHaveAttribute('data-dragging', 'true');
    await expect(async () => {
      await page.keyboard.press('ArrowLeft');
      await expect(photos.nth(0)).toHaveAttribute('style', /translate3d\([1-9]/, { timeout: 1000 });
    }).toPass();
    await page.keyboard.press('Space');
    await sortRequest;
    await expect(photos.nth(0)).toHaveAttribute('data-testid', secondId!);
    await expect(photos.nth(0)).toContainText('Main photo');

    await lineup.drawer().getByRole('button', { name: 'Remove photo 2' }).focus();
    await page.keyboard.press('Enter');
    const confirm = page.getByRole('dialog').filter({ hasText: 'Remove this photo?' });
    await expect(confirm).toBeVisible();
    const deleteRequest = page.waitForRequest(request => request.method() === 'DELETE' && /\/images\/\d+/.test(request.url()));
    await confirm.getByRole('button', { name: 'Remove' }).click();
    await deleteRequest;
    await expect(photos).toHaveCount(1);
    await expect(photos.nth(0)).toHaveAttribute('data-testid', secondId!);
  });

  test('people hidden from every lineup are not counted as showing on a live event', async ({ authedPage: page, api, account }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    const announced = uniqueName('Announced Act');
    const secret = uniqueName('Secret Act');
    const announcedProfile = await api.createLineupProfile(account.organizerId, { name: announced });
    const secretProfile = await api.createLineupProfile(account.organizerId, { name: secret });
    await api.addLineupEntry(event.eventId, { profile_id: announcedProfile.id, billing_tier: 'FEATURED' });
    await api.addLineupEntry(event.eventId, { profile_id: secretProfile.id, billing_tier: 'HEADLINER', is_hidden: true });

    const people = new PeoplePage(page);
    await people.goto(account.organizerId);
    await expect(people.row(secret)).toContainText(event.title);
    await page.getByRole('button', { name: /On live events/ }).click();

    await expect(people.row(announced)).toBeVisible();
    await expect(people.row(secret)).toHaveCount(0);
  });

  test('the import preview takes focus and keeps comma names as one person', async ({ authedPage: page, api, account }, testInfo) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    const csvPath = testInfo.outputPath('schedule.csv');
    await writeFile(csvPath, [
      'Date,Start,End,Title,People',
      '2030-06-14,19:00,20:00,,"Alice, Bob"',
      '2030-06-14,20:00,21:00,Panel,Carol; Dan',
    ].join('\n'));

    const lineup = new LineupPage(page);
    await lineup.goto(event.eventId);

    await lineup.openImport();
    await expect(lineup.importDialog().getByRole('button', { name: 'Choose a spreadsheet to import' })).toBeVisible();
    await lineup.importDialog().locator('input[type="file"]').setInputFiles(csvPath);

    await expect(lineup.importDialog().getByTestId('lineup-import-summary')).toBeFocused();
    await expect(lineup.importDialog().getByTestId('lineup-import-summary')).toHaveAccessibleName(/people to add.*time slots to add.*rows to fix/);
    await expect(lineup.importRow('Alice, Bob')).toContainText('Treated as one person. Separate several people with a semicolon.');
    await expect(lineup.importRow('Panel')).toContainText('Carol; Dan');
    await expect(lineup.importRow('Panel')).toContainText('Creates new profiles for Carol; Dan');
    await expect(lineup.importRow('Panel')).not.toContainText('Treated as one person');
  });
});
