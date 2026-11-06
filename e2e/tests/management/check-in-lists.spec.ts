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

  test('an organizer turns on badge printing and kiosk mode for a list', async ({ authedPage, api, account }) => {
    const event = await createDraftEventWithTicket(api, account.organizerId);
    await api.enableBadges(event.eventId);
    const name = uniqueShort('Registration');

    const checkInLists = new CheckInListPage(authedPage);
    await checkInLists.goto(event.eventId);
    await checkInLists.createList(name, { badgePrinting: true, kiosk: true });

    const row = checkInLists.row(name);
    await expect(row.getByText('Prints badges')).toBeVisible();
    await expect(row.getByText('Kiosk')).toBeVisible();

    await checkInLists.openRowAction(name, 'Edit Check-In List');
    const dialog = authedPage.getByRole('dialog');
    await expect(dialog.getByTestId('check-in-list-ledger-kiosk')).toContainText('On');
    await checkInLists.toggleKiosk(dialog);
    await checkInLists.submitEdit();

    await expect(row.getByText('Prints badges')).toBeVisible();
    await expect(row.getByText('Kiosk')).toHaveCount(0);
  });

  test('an organizer turns off the kiosk ticket lookup', async ({ authedPage, api, account }) => {
    const event = await createDraftEventWithTicket(api, account.organizerId);
    const name = uniqueShort('Self check-in');

    const checkInLists = new CheckInListPage(authedPage);
    await checkInLists.goto(event.eventId);
    await checkInLists.createList(name, { kiosk: true });

    await checkInLists.openRowAction(name, 'Edit Check-In List');
    const dialog = authedPage.getByRole('dialog');
    await checkInLists.toggleKioskLookup(dialog);
    await checkInLists.submitEdit();

    await checkInLists.openRowAction(name, 'Edit Check-In List');
    await dialog.getByTestId('check-in-list-ledger-kiosk').click();
    await expect(dialog.getByTestId('check-in-list-kiosk-switch')).toBeChecked();
    await expect(dialog.getByTestId('check-in-list-kiosk-lookup-switch')).not.toBeChecked();
  });
});
