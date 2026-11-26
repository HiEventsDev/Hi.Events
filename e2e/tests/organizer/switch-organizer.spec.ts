import { test, expect } from '../../fixtures';
import { createFreshOrganizer } from '../../api/factory';
import { OrganizerPage } from '../../pages/organizer.page';
import { uniqueName } from '../../utils/unique';

test.describe('switch organizer', () => {
  test('an account with many organizers searches for one and switches to it', async ({ freshAccount }) => {
    const target = await createFreshOrganizer(freshAccount.api, uniqueName('Zephyr Collective'));
    const archived = await createFreshOrganizer(freshAccount.api, uniqueName('Zephyr Retired'));
    await freshAccount.api.updateOrganizerStatus(archived.id, 'ARCHIVED');
    for (let index = 0; index < 4; index++) {
      await createFreshOrganizer(freshAccount.api);
    }
    const page = await freshAccount.newAuthedPage();
    const organizer = new OrganizerPage(page);

    await page.goto(`/manage/organizer/${freshAccount.organizerId}/dashboard`);
    await organizer.openSwitcher();
    await expect(organizer.switcherOption(target.name)).toBeVisible();
    await expect(organizer.switcherOption(archived.name)).toHaveCount(0);

    await organizer.switcherSearch.fill('zephyr');
    await expect(organizer.switcherOption(target.name)).toBeVisible();
    await expect(organizer.switcherOption(archived.name)).toContainText('Archived');
    await expect(page.getByRole('dialog').getByRole('button', { name: /E2E/ })).toHaveCount(0);

    await organizer.switcherOption(target.name).click();
    await expect(page).toHaveURL(new RegExp(`/manage/organizer/${target.id}/dashboard`));
    await expect(page.getByRole('heading', { name: new RegExp(target.name) })).toBeVisible();
  });
});
