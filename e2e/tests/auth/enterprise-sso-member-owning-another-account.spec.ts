import { expect, test } from '../../fixtures';
import {
  EnterpriseSsoSettings,
  MOCK_OIDC_URL,
  mockOidcIsReachable,
  signInAtMockProvider,
  SsoLoginPage,
} from '../../pages/enterprise-sso.page';
import { confirmEmailWithCode, login, registerAccount } from '../../api/api-client';
import { enableFeatureFlag, markSsoConnectionTested, markSsoDomainVerified } from '../../utils/db';
import { IS_SAAS_MODE } from '../../utils/env';
import { nextTotpCode } from '../../utils/totp';
import { apiAs, latestInvitationPath, MEMBER_PASSWORD } from './team-member.shared';

const uniqueSuffix = (): string => `${Date.now().toString(36)}${Math.random().toString(36).slice(2, 6)}`;

test.describe('enterprise single sign-on for a teammate who owns another account', () => {
  test.beforeEach(async ({ page }) => {
    test.skip(!(await mockOidcIsReachable(page)), `No mock identity provider at ${MOCK_OIDC_URL}`);
  });

  test('requiring SSO keeps them signed in, shows them as Password, and SSO tells them to connect it first', async ({ freshAccount, browser, mailpit, publicApi, playwright }) => {
    const { id: accountId } = await freshAccount.api.getAccount();
    enableFeatureFlag(accountId, 'enterprise_sso');
    const issuerId = `owner-elsewhere-${uniqueSuffix()}`;
    const domain = `${issuerId}.test`;
    const memberEmail = `kim@${domain}`;

    await freshAccount.api.createSsoConnection(accountId, {
      type: 'OIDC',
      issuer: `${MOCK_OIDC_URL}/${issuerId}`,
      client_id: 'hievents-e2e',
      client_secret: 'e2e-secret',
    });
    markSsoConnectionTested(accountId);
    await freshAccount.api.setSsoConnectionStatus(accountId, 'ACTIVE');
    await freshAccount.api.addSsoDomain(accountId, domain);
    markSsoDomainVerified(accountId, domain);

    await registerAccount(publicApi, {
      first_name: 'Kim',
      last_name: 'Member',
      email: memberEmail,
      password: MEMBER_PASSWORD,
      password_confirmation: MEMBER_PASSWORD,
      timezone: 'UTC',
      currency_code: 'USD',
      locale: 'en',
    });
    const ownAccount = await login(publicApi, memberEmail, MEMBER_PASSWORD);
    if (IS_SAAS_MODE && !ownAccount.user.is_email_verified) {
      await confirmEmailWithCode(publicApi, ownAccount.token, ownAccount.user.id, await mailpit.waitForVerificationCode(memberEmail));
    }
    await freshAccount.api.inviteUser({ first_name: 'Kim', last_name: 'Member', email: memberEmail, role: 'ORGANIZER' });
    const invitationPath = await latestInvitationPath(mailpit, memberEmail, 1);
    const ownAccountApi = await apiAs(playwright, ownAccount.token);
    expect((await ownAccountApi.post(`auth/invitation/${invitationPath.split('/').pop()}/join`)).ok()).toBeTruthy();
    await ownAccountApi.dispose();

    const acmeLogin = await publicApi.post('auth/login', { data: { email: memberEmail, password: MEMBER_PASSWORD, account_id: accountId } });
    expect(acmeLogin.ok()).toBeTruthy();
    const memberApi = await apiAs(playwright, (await acmeLogin.json()).token);

    const { secret } = await freshAccount.api.beginTwoFactorSetup(freshAccount.password);
    await freshAccount.api.confirmTwoFactorSetup(await nextTotpCode(secret));

    const page = await freshAccount.newAuthedPage();
    const settings = new EnterpriseSsoSettings(page);
    await settings.open();
    await settings.toggle('sso-enforce-switch');
    const preview = page.getByTestId('sso-enforcement-preview');
    await expect(preview.getByRole('listitem').filter({ hasText: memberEmail }))
      .toContainText('Owns another Hi.Events account, so keeps signing in with a password');
    await page.getByTestId('sso-enforce-confirm').click();
    await expect(page.getByText('Single sign-on is now required')).toBeVisible();

    expect((await memberApi.get('users/me')).status()).toBe(200);
    await memberApi.dispose();

    await page.goto('/settings/team');
    const memberRow = page.getByTestId('team-member-row').filter({ hasText: memberEmail });
    await expect(memberRow).toContainText('Password');
    await expect(memberRow).not.toContainText('Exempt');

    const context = await browser.newContext({ ignoreHTTPSErrors: true });
    const signIn = await context.newPage();
    await new SsoLoginPage(signIn).signInWithSso(memberEmail);
    await signInAtMockProvider(signIn, issuerId, 'kim', { email: memberEmail, email_verified: true });
    const error = signIn.getByTestId('sso-error');
    await expect(error.getByRole('heading', { name: `You already have an account with ${memberEmail}` })).toBeVisible();
    await expect(error).toContainText('Sign in with your password, then connect');
    await context.close();
  });
});
