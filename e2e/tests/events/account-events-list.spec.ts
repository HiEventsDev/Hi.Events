import { test, expect } from '../../fixtures';
import { createFreshOrganizer, createLiveEventWithFreeTicket } from '../../api/factory';
import { AccountEventsPage } from '../../pages/account-events.page';

test.describe('account events list', () => {
  test('a multi-organizer account filters its events by organizer and opens one', async ({ freshAccount }) => {
    const secondOrganizer = await createFreshOrganizer(freshAccount.api);
    const firstEvent = await createLiveEventWithFreeTicket(freshAccount.api, freshAccount.organizerId);
    const secondEvent = await createLiveEventWithFreeTicket(freshAccount.api, secondOrganizer.id);
    const page = await freshAccount.newAuthedPage();
    const events = new AccountEventsPage(page);

    await events.goto();
    await expect(events.row(firstEvent.title)).toBeVisible();
    await expect(events.row(secondEvent.title).getByText('On Sale')).toBeVisible();
    await expect(events.row(secondEvent.title).getByRole('link', { name: secondOrganizer.name })).toBeVisible();

    await events.filterByOrganizer(secondOrganizer.name);
    await expect(events.row(secondEvent.title)).toBeVisible();
    await expect(events.row(firstEvent.title)).toHaveCount(0);

    await events.showStatus('Archived');
    await expect(page.getByRole('heading', { name: 'No events to show' })).toBeVisible();

    await events.showStatus('Upcoming');
    await expect(events.row(firstEvent.title)).toHaveCount(0);
    await events.openEvent(secondEvent.title);
    await expect(page).toHaveURL(new RegExp(`/manage/event/${secondEvent.eventId}/dashboard`));
    await expect(page.getByText(secondEvent.title).first()).toBeVisible();
  });
});
