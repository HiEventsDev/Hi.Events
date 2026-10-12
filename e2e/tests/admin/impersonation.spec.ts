import { test, expect } from '../../fixtures';
import { createLiveEventWithProduct } from '../../api/factory';
import { uniqueName } from '../../utils/unique';
import { AdminPage } from '../../pages/admin.page';

test.describe('admin impersonation', () => {
  test('a superadmin impersonates an organizer and returns to the admin context', { tag: '@admin' }, async ({ freshAccount, superAdminPage }) => {
    const event = await createLiveEventWithProduct(freshAccount.api, {
      organizerId: freshAccount.organizerId,
      title: uniqueName('Impersonated Event'),
    });

    await superAdminPage.goto('/admin/accounts');
    await superAdminPage.waitForLoadState('networkidle');
    await superAdminPage.getByPlaceholder(/^Search by account name/).fill(freshAccount.email);

    await expect(superAdminPage.getByText(freshAccount.email).first()).toBeVisible();
    const impersonateButton = superAdminPage.getByTestId('admin-impersonate-menu-item');
    await expect(impersonateButton).toHaveCount(1);
    await impersonateButton.click();

    await expect(superAdminPage.getByText(/You are impersonating/)).toBeVisible();
    await expect(superAdminPage.getByRole('button', { name: 'Stop Impersonating' })).toBeVisible();
    await expect(superAdminPage.getByRole('link', { name: new RegExp(event.title) })).toBeVisible();

    await superAdminPage.getByRole('button', { name: 'Stop Impersonating' }).click();
    await expect(superAdminPage.getByText(/You are impersonating/)).toHaveCount(0);
    await expect(superAdminPage.getByRole('heading', { name: 'Users', exact: true })).toBeVisible();

    await superAdminPage.goto('/admin');
    await superAdminPage.waitForLoadState('networkidle');
    await expect(superAdminPage.getByRole('heading', { name: 'Dashboard', exact: true })).toBeVisible();
  });

  test('a superadmin impersonates a user from the account page', { tag: '@admin' }, async ({ freshAccount, superAdminPage, adminApi }) => {
    const accountId = await adminApi.findAccountIdByEmail(freshAccount.email);

    const admin = new AdminPage(superAdminPage);
    await admin.gotoAccount(accountId);
    await admin.accountImpersonateButtons().first().click();

    await expect(superAdminPage.getByText(/You are impersonating/)).toBeVisible();
    await superAdminPage.getByRole('button', { name: 'Stop Impersonating' }).click();
    await expect(superAdminPage.getByText(/You are impersonating/)).toHaveCount(0);
  });
});
