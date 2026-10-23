import { test, expect } from '../../fixtures';
import { createFreshOrganizer, createLiveEventWithFreeTicket } from '../../api/factory';
import { PublicEventPage } from '../../pages/public-event.page';
import type { ApiClient } from '../../api/api-client';

const showMoreEvents = (api: ApiClient, eventId: number) => api.updateEventSettings(eventId, {
  homepage_sections: [
    { id: 'tickets', type: 'TICKETS', enabled: true },
    { id: 'more_events', type: 'MORE_EVENTS', enabled: true },
  ],
});

test.describe('more from the organizer', () => {
  test('an event page lists the organizer’s other upcoming events and opens them', async ({ page, api }) => {
    const organizer = await createFreshOrganizer(api);
    await api.updateOrganizerStatus(organizer.id, 'LIVE');
    const current = await createLiveEventWithFreeTicket(api, organizer.id);
    const other = await createLiveEventWithFreeTicket(api, organizer.id);
    await showMoreEvents(api, current.eventId);

    const eventPage = new PublicEventPage(page);
    await eventPage.goto(current.eventId, current.slug);

    const section = eventPage.moreEventsSection();
    await expect(section.getByRole('heading', { name: `More from ${organizer.name}`, level: 2 })).toBeVisible();
    await expect(section.getByRole('link', { name: other.title })).toBeVisible();
    await expect(section.getByRole('link', { name: current.title })).toHaveCount(0);
    await expect(section.getByRole('link', { name: 'View all' })).toHaveAttribute('href', new RegExp(`/events/${organizer.id}/`));

    await section.getByRole('link', { name: other.title }).click();
    await expect(page).toHaveURL(new RegExp(`/event/${other.eventId}/`));
    await expect(page.getByRole('heading', { name: other.title, level: 1 })).toBeVisible();
  });

  test('an organizer with no other upcoming events gets no section', async ({ page, api }) => {
    const organizer = await createFreshOrganizer(api);
    await api.updateOrganizerStatus(organizer.id, 'LIVE');
    const only = await createLiveEventWithFreeTicket(api, organizer.id);
    await showMoreEvents(api, only.eventId);

    const eventPage = new PublicEventPage(page);
    await eventPage.goto(only.eventId, only.slug);

    await expect(page.getByRole('heading', { name: only.title, level: 1 })).toBeVisible();
    await expect(eventPage.moreEventsSection()).toHaveCount(0);
  });

  test('the section stays hidden until the organizer turns it on', async ({ page, api }) => {
    const organizer = await createFreshOrganizer(api);
    await api.updateOrganizerStatus(organizer.id, 'LIVE');
    const current = await createLiveEventWithFreeTicket(api, organizer.id);
    await createLiveEventWithFreeTicket(api, organizer.id);

    const eventPage = new PublicEventPage(page);
    await eventPage.goto(current.eventId, current.slug);

    await expect(page.getByRole('heading', { name: current.title, level: 1 })).toBeVisible();
    await expect(eventPage.moreEventsSection()).toHaveCount(0);
  });
});
