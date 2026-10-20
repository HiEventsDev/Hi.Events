import { test, expect } from '../../fixtures';
import { createRecurringLiveEvent } from '../../api/factory';
import { ApiKeysPage } from '../../pages/api-keys.page';
import { TeamPage } from '../../pages/team.page';
import { openAnonymousPage, TwoFactorPage } from '../../pages/two-factor.page';
import { nextTotpCode } from '../../utils/totp';
import { uniqueEmail } from '../../utils/unique';

const MEMBER_PASSWORD = 'MemberPass123!';

test.describe('settings dialogs: focus and names', () => {
  test('a failed identity confirmation puts focus back on the password field', async ({ freshAccount }) => {
    const page = await freshAccount.newAuthedPage();
    await page.goto('/settings/profile');

    await expect(page.getByRole('textbox', { name: 'First name' })).toHaveValue('E2E');
    await page.locator('#profile-email').fill(uniqueEmail('refocus'));
    await page.getByTestId('profile-save-button').click();

    const dialog = page.getByRole('dialog', { name: "Confirm it's you" });
    const password = dialog.getByLabel('Current password');
    await password.fill('definitely-wrong-password');

    const confirm = dialog.getByTestId('confirm-identity-submit');
    await confirm.focus();
    await page.keyboard.press('Enter');

    await expect(dialog.getByText('The password is incorrect.')).toBeVisible();
    await expect(password).toBeFocused();
  });

  test('the create API key modal opens on the name field and its unsaved-key warning names its close button', async ({ freshAccount }) => {
    const page = await freshAccount.newAuthedPage();
    const apiKeys = new ApiKeysPage(page);

    await apiKeys.goto();
    await apiKeys.openCreate();
    await expect(page.getByTestId('api-key-name')).toBeFocused();

    await apiKeys.fillCreateForm({ name: 'Close label check', password: freshAccount.password });
    await apiKeys.submitCreate();
    await page.getByTestId('api-key-done').click();

    const warning = page.getByRole('dialog', { name: /You haven't copied this key/ });
    await expect(warning).toBeVisible();
    await expect(warning.getByRole('button', { name: 'Close', exact: true })).toBeVisible();
  });

  test('notifications name their close button', async ({ freshAccount }) => {
    const page = await freshAccount.newAuthedPage();
    await page.goto('/settings/profile');

    const firstName = page.getByRole('textbox', { name: 'First name' });
    await expect(firstName).toHaveValue('E2E');
    await firstName.fill('E2E Renamed');
    await page.getByTestId('profile-save-button').click();

    const notice = page.getByRole('alert').filter({ hasText: 'Profile updated successfully' });
    await expect(notice.getByRole('button', { name: 'Close', exact: true })).toBeVisible();
  });

  test('team row actions say which member they act on', async ({ freshAccount }) => {
    const page = await freshAccount.newAuthedPage();
    const team = new TeamPage(page);
    await team.goto();

    const row = team.userRow(freshAccount.email);
    await expect(row.getByRole('button', { name: 'Edit E2E Organizer', exact: true })).toBeVisible();
    await expect(row.getByRole('button', { name: 'More actions for E2E Organizer', exact: true })).toBeVisible();
  });

  test('the occurrence drawer is named by its date', async ({ freshAccount }) => {
    const event = await createRecurringLiveEvent(freshAccount.api, freshAccount.organizerId, { count: 2 });
    const page = await freshAccount.newAuthedPage();

    await page.goto(`/manage/event/${event.eventId}/occurrences`);
    await page.locator('[class*="dateTimeLink"]').first().click();

    const drawer = page.getByRole('dialog').filter({ has: page.locator('[data-status]') });
    await expect(drawer).toBeVisible();
    const name = await drawer.evaluate((element) => {
      const labelledBy = element.getAttribute('aria-labelledby');
      return labelledBy ? document.getElementById(labelledBy)?.textContent?.trim() ?? '' : '';
    });
    expect(name.length).toBeGreaterThan(0);
  });

  test('two-factor setup opens on the password field in the security dialog', async ({ freshAccount }) => {
    const page = await freshAccount.newAuthedPage();
    const security = new TwoFactorPage(page);
    await security.gotoSecurity();

    await page.getByTestId('two-factor-enable-button').click();
    await expect(page.getByTestId('two-factor-setup-password')).toBeFocused();
  });

  test('the required two-factor setup page opens on the password field', async ({ freshAccount, browser, mailpit, publicApi }) => {
    const { secret } = await freshAccount.api.beginTwoFactorSetup(freshAccount.password);
    await freshAccount.api.confirmTwoFactorSetup(await nextTotpCode(secret));

    const ownerPage = await freshAccount.newAuthedPage();
    const team = new TeamPage(ownerPage);
    await team.goto();
    const requireSwitch = ownerPage.getByTestId('account-require-two-factor-switch');
    await requireSwitch.click({ force: true });
    await expect(requireSwitch).toBeChecked();

    const memberEmail = uniqueEmail('2fa-focus');
    await freshAccount.api.inviteUser({ first_name: 'Morgan', last_name: 'Member', email: memberEmail, role: 'ORGANIZER' });
    const inviteUrl = await mailpit.waitForLink(memberEmail, /accept-invitation/, { subjectContains: 'invited to join' });
    const acceptResponse = await publicApi.post(`auth/invitation/${inviteUrl.pathname.split('/').pop()}`, {
      data: {
        first_name: 'Morgan',
        last_name: 'Member',
        password: MEMBER_PASSWORD,
        password_confirmation: MEMBER_PASSWORD,
        timezone: 'UTC',
      },
    });
    expect(acceptResponse.ok()).toBeTruthy();

    const memberPage = await openAnonymousPage(browser);
    await new TwoFactorPage(memberPage).signInWithPassword(memberEmail, MEMBER_PASSWORD);
    await expect(memberPage).toHaveURL(/\/auth\/two-factor-setup/);
    await expect(memberPage.getByTestId('two-factor-setup-password')).toBeFocused();

    await memberPage.reload();
    await expect(memberPage.getByTestId('two-factor-setup-password')).toBeFocused();
    await memberPage.context().close();
  });
});
