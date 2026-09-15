import { test, expect } from '../../fixtures';
import { PublicEventPage } from '../../pages/public-event.page';
import { createFreshOrganizer, createLiveEventWithProduct } from '../../api/factory';
import { uniqueName } from '../../utils/unique';

test.describe('user generated links', () => {
  test('an outbound link in a ticket description is rendered as non-followable', async ({ page, api }) => {
    const organizer = await createFreshOrganizer(api, uniqueName('E2E Link Org'));
    await api.updateOrganizerStatus(organizer.id, 'LIVE');

    const event = await createLiveEventWithProduct(api, {
      organizerId: organizer.id,
      title: uniqueName('E2E Link Event'),
      productDescription: '<p>See <a href="https://example.com/offers">our other offers</a>.</p>',
    });

    const publicPage = new PublicEventPage(page);
    await publicPage.goto(event.eventId, event.slug);

    const link = page.locator('a[href="https://example.com/offers"]').first();
    await expect(link).toHaveAttribute('rel', /nofollow/);
    await expect(link).toHaveAttribute('rel', /ugc/);
    await expect(link).toHaveAttribute('target', '_blank');
  });
});
