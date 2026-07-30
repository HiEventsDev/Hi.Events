import { test, expect } from '../../fixtures';

test.describe('account deletion', () => {
  test('an owner requests account deletion and cancels it', async ({ freshAccount }) => {
    const account = await freshAccount.api.getAccount();
    const page = await freshAccount.newAuthedPage();

    await page.goto('/account/danger-zone');
    await page.getByTestId('delete-account-button').click();
    await page.getByLabel('Account name').fill(account.name);
    await page.getByTestId('confirm-account-deletion-button').click();

    await expect(page.getByText('This account is scheduled for deletion', { exact: true })).toBeVisible();
    await expect(page.getByTestId('pending-deletion-banner-button')).toBeVisible();

    await page.getByTestId('cancel-account-deletion-button').click();

    await expect(page.getByTestId('delete-account-button')).toBeVisible();
    await expect(page.getByTestId('pending-deletion-banner-button')).toHaveCount(0);
  });

  test('a superadmin sees a pending deletion request and cancels it', async ({ freshAccount, superAdminPage }) => {
    const account = await freshAccount.api.getAccount();
    await freshAccount.api.requestAccountDeletion(account.name);

    await superAdminPage.goto('/admin/deletion-requests');
    const row = superAdminPage.getByRole('row').filter({ hasText: freshAccount.email });
    await expect(row).toBeVisible();

    await row.getByTestId('admin-cancel-deletion-button').click();
    await superAdminPage.getByTestId('admin-confirm-deletion-action-button').click();

    await expect(superAdminPage.getByText('Deletion request cancelled')).toBeVisible();
    await expect(row).toHaveCount(0);
  });

  test('the confirm button stays disabled until the account name matches', async ({ freshAccount }) => {
    const page = await freshAccount.newAuthedPage();

    await page.goto('/account/danger-zone');
    await page.getByTestId('delete-account-button').click();

    await expect(page.getByTestId('confirm-account-deletion-button')).toBeDisabled();

    await page.getByLabel('Account name').fill('Wrong Name');
    await expect(page.getByTestId('confirm-account-deletion-button')).toBeDisabled();
  });
});
