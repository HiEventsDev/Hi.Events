import { test, expect } from '../../fixtures';
import { PublicEventPage } from '../../pages/public-event.page';
import { createFreshOrganizer, createPastEventWithCoverImage } from '../../api/factory';
import { uniqueName } from '../../utils/unique';

test.describe('past event page', () => {
  test('a visitor sees that the event has ended and no way to buy', async ({ page, api }) => {
    const organizer = await createFreshOrganizer(api, uniqueName('E2E Past Org'));
    await api.updateOrganizerStatus(organizer.id, 'LIVE');
    const event = await createPastEventWithCoverImage(api, organizer.id, { title: uniqueName('E2E Past Event') });

    const publicPage = new PublicEventPage(page);
    await publicPage.goto(event.eventId, event.slug);

    await expect(page.getByRole('heading', { name: event.title })).toBeVisible();
    await expect(page.getByText('This event has ended', { exact: true })).toBeVisible();
    await expect(page.getByRole('region', { name: 'Tickets' })).toHaveCount(0);
    await expect(page.getByText(event.productTitle)).toHaveCount(0);
    await expect(page.getByRole('button', { name: 'Get Tickets' })).toHaveCount(0);
  });

  test('a visitor sees that a recurring event whose dates have all passed has ended', async ({ page, api }) => {
    const organizer = await createFreshOrganizer(api, uniqueName('E2E Past Recurring Org'));
    await api.updateOrganizerStatus(organizer.id, 'LIVE');
    const event = await createPastEventWithCoverImage(api, organizer.id, {
      title: uniqueName('E2E Past Recurring Event'),
      eventType: 'RECURRING',
    });

    const publicPage = new PublicEventPage(page);
    await publicPage.goto(event.eventId, event.slug);

    await expect(page.getByRole('heading', { name: event.title })).toBeVisible();
    await expect(page.getByText('This event has ended', { exact: true })).toBeVisible();
  });
});
