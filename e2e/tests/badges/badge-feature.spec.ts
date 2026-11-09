import { test, expect } from '../../fixtures';
import { createLiveEventWithFreeTicket } from '../../api/factory';

test.describe('name badges feature switch', () => {
  test('badges stay out of the way until an organizer turns them on for the event', async ({ authedPage, api, account }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    const badgesLink = authedPage.getByRole('link', { name: 'Badges', exact: true });

    await authedPage.goto(`/manage/event/${event.eventId}/attendees`);
    await authedPage.waitForLoadState('networkidle');
    await expect(authedPage.getByTestId('attendees-print-badges-button')).toHaveCount(0);
    await expect(badgesLink).toHaveCount(0);

    await authedPage.goto(`/manage/event/${event.eventId}/settings#features`);
    await authedPage.waitForLoadState('networkidle');
    await authedPage.getByTestId('event-feature-badges-switch').click({ force: true });
    await expect(authedPage.getByText('Name badges turned on')).toBeVisible();
    expect((await api.getEventSettings(event.eventId)).enabled_features).toEqual(['BADGES']);

    await expect(badgesLink).toBeVisible();
    await authedPage.goto(`/manage/event/${event.eventId}/attendees`);
    await authedPage.waitForLoadState('networkidle');
    await expect(authedPage.getByTestId('attendees-print-badges-button')).toBeVisible();
  });
});
