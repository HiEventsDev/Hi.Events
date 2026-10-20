import { test, expect } from '../../fixtures';
import type { FreshAccount } from '../../fixtures';
import {
  EnterpriseSsoSettings,
  MOCK_OIDC_URL,
  mockOidcIsReachable,
  signInAtMockProvider,
  SsoLoginPage,
} from '../../pages/enterprise-sso.page';
import { TwoFactorPage } from '../../pages/two-factor.page';
import { enableFeatureFlag, markSsoConnectionTested, markSsoDomainVerified } from '../../utils/db';
import { nextTotpCode } from '../../utils/totp';
import { uniqueEmail } from '../../utils/unique';
import { openAuthedPage } from '../../fixtures/auth.fixture';
import { ApiClient, login } from '../../api/api-client';
import { TeamPage } from '../../pages/team.page';
import { acceptWithPassword, apiAs, joinAnotherAccount, latestInvitationPath, MEMBER_PASSWORD } from './team-member.shared';

const uniqueSuffix = (): string => `${Date.now().toString(36)}${Math.random().toString(36).slice(2, 6)}`;

const prepareAccount = async (account: FreshAccount): Promise<number> => {
  const { id } = await account.api.getAccount();
  enableFeatureFlag(id, 'enterprise_sso');
  return id;
};

const activeConnection = async (account: FreshAccount, accountId: number, issuerId: string, domain?: string): Promise<void> => {
  await account.api.createSsoConnection(accountId, {
    type: 'OIDC',
    issuer: `${MOCK_OIDC_URL}/${issuerId}`,
    client_id: 'hievents-e2e',
    client_secret: 'e2e-secret',
    jit_provisioning_enabled: true,
  });
  markSsoConnectionTested(accountId);
  await account.api.setSsoConnectionStatus(accountId, 'ACTIVE');

  if (domain) {
    await account.api.addSsoDomain(accountId, domain);
    markSsoDomainVerified(accountId, domain);
  }
};

