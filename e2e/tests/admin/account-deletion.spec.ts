import { test, expect } from '../../fixtures';
import { AdminPage } from '../../pages/admin.page';

test.describe('admin account deletion', () => {
  test('a superadmin schedules an account deletion and cancels it', { tag: '@admin' }, async ({ superAdminPage, freshAccount, adminApi }) => {
    const accountId = await adminApi.findAccountIdByEmail(freshAccount.email);

    const admin = new AdminPage(superAdminPage);
    await admin.gotoAccount(accountId);
    const accountName = (await superAdminPage.getByRole('heading', { level: 1 }).textContent())!.trim();

    await admin.scheduleAccountDeletion(accountName, 'E2E scheduled deletion');

    await expect(superAdminPage.getByText('Account scheduled for deletion in 30 days')).toBeVisible();
    await expect(superAdminPage.getByText(/Deletion is scheduled for/)).toBeVisible();

    await superAdminPage.getByRole('button', { name: 'Cancel deletion' }).click();

    await expect(superAdminPage.getByText('Account deletion cancelled')).toBeVisible();
    await expect(superAdminPage.getByTestId('admin-delete-account-button')).toBeVisible();
  });
});
