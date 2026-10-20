import { test, expect } from '../../fixtures';
import { CheckInListPage } from '../../pages/check-in-list.page';
import { createDraftEventWithTicket } from '../../api/factory';
import { uniqueShort } from '../../utils/unique';

test.describe('check-in list lifecycle', () => {
  test('an organizer renames a check-in list from the row menu', async ({ authedPage, api, account }) => {
    const event = await createDraftEventWithTicket(api, account.organizerId);
    const name = uniqueShort('Front Gate');
    const newName = uniqueShort('Back Gate');
    await api.createCheckInList(event.eventId, { name });

    const checkInLists = new CheckInListPage(authedPage);
    await checkInLists.goto(event.eventId);
    await checkInLists.openRowAction(name, 'Edit Check-In List');

    await expect(checkInLists.editNameInput()).toHaveValue(name);
    await checkInLists.editNameInput().fill(newName);
    await checkInLists.submitEdit();

    await expect(checkInLists.row(newName)).toBeVisible();
    await expect(checkInLists.row(name)).toHaveCount(0);
  });

  test('an organizer deletes a check-in list from the row menu', async ({ authedPage, api, account }) => {
    const event = await createDraftEventWithTicket(api, account.organizerId);
    const name = uniqueShort('Doomed List');
    await api.createCheckInList(event.eventId, { name });

    const checkInLists = new CheckInListPage(authedPage);
    await checkInLists.goto(event.eventId);

    await expect(checkInLists.row(name)).toBeVisible();
    await checkInLists.openRowAction(name, 'Delete Check-In List');
    await authedPage.getByRole('button', { name: 'Confirm' }).click();

    await expect(checkInLists.row(name)).toHaveCount(0);
  });

  test('a check-in list with a past expiry shows as inactive', async ({ authedPage, api, account }) => {
    const event = await createDraftEventWithTicket(api, account.organizerId);
    const name = uniqueShort('Expired List');
    const yesterday = new Date(Date.now() - 24 * 60 * 60 * 1000).toISOString();
    await api.createCheckInList(event.eventId, { name, expires_at: yesterday });

    const checkInLists = new CheckInListPage(authedPage);
    await checkInLists.goto(event.eventId);

    await expect(checkInLists.row(name).getByText('Inactive')).toBeVisible();
  });
});
