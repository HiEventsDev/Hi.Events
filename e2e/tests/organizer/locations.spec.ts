import type { Page } from '@playwright/test';
import { test, expect } from '../../fixtures';
import { createFreshOrganizer } from '../../api/factory';
import { uniqueName } from '../../utils/unique';

const gotoLocations = async (page: Page, organizerId: number): Promise<void> => {
  await page.goto(`/manage/organizer/${organizerId}/locations`);
  await page.waitForLoadState('networkidle');
};

const createLocationViaUi = async (page: Page, venueName: string): Promise<void> => {
  await page.getByRole('button', { name: 'Add Location' }).first().click();
  const dialog = page.getByRole('dialog');
  await dialog.getByLabel('Venue Name').fill(venueName);
  await dialog.getByLabel('Address Line 1').fill('123 Test Street');
  await dialog.getByLabel('City').fill('Dublin');
  await dialog.getByLabel('Zip or Postal Code').fill('D01 F5P2');
  await dialog.getByRole('button', { name: 'Add Location' }).click();
};

test.describe('organizer locations', () => {
  test('an organizer creates a venue with a manual address', async ({ authedPage, api }) => {
    const organizer = await createFreshOrganizer(api);
    const venueName = uniqueName('Venue');

    await gotoLocations(authedPage, organizer.id);
    await createLocationViaUi(authedPage, venueName);

    const row = authedPage.getByRole('row').filter({ hasText: venueName });
    await expect(row).toBeVisible();
    await expect(row.getByText('123 Test Street', { exact: false })).toBeVisible();
  });

  test('an organizer renames a location', async ({ authedPage, api }) => {
    const organizer = await createFreshOrganizer(api);
    const venueName = uniqueName('Venue');
    const newName = uniqueName('Renamed Venue');

    await gotoLocations(authedPage, organizer.id);
    await createLocationViaUi(authedPage, venueName);

    const row = authedPage.getByRole('row').filter({ hasText: venueName });
    await row.getByRole('button').click();
    await authedPage.getByRole('menuitem', { name: 'Edit location' }).click();

    const dialog = authedPage.getByRole('dialog');
    await expect(dialog.getByLabel('Venue Name')).toHaveValue(venueName);
    await dialog.getByLabel('Display name').fill(newName);
    await dialog.getByRole('button', { name: 'Save Changes' }).click();

    await expect(authedPage.getByRole('row').filter({ hasText: newName })).toBeVisible();
  });

  test('an organizer deletes a location', async ({ authedPage, api }) => {
    const organizer = await createFreshOrganizer(api);
    const venueName = uniqueName('Venue');

    await gotoLocations(authedPage, organizer.id);
    await createLocationViaUi(authedPage, venueName);

    const row = authedPage.getByRole('row').filter({ hasText: venueName });
    await expect(row).toBeVisible();
    await row.getByRole('button').click();
    await authedPage.getByRole('menuitem', { name: 'Delete location' }).click();
    await authedPage.getByRole('button', { name: 'Delete', exact: true }).click();

    await expect(authedPage.getByRole('row').filter({ hasText: venueName })).toHaveCount(0);
    await expect(authedPage.getByText('No Saved Locations')).toBeVisible();
  });
});
