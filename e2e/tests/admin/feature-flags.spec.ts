import { test, expect } from '../../fixtures';
import { AdminPage } from '../../pages/admin.page';
import { simulateLicence, withLicence } from '../../utils/licence';

test.describe('admin feature flags', () => {
  test('a superadmin overrides a flag for an account and removes it from the flags page', { tag: ['@admin', '@smoke'] }, async ({ superAdminPage, superAdminAuth, freshAccount, adminApi, playwright }) => {
    const cloud = await withLicence(playwright, superAdminAuth!.token, 'CLOUD', api => api.getMe());
    test.skip(cloud.licence.status !== 'ACTIVE', 'The backend ignores licence simulation (APP_ENV is not testing or e2e), so it cannot act as Hi.Events Cloud.');
    const accountId = await adminApi.findAccountIdByEmail(freshAccount.email);
    await simulateLicence(superAdminPage, 'CLOUD');

    const admin = new AdminPage(superAdminPage);
    await admin.gotoAccount(accountId);

    await expect(admin.accountFeatureFlag('seating')).toBeVisible();
    await admin.setAccountFeatureFlag('seating', 'On');
    await expect(superAdminPage.getByText('Feature flag updated')).toBeVisible();

    await admin.gotoFeatureFlags();
    await admin.expandFeatureFlagOverrides('seating');

    const row = admin.featureFlagOverrideRow(accountId);
    await expect(row).toBeVisible();
    await expect(row.getByText('On', { exact: true })).toBeVisible();

    await row.getByRole('button', { name: 'Remove override' }).click();
    await expect(superAdminPage.getByText('Override removed')).toBeVisible();
    await expect(row).toBeHidden();

    await admin.gotoAccount(accountId);
    await expect(admin.accountFeatureFlag('seating').locator('input[value="default"]')).toBeChecked();
  });

  test('outside Hi.Events Cloud the licence alone decides, so there are no flags to manage', { tag: '@admin' }, async ({ superAdminPage, freshAccount, adminApi }) => {
    const accountId = await adminApi.findAccountIdByEmail(freshAccount.email);
    await simulateLicence(superAdminPage, 'ACTIVE');

    const admin = new AdminPage(superAdminPage);
    await admin.gotoAccount(accountId);

    await expect(superAdminPage.getByRole('link', { name: 'Licence', exact: true })).toBeVisible();
    await expect(superAdminPage.getByRole('link', { name: 'Feature Flags', exact: true })).toHaveCount(0);
    await expect(admin.accountFeatureFlag('seating')).toHaveCount(0);
  });
});
