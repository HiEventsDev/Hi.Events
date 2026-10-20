import { test, expect } from '../../fixtures';
import { CheckInListPage } from '../../pages/check-in-list.page';
import { createDraftEventWithTicket } from '../../api/factory';
import { uniqueShort } from '../../utils/unique';

test.describe('check-in lists', () => {
  test('an organizer creates a check-in list and sees it in the table', { tag: '@smoke' }, async ({ authedPage, api, account }) => {
    const event = await createDraftEventWithTicket(api, account.organizerId);
    const name = uniqueShort('Main Door');

    const checkInLists = new CheckInListPage(authedPage);
    await checkInLists.goto(event.eventId);
    await checkInLists.createList(name);

    const row = authedPage.getByRole('row').filter({ hasText: name });
    await expect(row).toBeVisible();
    await expect(row.getByText('Active')).toBeVisible();
  });
});
