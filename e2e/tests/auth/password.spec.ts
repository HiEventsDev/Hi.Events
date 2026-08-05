import { test, expect } from '../../fixtures';

test.describe('password management', () => {
  test('a user changes their password and logs back in with it', async ({ freshAccount }) => {
    const newPassword = 'ChangedPass456!';
    const page = await freshAccount.newAuthedPage();

    await page.goto('/manage/profile');
    await page.waitForLoadState('networkidle');
    await expect(page.getByRole('heading', { name: 'Manage Profile' })).toBeVisible();

    await page.getByRole('tab', { name: 'Password' }).click();
    await page.getByLabel(/^Current Password/).fill(freshAccount.password);
    await page.getByLabel(/^New Password/).fill(newPassword);
    await page.getByLabel(/^Confirm New Password/).fill(newPassword);
    await page.getByRole('button', { name: 'Change password' }).click();
    await expect(page.getByText('Profile updated successfully')).toBeVisible();

    await page.getByRole('button', { name: 'EO', exact: true }).click();
    await page.getByRole('menuitem', { name: 'Logout' }).click();
    await expect(page).toHaveURL(/\/auth\/login/);

    await page.getByLabel(/^Email/).fill(freshAccount.email);
    await page.getByLabel(/^Password/).fill(newPassword);
    await page.getByRole('button', { name: 'Log in' }).click();

    await expect(page).toHaveURL(/\/manage\/organizer\/\d+/);
    await expect(page.getByRole('heading', { level: 1, name: /Dashboard/ })).toBeVisible();
  });

  test('a user resets a forgotten password via email', async ({ freshAccount, page, mailpit }) => {
    const newPassword = 'ResetPass789!';

    await page.goto('/auth/forgot-password');
    await page.waitForLoadState('networkidle');
    await page.getByLabel(/^Email/).fill(freshAccount.email);
    await page.getByRole('button', { name: 'Send reset link' }).click();
    await expect(page.getByRole('heading', { name: 'Check your email' })).toBeVisible();

    const resetUrl = await mailpit.waitForLink(freshAccount.email, /reset-password/, { subjectContains: 'Password reset' });
    await page.goto(resetUrl.pathname);
    await expect(page.getByRole('heading', { name: 'Create new password' })).toBeVisible();

    await page.getByLabel(/^New Password/).fill(newPassword);
    await page.getByLabel(/^Confirm Password/).fill(newPassword);
    await page.getByRole('button', { name: 'Reset password' }).click();
    await expect(page).toHaveURL(/\/auth\/login/);

    await page.getByLabel(/^Email/).fill(freshAccount.email);
    await page.getByLabel(/^Password/).fill(newPassword);
    await page.getByRole('button', { name: 'Log in' }).click();

    await expect(page).toHaveURL(/\/manage\/organizer\/\d+/);
    await expect(page.getByRole('heading', { level: 1, name: /Dashboard/ })).toBeVisible();
  });
});
