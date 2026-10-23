import { test, expect } from '../../fixtures';
import { AdminPage } from '../../pages/admin.page';

test.describe('admin account suspension', () => {
  test('a superadmin suspends an account and unsuspends it', { tag: '@admin' }, async ({ superAdminPage, freshAccount, adminApi }) => {
    const accountId = await adminApi.findAccountIdByEmail(freshAccount.email);

    const admin = new AdminPage(superAdminPage);
    await admin.gotoAccount(accountId);

    await admin.suspendAccount('E2E suspension');

    await expect(superAdminPage.getByText('Account suspended', { exact: true })).toBeVisible();
    await expect(superAdminPage.getByText('Reason: E2E suspension')).toBeVisible();

    await superAdminPage.getByTestId('admin-unsuspend-account-button').click();
    await superAdminPage.getByRole('button', { name: 'Unsuspend', exact: true }).click();

    await expect(superAdminPage.getByText('Account unsuspended')).toBeVisible();
    await expect(superAdminPage.getByTestId('admin-suspend-account-button')).toBeVisible();
  });
});
