import { test, expect } from '../../fixtures';
import type { Page } from '@playwright/test';
import { uniqueName } from '../../utils/unique';

const configCard = (page: Page, name: string) =>
  page.locator('[class*="configCard"]').filter({ hasText: name });

const gotoConfigurations = async (page: Page) => {
  await page.goto('/admin/configurations');
  await page.waitForLoadState('networkidle');
};

test.describe('admin configurations', () => {
  test.describe.configure({ mode: 'serial' });

  const originalName = uniqueName('E2E Config');
  const renamedName = uniqueName('E2E Config Renamed');

  test('a superadmin creates a configuration', { tag: '@admin' }, async ({ superAdminPage }) => {
    await gotoConfigurations(superAdminPage);
    await expect(superAdminPage.getByRole('heading', { name: 'Configurations', exact: true })).toBeVisible();

    await superAdminPage.getByRole('button', { name: 'Create Configuration' }).click();

    const dialog = superAdminPage.getByRole('dialog');
    await dialog.getByLabel(/^Name/).fill(originalName);
    await dialog.getByLabel(/^Fixed Fee/).fill('1.50');
    await dialog.getByLabel(/^Percentage Fee/).fill('2.5');
    await dialog.getByRole('button', { name: 'Create Configuration' }).click();

    const card = configCard(superAdminPage, originalName);
    await expect(card).toBeVisible();
    await expect(card.getByText('2.5%')).toBeVisible();
  });

  test('a superadmin edits a configuration', { tag: '@admin' }, async ({ superAdminPage }) => {
    await gotoConfigurations(superAdminPage);

    const card = configCard(superAdminPage, originalName);
    await expect(card).toBeVisible();
    await card.getByRole('button').first().click();

    const dialog = superAdminPage.getByRole('dialog');
    await expect(dialog.getByLabel(/^Name/)).toHaveValue(originalName);
    await dialog.getByLabel(/^Name/).fill(renamedName);
    await dialog.getByLabel(/^Percentage Fee/).fill('5');
    await dialog.getByRole('button', { name: 'Save Changes' }).click();

    const renamedCard = configCard(superAdminPage, renamedName);
    await expect(renamedCard).toBeVisible();
    await expect(renamedCard.getByText('5%')).toBeVisible();
    await expect(configCard(superAdminPage, originalName)).toHaveCount(0);
  });

  test('a superadmin deletes a configuration', { tag: '@admin' }, async ({ superAdminPage }) => {
    await gotoConfigurations(superAdminPage);

    const card = configCard(superAdminPage, renamedName);
    await expect(card).toBeVisible();

    superAdminPage.once('dialog', (dialog) => dialog.accept());
    await card.getByRole('button').last().click();

    await expect(card).toHaveCount(0);
  });
});

test.describe('currency default configurations', () => {
  test(
    'currency defaults show a badge and cannot be deleted',
    { tag: '@admin' },
    async ({ superAdminPage, adminApi }, testInfo) => {
      const configurations = await adminApi.listConfigurations();
      const usdDefault = configurations.find((config) => config.default_for_currency === 'USD');

      testInfo.skip(
        !usdDefault,
        'No currency default configurations seeded — re-run `php artisan dev:bootstrap` against this stack.',
      );

      await gotoConfigurations(superAdminPage);

      const card = configCard(superAdminPage, usdDefault!.name);
      await expect(card).toBeVisible();
      await expect(card.getByText('USD Default')).toBeVisible();
      await expect(card.getByRole('button').last()).toBeDisabled();
    },
  );
});
