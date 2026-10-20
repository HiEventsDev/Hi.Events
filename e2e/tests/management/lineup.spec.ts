import { fileURLToPath } from 'node:url';
import { test, expect } from '../../fixtures';
import { createLiveEventWithFreeTicket } from '../../api/factory';
import { LineupPage } from '../../pages/lineup.page';
import { PeoplePage } from '../../pages/people.page';
import { PublicEventPage } from '../../pages/public-event.page';
import { uniqueName } from '../../utils/unique';

const PHOTO = fileURLToPath(new URL('../../fixtures/assets/event-cover.png', import.meta.url));

test.describe('event lineup and schedule', () => {
  test('an organizer builds a lineup and timetable that shows on the event page', { tag: '@smoke' }, async ({ authedPage: page, api, account }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    const name = uniqueName('Nova Lights');
    const lineup = new LineupPage(page);

    await lineup.goto(event.eventId);
    await lineup.addNewPerson(name);
    await expect(page.getByText(`${name} added to the lineup`)).toBeVisible();

    await lineup.fillEventDetails({ billing: 'Headliner', role: 'Closing set' });
    await lineup.fillProfile({ tagline: 'Berlin techno', link: 'https://open.spotify.com/artist/4tZwfgrHOc3mvqYlEYSvVi' });
    await lineup.uploadPhoto(PHOTO);
    await expect(lineup.drawer().getByText('Main photo')).toBeVisible();
    await lineup.save();

    await expect(lineup.person(name)).toContainText('Headliner');
    await expect(lineup.person(name)).toContainText('Not scheduled');

    await lineup.openView('List');
    await lineup.addStage('Main Stage');
    await expect(page.getByTestId('lineup-add-slot-button')).toBeEnabled();
    await lineup.addTimeSlot({ person: name, date: '2030-06-14', starts: '22:00', ends: '23:30' });
    await expect(page.getByText('Time slot added')).toBeVisible();
    await expect(page.getByTestId('lineup-agenda')).toContainText(name);
    await expect(lineup.person(name)).toContainText('1 slot');

    await lineup.openView('Timetable');
    await lineup.stepTimetable('Next');
    await lineup.stepTimetable('Previous');
    await expect(page.getByRole('heading', { name: 'Lineup & Schedule' })).toBeVisible();
    await expect(page.getByText(name).first()).toBeVisible();

    const publicPage = new PublicEventPage(page);
    await publicPage.goto(event.eventId, event.slug);

    const lineupSection = page.getByTestId('event-lineup-section');
    await expect(lineupSection.getByRole('heading', { name: 'Lineup' })).toBeVisible();
    await expect(lineupSection).toContainText(name);
    await expect(lineupSection).toContainText('Closing set');

    const slot = page.getByTestId('event-schedule-item').filter({ hasText: name });
    await expect(slot).toContainText('Main Stage');
    await expect(slot).toContainText('10:00 PM');

    await lineupSection.getByRole('button', { name: `About ${name}` }).click();
    const profile = page.getByRole('dialog', { name });
    await expect(profile.getByRole('heading', { name, level: 3 })).toBeVisible();
    await expect(profile).toContainText('Berlin techno');
    await expect(profile.getByRole('link', { name: 'Spotify' })).toHaveAttribute('href', /open\.spotify\.com/);
  });

  test('a profile saved in the people library can be added to an event', async ({ authedPage: page, api, account }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    const name = uniqueName('Ada Byron');

    const people = new PeoplePage(page);
    await people.goto(account.organizerId);
    await people.createProfile({ name, tagline: 'CTO, Analytical Engines' });
    await expect(page.getByText('Profile created')).toBeVisible();
    await page.getByRole('dialog').getByRole('button', { name: 'Close' }).click();
    await expect(people.row(name)).toContainText('Not on any event');

    const lineup = new LineupPage(page);
    await lineup.goto(event.eventId);
    await lineup.addSavedPerson(name);
    await expect(lineup.person(name)).toBeVisible();

    await people.goto(account.organizerId);
    await expect(people.row(name)).toContainText(event.title);
  });

  test('people hidden from the lineup stay off the public event page', async ({ page, api, account }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    const announced = uniqueName('Announced');
    const secret = uniqueName('Secret Guest');

    const announcedProfile = await api.createLineupProfile(account.organizerId, { name: announced });
    const secretProfile = await api.createLineupProfile(account.organizerId, { name: secret });
    await api.addLineupEntry(event.eventId, { profile_id: announcedProfile.id, billing_tier: 'FEATURED' });
    await api.addLineupEntry(event.eventId, { profile_id: secretProfile.id, billing_tier: 'HEADLINER', is_hidden: true });

    await new PublicEventPage(page).goto(event.eventId, event.slug);

    const lineupSection = page.getByTestId('event-lineup-section');
    await expect(lineupSection).toContainText(announced);
    await expect(lineupSection).not.toContainText(secret);
  });

  test('a long profile scrolls inside its popup on a phone and keeps the close button in reach', async ({ page, api, account }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    const name = uniqueName('Headliner');
    const headliner = await api.createLineupProfile(account.organizerId, {
      name,
      bio: Array.from({ length: 12 }, (_, index) => `<p>Chapter ${index + 1} of a biography long enough to run well past the bottom of a phone screen.</p>`).join(''),
      links: ['https://open.spotify.com/artist/4tZwfgrHOc3mvqYlEYSvVi'],
    });
    await api.addLineupEntry(event.eventId, { profile_id: headliner.id, billing_tier: 'HEADLINER' });

    await page.setViewportSize({ width: 390, height: 664 });
    await new PublicEventPage(page).goto(event.eventId, event.slug);
    await page.getByTestId('event-lineup-section').getByRole('button', { name: `About ${name}` }).click();

    const profile = page.getByRole('dialog', { name });
    const lastChapter = profile.getByText('Chapter 12 of a biography');
    await expect(profile.getByRole('heading', { name, level: 3 })).toBeVisible();
    await expect(lastChapter).not.toBeInViewport();

    await profile.getByRole('heading', { name, level: 3 }).hover();
    await page.mouse.wheel(0, 4000);

    await expect(lastChapter).toBeInViewport();
    await expect(profile.getByRole('button', { name: 'Close' })).toBeInViewport();
  });
});
