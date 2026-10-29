import { test, expect } from '../../fixtures';
import { createDraftEventWithTicket } from '../../api/factory';

test.describe('widget indexing', () => {
  test('widget pages ask search engines not to index them on their own', async ({ request, api, account }) => {
    const event = await createDraftEventWithTicket(api, account.organizerId);
    const widgetPaths = [
      `/widget/${event.eventId}`,
      `/widget/${event.eventId}/lineup`,
      `/widget/${event.eventId}/schedule`,
      `/widget/organizer/${account.organizerId}`,
    ];

    for (const path of widgetPaths) {
      const response = await request.get(path);
      expect(response.headers()['x-robots-tag'], path).toBe('noindex, indexifembedded');
    }

    const homepage = await request.get(`/event/${event.eventId}/${event.slug}`);
    expect(homepage.headers()['x-robots-tag']).toBeUndefined();
  });
});
