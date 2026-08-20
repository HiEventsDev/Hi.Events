import { test, expect } from '../../fixtures';
import { AdminPage } from '../../pages/admin.page';

test.describe('admin account verification', () => {
  test('a superadmin verifies an account and the state persists', { tag: '@admin' }, async ({ superAdminPage, freshAccount, adminApi }) => {
    const accountId = await adminApi.findAccountIdByEmail(freshAccount.email);

    const admin = new AdminPage(superAdminPage);
    await admin.gotoAccount(accountId);

    await expect(superAdminPage.getByText('Verification')).toBeVisible();
    await expect(admin.verificationSwitch()).not.toBeChecked();

    await admin.verificationSwitch().click();

    await expect(superAdminPage.getByText('Account marked as verified')).toBeVisible();
    await expect(admin.verificationSwitch()).toBeChecked();

    await superAdminPage.reload();
    await superAdminPage.waitForLoadState('networkidle');
    await expect(admin.verificationSwitch()).toBeChecked();
  });

  test('a superadmin revokes verification', { tag: '@admin' }, async ({ superAdminPage, freshAccount, adminApi }) => {
    const accountId = await adminApi.findAccountIdByEmail(freshAccount.email);
    await adminApi.setAccountVerification(accountId, true);

    const admin = new AdminPage(superAdminPage);
    await admin.gotoAccount(accountId);
    await expect(admin.verificationSwitch()).toBeChecked();

    await admin.verificationSwitch().click();

    await expect(superAdminPage.getByText('Account verification revoked')).toBeVisible();
    await expect(admin.verificationSwitch()).not.toBeChecked();

    await superAdminPage.reload();
    await superAdminPage.waitForLoadState('networkidle');
    await expect(admin.verificationSwitch()).not.toBeChecked();
  });
});
