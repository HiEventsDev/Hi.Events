import { test, expect } from '../../fixtures';
import { openAnonymousPage, TwoFactorPage } from '../../pages/two-factor.page';
import { TeamPage } from '../../pages/team.page';
import { nextTotpCode } from '../../utils/totp';
import { uniqueEmail } from '../../utils/unique';
import type { FreshAccount } from '../../fixtures';
import { ApiClient, login } from '../../api/api-client';
import { API_BASE_URL } from '../../utils/env';

const MEMBER_PASSWORD = 'MemberPass123!';

const enableTwoFactorViaApi = async (account: FreshAccount): Promise<{ secret: string; recoveryCodes: string[] }> => {
  const { secret } = await account.api.beginTwoFactorSetup(account.password);
  const { recovery_codes } = await account.api.confirmTwoFactorSetup(await nextTotpCode(secret));
  return { secret, recoveryCodes: recovery_codes };
};

test.describe('two-factor authentication', () => {
  test('a user turns on two-factor from their profile and signs in with a code', { tag: '@smoke' }, async ({ freshAccount, browser }) => {
    const profilePage = await freshAccount.newAuthedPage();
    const profile = new TwoFactorPage(profilePage);
    await profile.gotoSecurity();
    await expect(profile.status()).toHaveText('Off');

    const { secret, recoveryCodes } = await profile.enable(freshAccount.password);

    expect(recoveryCodes).toHaveLength(10);
    expect(new Set(recoveryCodes).size).toBe(10);
    await expect(profile.status()).toHaveText('On');
    await expect(profilePage.getByTestId('two-factor-recovery-remaining')).toHaveText('10 of 10 remaining');

    const loginPage = await openAnonymousPage(browser);
    const login = new TwoFactorPage(loginPage);
    await login.signInWithPassword(freshAccount.email, freshAccount.password);
    await login.expectChallenge();

    await login.enterLoginCode('000000');
    await expect(loginPage.getByRole('alert')).toContainText('That code is not valid');

    await login.enterLoginCode(await nextTotpCode(secret));
    await login.expectDashboard();
    await loginPage.context().close();
  });

  test('a recovery code signs the user in once and the remaining count drops', async ({ freshAccount, browser }) => {
    const { recoveryCodes } = await enableTwoFactorViaApi(freshAccount);

    const page = await openAnonymousPage(browser);
    const login = new TwoFactorPage(page);
    await login.signInWithPassword(freshAccount.email, freshAccount.password);
    await login.expectChallenge();
    await login.enterRecoveryCode(recoveryCodes[0].toUpperCase());

    const notice = page.getByTestId('two-factor-recovery-notice');
    await expect(notice).toContainText('You have 9 recovery codes left');
    await page.getByTestId('two-factor-manage-codes').click();

    await expect(page).toHaveURL(/\/manage\/profile\/security/);
    await expect(page.getByTestId('two-factor-recovery-remaining')).toHaveText('9 of 10 remaining');
    await page.context().close();

    const secondAttempt = await openAnonymousPage(browser);
    const retry = new TwoFactorPage(secondAttempt);
    await retry.signInWithPassword(freshAccount.email, freshAccount.password);
    await retry.expectChallenge();
    await retry.enterRecoveryCode(recoveryCodes[0]);
    await expect(secondAttempt.getByText('That recovery code is not valid or has already been used.')).toBeVisible();
    await secondAttempt.context().close();
  });

  test('a trusted device skips the code until it is removed', async ({ freshAccount, browser }) => {
    const { secret } = await enableTwoFactorViaApi(freshAccount);

    const page = await openAnonymousPage(browser);
    const twoFactor = new TwoFactorPage(page);
    await twoFactor.signInWithPassword(freshAccount.email, freshAccount.password);
    await twoFactor.expectChallenge();
    await twoFactor.enterLoginCode(await nextTotpCode(secret), { trustDevice: true });
    await twoFactor.expectDashboard();

    await page.context().clearCookies({ name: 'token' });
    await twoFactor.signInWithPassword(freshAccount.email, freshAccount.password);
    await twoFactor.expectDashboard();

    await twoFactor.gotoSecurity();
    const device = page.getByTestId('two-factor-trusted-device');
    await expect(device).toHaveCount(1);
    await expect(device).toContainText('This device');
    await page.getByTestId('two-factor-revoke-devices').click();
    await page.getByRole('button', { name: 'Confirm' }).click();
    await expect(device).toHaveCount(0);

    await page.context().clearCookies({ name: 'token' });
    await twoFactor.signInWithPassword(freshAccount.email, freshAccount.password);
    await twoFactor.expectChallenge();
    await page.context().close();
  });

  test('regenerating recovery codes replaces the old set', async ({ freshAccount, browser }) => {
    const { secret, recoveryCodes: oldCodes } = await enableTwoFactorViaApi(freshAccount);

    const profilePage = await freshAccount.newAuthedPage();
    const profile = new TwoFactorPage(profilePage);
    await profile.gotoSecurity();
    await profilePage.getByTestId('two-factor-regenerate-button').click();
    await profilePage.getByTestId('two-factor-regenerate-code').fill(await nextTotpCode(secret));

    const codes = profilePage.getByTestId('two-factor-recovery-codes').getByRole('listitem');
    await expect(codes).toHaveCount(10);
    const newCodes = (await codes.allTextContents()).map((code) => code.trim());
    expect(newCodes).not.toContain(oldCodes[0]);
    await profilePage.getByText('I have saved my recovery codes somewhere safe').click();
    await profilePage.getByTestId('two-factor-regenerate-done').click();

    const loginPage = await openAnonymousPage(browser);
    const login = new TwoFactorPage(loginPage);
    await login.signInWithPassword(freshAccount.email, freshAccount.password);
    await login.expectChallenge();
    await login.enterRecoveryCode(oldCodes[0]);
    await expect(loginPage.getByText('That recovery code is not valid or has already been used.')).toBeVisible();

    await loginPage.getByTestId('two-factor-login-recovery-code').fill(newCodes[0]);
    await loginPage.getByTestId('two-factor-login-submit').click();
    await expect(loginPage.getByTestId('two-factor-recovery-notice')).toBeVisible();
    await loginPage.context().close();
  });

  test('a user turns two-factor off with their password and a code', async ({ freshAccount, browser }) => {
    const { secret } = await enableTwoFactorViaApi(freshAccount);

    const profilePage = await freshAccount.newAuthedPage();
    const profile = new TwoFactorPage(profilePage);
    await profile.gotoSecurity();
    await expect(profile.status()).toHaveText('On');

    await profilePage.getByTestId('two-factor-disable-button').click();
    await profilePage.getByTestId('two-factor-disable-password').fill('not-my-password');
    await profilePage.getByTestId('two-factor-disable-code').fill(await nextTotpCode(secret));
    await profilePage.getByTestId('two-factor-disable-submit').click();
    await expect(profilePage.getByText('The password is incorrect.')).toBeVisible();

    await profilePage.getByTestId('two-factor-disable-password').fill(freshAccount.password);
    await profilePage.getByTestId('two-factor-disable-code').fill(await nextTotpCode(secret));
    await profilePage.getByTestId('two-factor-disable-submit').click();
    await expect(profile.status()).toHaveText('Off');

    const loginPage = await openAnonymousPage(browser);
    const login = new TwoFactorPage(loginPage);
    await login.signInWithPassword(freshAccount.email, freshAccount.password);
    await login.expectDashboard();
    await loginPage.context().close();
  });

  test('an account that requires two-factor makes new members set it up before continuing', async ({ freshAccount, browser, mailpit, publicApi }) => {
    await enableTwoFactorViaApi(freshAccount);

    const ownerPage = await freshAccount.newAuthedPage();
    const team = new TeamPage(ownerPage);
    await team.goto();
    const requireSwitch = ownerPage.getByTestId('account-require-two-factor-switch');
    await expect(requireSwitch).not.toBeChecked();
    await requireSwitch.click({ force: true });
    await expect(ownerPage.getByText('Two-factor authentication is now required')).toBeVisible();
    await expect(requireSwitch).toBeChecked();

    const memberEmail = uniqueEmail('2fa-member');
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
    const member = new TwoFactorPage(memberPage);
    await member.signInWithPassword(memberEmail, MEMBER_PASSWORD);
    await expect(memberPage).toHaveURL(/\/auth\/two-factor-setup/);
    await expect(memberPage.getByRole('heading', { name: 'Secure your account' })).toBeVisible();

    await member.completeSetupFlow(MEMBER_PASSWORD);
    await expect(memberPage).not.toHaveURL(/two-factor-setup/);
    await expect(memberPage.getByRole('heading', { level: 1 })).toBeVisible();

    await team.goto();
    await expect(team.userRow(memberEmail).getByText('On', { exact: true })).toBeVisible();
    await memberPage.context().close();
  });

  test('an account admin resets two-factor for a team member who lost their phone', async ({ freshAccount, browser, mailpit, publicApi, playwright }) => {
    const memberEmail = uniqueEmail('2fa-reset');
    await freshAccount.api.inviteUser({ first_name: 'Riley', last_name: 'Member', email: memberEmail, role: 'ORGANIZER' });
    const inviteUrl = await mailpit.waitForLink(memberEmail, /accept-invitation/, { subjectContains: 'invited to join' });
    const acceptResponse = await publicApi.post(`auth/invitation/${inviteUrl.pathname.split('/').pop()}`, {
      data: {
        first_name: 'Riley',
        last_name: 'Member',
        password: MEMBER_PASSWORD,
        password_confirmation: MEMBER_PASSWORD,
        timezone: 'UTC',
      },
    });
    expect(acceptResponse.ok()).toBeTruthy();

    const { token } = await login(publicApi, memberEmail, MEMBER_PASSWORD);
    const memberContext = await playwright.request.newContext({
      baseURL: API_BASE_URL,
      ignoreHTTPSErrors: true,
      extraHTTPHeaders: { Accept: 'application/json', Authorization: `Bearer ${token}` },
    });
    const memberApi = new ApiClient(memberContext);
    const { secret } = await memberApi.beginTwoFactorSetup(MEMBER_PASSWORD);
    await memberApi.confirmTwoFactorSetup(await nextTotpCode(secret));
    await memberContext.dispose();

    const ownerPage = await freshAccount.newAuthedPage();
    const team = new TeamPage(ownerPage);
    await team.goto();
    const memberRow = team.userRow(memberEmail);
    await expect(memberRow.getByText('On', { exact: true })).toBeVisible();

    await memberRow.getByRole('button').click();
    await ownerPage.getByTestId('team-reset-two-factor-menu-item').click();
    await ownerPage.getByRole('button', { name: 'Reset two-factor' }).click();
    await expect(ownerPage.getByText('Two-factor authentication reset')).toBeVisible();
    await expect(memberRow.getByText('Off', { exact: true })).toBeVisible();

    const memberPage = await openAnonymousPage(browser);
    const member = new TwoFactorPage(memberPage);
    await member.signInWithPassword(memberEmail, MEMBER_PASSWORD);
    await member.expectDashboard();
    await memberPage.context().close();
  });

  test('a superadmin resets two-factor for a locked-out user', { tag: '@admin' }, async ({ freshAccount, superAdminPage, browser }) => {
    await enableTwoFactorViaApi(freshAccount);

    await superAdminPage.goto('/admin/users');
    await superAdminPage.waitForLoadState('networkidle');
    await superAdminPage.getByPlaceholder(/^Search by name, email/).fill(freshAccount.email);
    await expect(superAdminPage.getByText(freshAccount.email)).toBeVisible();
    const resetButton = superAdminPage.getByTestId('admin-reset-two-factor-button');
    await expect(resetButton).toHaveCount(1, { timeout: 30_000 });
    await resetButton.click();
    await superAdminPage.getByRole('button', { name: 'Reset 2FA' }).last().click();
    await expect(superAdminPage.getByText('Two-factor authentication reset')).toBeVisible();
    await expect(resetButton).toHaveCount(0);

    const loginPage = await openAnonymousPage(browser);
    const login = new TwoFactorPage(loginPage);
    await login.signInWithPassword(freshAccount.email, freshAccount.password);
    await login.expectDashboard();
    await loginPage.context().close();
  });
});
