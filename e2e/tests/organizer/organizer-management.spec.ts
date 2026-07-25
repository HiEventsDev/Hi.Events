import { test, expect } from '../../fixtures';
import { OrganizerPage } from '../../pages/organizer.page';
import { createFreshOrganizer } from '../../api/factory';
import { uniqueName } from '../../utils/unique';

test.describe('organizer management', () => {
  test('an organizer creates a second organizer from the global menu', { tag: '@smoke' }, async ({ authedPage, account }) => {
    const organizerName = uniqueName('E2E Second Org');

    const organizer = new OrganizerPage(authedPage);
    await organizer.gotoEventsDashboard();
    await organizer.openCreateOrganizerModal('EO');

    await expect(authedPage.getByRole('heading', { name: 'Create Organizer' })).toBeVisible();
    await expect(organizer.contactEmailInput).toHaveValue(account.email);
    await organizer.organizationNameInput.fill(organizerName);
    await organizer.continueSetupButton.click();

    await expect(authedPage.getByRole('heading', { name: organizerName })).toBeVisible();
  });

  test('an organizer renames their organizer from basic information settings', async ({ authedPage, api }) => {
    const seeded = await createFreshOrganizer(api);
    const newName = uniqueName('E2E Renamed Org');

    const organizer = new OrganizerPage(authedPage);
    await organizer.gotoSettings(seeded.id);

    await expect(organizer.settingsNameInput).toHaveValue(seeded.name);
    await organizer.settingsNameInput.fill(newName);
    await organizer.saveBasicSettings();
    await expect(authedPage.getByText('Successfully Updated Organizer')).toBeVisible();

    await authedPage.reload();
    await authedPage.waitForLoadState('networkidle');
    await expect(organizer.settingsNameInput).toHaveValue(newName);
  });
});