test.describe('enterprise single sign-on', () => {
  test.beforeEach(async ({ page }) => {
    test.skip(!(await mockOidcIsReachable(page)), `No mock identity provider at ${MOCK_OIDC_URL}`);
  });

  test('an admin connects an identity provider and a new teammate joins by signing in with SSO', { tag: '@smoke' }, async ({ freshAccount, browser }) => {
    const accountId = await prepareAccount(freshAccount);
    const issuerId = `acme-${uniqueSuffix()}`;
    const domain = `${issuerId}.test`;
    const page = await freshAccount.newAuthedPage();
    const settings = new EnterpriseSsoSettings(page);

    await settings.open();
    await settings.connect(`${MOCK_OIDC_URL}/${issuerId}`, 'hievents-e2e', 'e2e-secret');

    await settings.testSignIn(issuerId, 'tester', { email: `tester@${domain}`, email_verified: true, given_name: 'Tess' });
    await expect(page.getByTestId('sso-test-result')).toContainText(`Signed in as tester@${domain}`);

    await settings.addDomain(domain);
    await settings.drawer().getByTestId('sso-domain-row').filter({ hasText: domain }).getByRole('button', { name: 'Verify' }).click();
    await expect(page.getByText("We couldn't find the TXT record yet")).toBeVisible();
    markSsoDomainVerified(accountId, domain);
    await settings.open();
    await expect(settings.drawer().getByTestId('sso-domain-row')).toContainText('Verified');

    await settings.toggle('sso-connection-active-switch');
    await expect(page.getByText('Your team can now sign in with your identity provider')).toBeVisible();
    await settings.toggle('sso-jit-switch');
    await expect(page.getByTestId('sso-jit-switch')).toBeChecked();

    const teammateContext = await browser.newContext({ ignoreHTTPSErrors: true });
    const teammate = await teammateContext.newPage();
    await new SsoLoginPage(teammate).signInWithSso(`ada@${domain}`);
    await signInAtMockProvider(teammate, issuerId, 'okta-ada', {
      email: `ada@${domain}`,
      email_verified: true,
      given_name: 'Ada',
      family_name: 'Lovelace',
    });

    await expect(teammate).toHaveURL(/\/manage\//);
    await teammateContext.close();

    await page.goto('/settings/team');
    await expect(page.getByTestId('team-member-row').filter({ hasText: `ada@${domain}` })).toContainText('Ada Lovelace');
  });

  test('requiring single sign-on previews who is affected and marks enforced members on the team page', async ({ freshAccount, browser }) => {
    const accountId = await prepareAccount(freshAccount);
    const issuerId = `corp-${uniqueSuffix()}`;
    const domain = `${issuerId}.test`;
    const memberEmail = uniqueEmail('member').replace(/@.*/, `@${domain}`);

    const { secret } = await freshAccount.api.beginTwoFactorSetup(freshAccount.password);
    await freshAccount.api.confirmTwoFactorSetup(await nextTotpCode(secret));
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
    await freshAccount.api.inviteUser({ first_name: 'Grace', last_name: 'Hopper', email: memberEmail, role: 'ORGANIZER' });

    const page = await freshAccount.newAuthedPage();
    const settings = new EnterpriseSsoSettings(page);
    await settings.open();
    await settings.toggle('sso-enforce-switch');

    const preview = page.getByTestId('sso-enforcement-preview');
    await expect(preview).toContainText('1 member will need to sign in with your identity provider.');
    await expect(preview).toContainText(memberEmail);
    await page.getByTestId('sso-enforce-confirm').click();
    await expect(page.getByText('Single sign-on is now required')).toBeVisible();

    await page.goto('/settings/team');
    await expect(page.getByTestId('team-member-row').filter({ hasText: memberEmail })).toContainText('SSO');

    const memberContext = await browser.newContext({ ignoreHTTPSErrors: true });
    const member = await memberContext.newPage();
    await new SsoLoginPage(member).signInWithSso(memberEmail);
    await signInAtMockProvider(member, issuerId, 'grace', { email: memberEmail, email_verified: true });
    await expect(member).toHaveURL(/\/manage\//);
    await memberContext.close();
  });

  test('an email without single sign-on gets a clear dead end with a way back', async ({ freshAccount, browser }) => {
    const accountId = await prepareAccount(freshAccount);
    const issuerId = `solo-${uniqueSuffix()}`;
    await freshAccount.api.createSsoConnection(accountId, {
      type: 'OIDC',
      issuer: `${MOCK_OIDC_URL}/${issuerId}`,
      client_id: 'hievents-e2e',
      client_secret: 'e2e-secret',
    });
    markSsoConnectionTested(accountId);
    await freshAccount.api.setSsoConnectionStatus(accountId, 'ACTIVE');

    const context = await browser.newContext({ ignoreHTTPSErrors: true });
    const page = await context.newPage();
    await new SsoLoginPage(page).signInWithSso(`nobody@${uniqueSuffix()}.test`);

    await expect(page.getByText("Single sign-on isn't set up for this email")).toBeVisible();
    await page.getByRole('button', { name: 'Back to sign in' }).click();
    await expect(page.getByRole('heading', { name: 'Welcome back' })).toBeVisible();
    await context.close();
  });

  test('once single sign-on is required a member is signed out and pointed from the password form to SSO', async ({ freshAccount, browser, mailpit, publicApi }) => {
    const accountId = await prepareAccount(freshAccount);
    const issuerId = `req-${uniqueSuffix()}`;
    const domain = `${issuerId}.test`;
    const memberEmail = `sam@${domain}`;

    await activeConnection(freshAccount, accountId, issuerId, domain);
    await freshAccount.api.inviteUser({ first_name: 'Sam', last_name: 'Member', email: memberEmail, role: 'ORGANIZER' });
    const inviteUrl = await mailpit.waitForLink(memberEmail, /accept-invitation/, { subjectContains: 'invited to join' });
    const accept = await publicApi.post(`auth/invitation/${inviteUrl.pathname.split('/').pop()}`, {
      data: { first_name: 'Sam', last_name: 'Member', password: MEMBER_PASSWORD, password_confirmation: MEMBER_PASSWORD, timezone: 'UTC' },
    });
    expect(accept.ok()).toBeTruthy();
    const { token } = await login(publicApi, memberEmail, MEMBER_PASSWORD);
    const memberSession = await openAuthedPage(browser, token);

    const { secret } = await freshAccount.api.beginTwoFactorSetup(freshAccount.password);
    await freshAccount.api.confirmTwoFactorSetup(await nextTotpCode(secret));
    await freshAccount.api.updateSsoEnforcement(accountId, true);

    await memberSession.goto('/manage/events');
    await expect(memberSession).toHaveURL(/\/auth\/login/);
    await expect(memberSession.getByText('Your organization requires single sign-on')).toBeVisible();
    await memberSession.context().close();

    const context = await browser.newContext({ ignoreHTTPSErrors: true });
    const page = await context.newPage();
    await new TwoFactorPage(page).signInWithPassword(memberEmail, MEMBER_PASSWORD);
    const required = page.getByRole('alert').filter({ hasText: 'Your organization requires single sign-on' });
    await expect(required).toBeVisible();
    await required.getByRole('button', { name: 'Sign in with SSO' }).click();

    await expect(page.getByLabel('Work email')).toHaveValue(memberEmail);
    await page.getByTestId('sso-discovery-submit').click();
    await signInAtMockProvider(page, issuerId, 'sam', { email: memberEmail, email_verified: true });
    await expect(page).toHaveURL(/\/manage\//);
    await context.close();
  });

  test('on a phone the test sign-in happens in the same tab and returns to the setup drawer', async ({ freshAccount }) => {
    const accountId = await prepareAccount(freshAccount);
    const issuerId = `phone-${uniqueSuffix()}`;
    await freshAccount.api.createSsoConnection(accountId, {
      type: 'OIDC',
      issuer: `${MOCK_OIDC_URL}/${issuerId}`,
      client_id: 'hievents-e2e',
      client_secret: 'e2e-secret',
    });

    const page = await freshAccount.newAuthedPage();
    await page.setViewportSize({ width: 375, height: 812 });
    const settings = new EnterpriseSsoSettings(page);
    await settings.open();
    await page.getByTestId('sso-test-sign-in').click();
    await signInAtMockProvider(page, issuerId, 'phone-tester', { email: `phone@${issuerId}.test`, email_verified: true });

    await expect(page).toHaveURL(/\/settings\/account/);
    await expect(page.getByTestId('sso-test-result')).toContainText(`Signed in as phone@${issuerId}.test`);
    await expect(page.getByTestId('sso-connection-active-switch')).toBeEnabled();
  });

  test('when an SSO session expires the app quietly asks the identity provider to sign the person back in', async ({ freshAccount, browser }) => {
    const accountId = await prepareAccount(freshAccount);
    const issuerId = `quiet-${uniqueSuffix()}`;
    const domain = `${issuerId}.test`;
    await activeConnection(freshAccount, accountId, issuerId, domain);

    const context = await browser.newContext({ ignoreHTTPSErrors: true });
    const page = await context.newPage();
    await new SsoLoginPage(page).signInWithSso(`lin@${domain}`);
    await signInAtMockProvider(page, issuerId, 'lin', { email: `lin@${domain}`, email_verified: true });
    await expect(page).toHaveURL(/\/manage\//);

    await context.clearCookies();
    await page.goto('/manage/events');

    await page.waitForURL(/prompt=none/);
    expect(new URL(page.url()).searchParams.get('login_hint')).toBeNull();
    await context.close();
  });

  test('turning the connection off signs members out without bouncing them between the app and the provider', async ({ freshAccount, browser }) => {
    const accountId = await prepareAccount(freshAccount);
    const issuerId = `off-${uniqueSuffix()}`;
    const domain = `${issuerId}.test`;
    await activeConnection(freshAccount, accountId, issuerId, domain);

    const context = await browser.newContext({ ignoreHTTPSErrors: true });
    const page = await context.newPage();
    await new SsoLoginPage(page).signInWithSso(`ada@${domain}`);
    await signInAtMockProvider(page, issuerId, 'ada', { email: `ada@${domain}`, email_verified: true });
    await expect(page).toHaveURL(/\/manage\//);

    await freshAccount.api.setSsoConnectionStatus(accountId, 'INACTIVE');

    await page.goto('/manage/events').catch(() => undefined);
    await expect(page).toHaveURL(/\/auth\/login/);
    await expect(page.getByRole('heading', { name: 'Sign in with SSO' })).toBeVisible();
    await expect(page.getByLabel('Work email')).toBeVisible();
    await context.close();
  });

  test('the owner can still sign in with a password and a code while SSO is required, and admins are told', async ({ freshAccount, browser, mailpit }) => {
    const accountId = await prepareAccount(freshAccount);
    const issuerId = `owner-${uniqueSuffix()}`;
    await activeConnection(freshAccount, accountId, issuerId, `${issuerId}.test`);
    const { secret } = await freshAccount.api.beginTwoFactorSetup(freshAccount.password);
    await freshAccount.api.confirmTwoFactorSetup(await nextTotpCode(secret));
    await freshAccount.api.updateSsoEnforcement(accountId, true);

    const context = await browser.newContext({ ignoreHTTPSErrors: true });
    const page = await context.newPage();
    const twoFactor = new TwoFactorPage(page);
    await twoFactor.signInWithPassword(freshAccount.email, freshAccount.password);
    await twoFactor.expectChallenge();
    await twoFactor.enterLoginCode(await nextTotpCode(secret));
    await expect(page).toHaveURL(/\/manage\//);

    await mailpit.waitForMessage(freshAccount.email, { subjectContains: 'signed in without single sign-on' });
    await context.close();
  });

  test('the owner connects the identity provider from security settings and then signs in through it', async ({ freshAccount, browser, playwright }) => {
    const accountId = await prepareAccount(freshAccount);
    const issuerId = `link-${uniqueSuffix()}`;
    const domain = `${issuerId}.test`;
    const accountName = `Acme ${issuerId}`;
    const workEmail = `it-lead@${domain}`;
    const ownerApi = await apiAs(playwright, freshAccount.token);
    expect((await ownerApi.put(`accounts/${accountId}`, { data: { name: accountName, timezone: 'UTC', currency_code: 'USD' } })).ok()).toBeTruthy();
    await ownerApi.dispose();
    const { provider_key: providerKey } = await freshAccount.api.createSsoConnection(accountId, {
      type: 'OIDC',
      issuer: `${MOCK_OIDC_URL}/${issuerId}`,
      client_id: 'hievents-e2e',
      client_secret: 'e2e-secret',
    });
    markSsoConnectionTested(accountId);
    await freshAccount.api.setSsoConnectionStatus(accountId, 'ACTIVE');
    await freshAccount.api.addSsoDomain(accountId, domain);
    markSsoDomainVerified(accountId, domain);
    const { secret } = await freshAccount.api.beginTwoFactorSetup(freshAccount.password);
    await freshAccount.api.confirmTwoFactorSetup(await nextTotpCode(secret));
    await freshAccount.api.updateSsoEnforcement(accountId, true);

    const page = await freshAccount.newAuthedPage();
    await page.goto('/settings/security');
    await expect(page.getByText(`Not connected. ${accountName} uses this for single sign-on.`)).toBeVisible();
    await page.getByTestId(`sso-connect-${providerKey}`).click();
    await page.getByRole('dialog').getByLabel(/Current password/).fill(freshAccount.password);
    await page.getByTestId('confirm-identity-submit').click();
    await signInAtMockProvider(page, issuerId, 'it-lead', { email: workEmail, email_verified: true });

    await expect(page).toHaveURL(/\/settings\/security/);
    await expect(page.getByTestId(`sso-identity-${providerKey}`)).toContainText(workEmail);

    const context = await browser.newContext({ ignoreHTTPSErrors: true });
    const signIn = await context.newPage();
    await new SsoLoginPage(signIn).signInWithSso(workEmail);
    await signInAtMockProvider(signIn, issuerId, 'it-lead', { email: workEmail, email_verified: true });
    const twoFactor = new TwoFactorPage(signIn);
    await twoFactor.expectChallenge();
    await twoFactor.enterLoginCode(await nextTotpCode(secret));
    await expect(signIn).toHaveURL(/\/manage\//);
    await context.close();
  });

  test('someone invited while single sign-on is required joins through the identity provider instead of setting a password', async ({ freshAccount, browser, mailpit }) => {
    const accountId = await prepareAccount(freshAccount);
    const issuerId = `invite-${uniqueSuffix()}`;
    const domain = `${issuerId}.test`;
    const inviteeEmail = `new.hire@${domain}`;
    await activeConnection(freshAccount, accountId, issuerId, domain);
    const { secret } = await freshAccount.api.beginTwoFactorSetup(freshAccount.password);
    await freshAccount.api.confirmTwoFactorSetup(await nextTotpCode(secret));
    await freshAccount.api.updateSsoEnforcement(accountId, true);
    await freshAccount.api.inviteUser({ first_name: 'New', last_name: 'Hire', email: inviteeEmail, role: 'ORGANIZER' });
    const invitationPath = await latestInvitationPath(mailpit, inviteeEmail, 1);

    const context = await browser.newContext({ ignoreHTTPSErrors: true });
    const page = await context.newPage();
    await page.goto(invitationPath);
    await expect(page.getByText('Your organization requires single sign-on')).toBeVisible();
    await expect(page.getByLabel(/^Password/)).toHaveCount(0);
    await page.getByTestId('invitation-sso-button').click();

    await expect(page.getByLabel('Work email')).toHaveValue(inviteeEmail);
    await page.getByTestId('sso-discovery-submit').click();
    await signInAtMockProvider(page, issuerId, 'new-hire', { email: inviteeEmail, email_verified: true });
    await expect(page).toHaveURL(new RegExp(`/manage/organizer/${freshAccount.organizerId}`));
    await context.close();

    const team = new TeamPage(await freshAccount.newAuthedPage());
    await team.goto();
    await expect(team.userRow(inviteeEmail)).toBeVisible();
    await expect(team.userRow(inviteeEmail).getByText(/^Invited$/)).toHaveCount(0);
  });

  test('a member of two accounts who picks the one that requires single sign-on is sent to SSO', async ({ freshAccount, browser, mailpit, publicApi, playwright }) => {
    const accountId = await prepareAccount(freshAccount);
    const issuerId = `pick-${uniqueSuffix()}`;
    const domain = `${issuerId}.test`;
    const memberEmail = `kim@${domain}`;
    const accountName = `Acme ${issuerId}`;
    const ownerApi = await apiAs(playwright, freshAccount.token);
    expect((await ownerApi.put(`accounts/${accountId}`, { data: { name: accountName, timezone: 'UTC', currency_code: 'USD' } })).ok()).toBeTruthy();
    await ownerApi.dispose();
    await activeConnection(freshAccount, accountId, issuerId, domain);
    await freshAccount.api.inviteUser({ first_name: 'Kim', last_name: 'Member', email: memberEmail, role: 'ORGANIZER' });
    await acceptWithPassword(publicApi, await latestInvitationPath(mailpit, memberEmail, 1));
    await joinAnotherAccount(playwright, publicApi, mailpit, memberEmail);

    const { secret } = await freshAccount.api.beginTwoFactorSetup(freshAccount.password);
    await freshAccount.api.confirmTwoFactorSetup(await nextTotpCode(secret));
    await freshAccount.api.updateSsoEnforcement(accountId, true);

    const context = await browser.newContext({ ignoreHTTPSErrors: true });
    const page = await context.newPage();
    await new TwoFactorPage(page).signInWithPassword(memberEmail, MEMBER_PASSWORD);
    await expect(page.getByRole('heading', { name: 'Choose an account' })).toBeVisible();
    await page.getByTestId('account-chooser-option').filter({ hasText: accountName }).click();

    const required = page.getByRole('alert').filter({ hasText: 'Your organization requires single sign-on' });
    await expect(required).toBeVisible();
    await required.getByRole('button', { name: 'Sign in with SSO' }).click();
    await expect(page.getByLabel('Work email')).toHaveValue(memberEmail);
    await context.close();
  });

  test('a member with two-factor who must use single sign-on is sent to SSO before being asked for a code', async ({ freshAccount, browser, mailpit, publicApi, playwright }) => {
    const accountId = await prepareAccount(freshAccount);
    const issuerId = `mfa-${uniqueSuffix()}`;
    const domain = `${issuerId}.test`;
    const memberEmail = `kim@${domain}`;
    await activeConnection(freshAccount, accountId, issuerId, domain);
    await freshAccount.api.inviteUser({ first_name: 'Kim', last_name: 'Member', email: memberEmail, role: 'ORGANIZER' });
    await acceptWithPassword(publicApi, await latestInvitationPath(mailpit, memberEmail, 1));

    const memberApi = await apiAs(playwright, (await login(publicApi, memberEmail, MEMBER_PASSWORD)).token);
    const member = new ApiClient(memberApi);
    const { secret: memberSecret } = await member.beginTwoFactorSetup(MEMBER_PASSWORD);
    await member.confirmTwoFactorSetup(await nextTotpCode(memberSecret));
    await memberApi.dispose();

    const { secret } = await freshAccount.api.beginTwoFactorSetup(freshAccount.password);
    await freshAccount.api.confirmTwoFactorSetup(await nextTotpCode(secret));
    await freshAccount.api.updateSsoEnforcement(accountId, true);

    const context = await browser.newContext({ ignoreHTTPSErrors: true });
    const page = await context.newPage();
    const twoFactor = new TwoFactorPage(page);
    await twoFactor.signInWithPassword(memberEmail, MEMBER_PASSWORD);

    const required = page.getByRole('alert').filter({ hasText: 'Your organization requires single sign-on' });
    await expect(required).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Two-factor authentication' })).toHaveCount(0);
    await expect(page.getByText('Something went wrong')).toHaveCount(0);
    await required.getByRole('button', { name: 'Sign in with SSO' }).click();
    await expect(page.getByLabel('Work email')).toHaveValue(memberEmail);
    await context.close();
  });
});
