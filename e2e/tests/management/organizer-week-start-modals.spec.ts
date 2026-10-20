import { test, expect } from '../../fixtures';
import { createDraftEvent } from '../../api/factory';
import { EventCreatePage } from '../../pages/event-create.page';
import { OrganizerPage } from '../../pages/organizer.page';
import { pickerWeekdayHeaders } from '../../utils/datePicker';
import { uniqueName } from '../../utils/unique';

test.describe('organizer first day of the week on the events dashboard', () => {
  test('the new event date picker starts on the chosen organizer\'s first day of the week', async ({ freshAccount }) => {
    await freshAccount.api.updateOrganizerSettings(freshAccount.organizerId, { first_day_of_week: 0 });
    await freshAccount.api.createOrganizer(uniqueName('E2E Monday Org'));
    const page = await freshAccount.newAuthedPage();

    const create = new EventCreatePage(page);
    await create.gotoDashboard();
    await create.openCreateModal();
    await page.getByTestId('create-event-organizer-select').click();
    await page.getByTestId(`create-event-organizer-select-option-${freshAccount.organizerId}`).click();
    await create.chip('start').click();

    await expect(page.getByTestId('create-event-start-chip-popover').locator('thead th').first()).toHaveText('Su');
  });

  test('the duplicate event date picker starts on the event organizer\'s first day of the week', async ({ freshAccount }) => {
    await freshAccount.api.updateOrganizerSettings(freshAccount.organizerId, { first_day_of_week: 0 });
    await freshAccount.api.createOrganizer(uniqueName('E2E Monday Org'));
    const event = await createDraftEvent(freshAccount.api, freshAccount.organizerId);
    const page = await freshAccount.newAuthedPage();

    const organizer = new OrganizerPage(page);
    await organizer.gotoEventsDashboard();
    await organizer.openEventDuplicateModal(event.title);
    await page.getByRole('dialog').getByLabel(/^Start Date/).click();

    await expect(pickerWeekdayHeaders(page).first()).toHaveText('Su');
  });
});
