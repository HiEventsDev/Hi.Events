import { test, expect } from '../../fixtures';
import { providerIsConfigured, SsoPage } from '../../pages/sso.page';
import { uniqueEmail } from '../../utils/unique';

test.describe('single sign-on', () => {
  test.beforeEach(async ({ page }) => {
    test.skip(!(await providerIsConfigured(page)), 'No OIDC provider is configured on this stack');
  });

  test('a new organizer signs up with single sign-on and signs back in', { tag: '@smoke' }, async ({ page, browser }) => {
    const email = uniqueEmail('sso');
    const subject = `subject-${email}`;
    const sso = new SsoPage(page);

    await page.goto('/auth/register');
    await sso.providerButton().click();
    await sso.signInAtProvider(subject, { email, email_verified: true, given_name: 'Ada', family_name: 'Lovelace' });
    await expect(page.getByText(email)).toBeVisible();
    await sso.completeSignup();
    await expect(page).toHaveURL(/\/welcome/);

    const returning = await browser.newPage();
    const returningSso = new SsoPage(returning);
    await returning.goto('/auth/login');
    await returningSso.providerButton().click();
    await returningSso.signInAtProvider(subject, { email, email_verified: true });
    await expect(returning).toHaveURL(/\/manage\//);
    await returning.close();
  });

  test('cancelling at the provider returns to the sign-in page', async ({ page }) => {
    const sso = new SsoPage(page);

    await page.goto('/auth/login');
    await sso.providerButton().click();
    await page.waitForURL(/\/default\/authorize/);
    const state = new URL(page.url()).searchParams.get('state');
    await page.goto(`/sso/callback?error=access_denied&state=${state}`);

    await expect(page).toHaveURL(/\/auth\/login/);
    await expect(page.getByText('Sign-in was cancelled')).toBeVisible();
  });

  test('an organizer connects single sign-on to their password account', async ({ freshAccount }) => {
    const page = await freshAccount.newAuthedPage();
    const sso = new SsoPage(page);
    const workEmail = uniqueEmail('work');

    await page.goto('/settings/security');
    await page.getByTestId('sso-connect-oidc').click();
    const dialog = page.getByRole('dialog');
    await dialog.getByLabel(/Current password/).fill(freshAccount.password);
    await page.getByTestId('confirm-identity-submit').click();
    await sso.signInAtProvider(`subject-${workEmail}`, { email: workEmail });

    await expect(page).toHaveURL(/\/settings\/security/);
    await expect(page.getByTestId('sso-identity-oidc')).toContainText(workEmail);
  });

  test('a provider email matching an account that must sign in first explains what to do', async ({ freshAccount, page }) => {
    const sso = new SsoPage(page);

    await page.goto('/auth/login');
    await sso.providerButton().click();
    await sso.signInAtProvider(`subject-${freshAccount.email}`, { email: freshAccount.email, email_verified: false });

    await expect(page.getByTestId('sso-error')).toHaveAttribute('data-error-code', 'SSO_LINK_REQUIRES_LOGIN');
    await page.getByRole('button', { name: 'Sign in with password' }).click();
    await expect(page).toHaveURL(/\/auth\/login/);
  });

  test('invitations are accepted with single sign-on and a member of two accounts chooses one', async ({ freshAccount, browser, mailpit }) => {
    const email = uniqueEmail('sso-member');
    const subject = `subject-${email}`;

    const signup = await browser.newPage();
    const signupSso = new SsoPage(signup);
    await signup.goto('/auth/register');
    await signupSso.providerButton().click();
    await signupSso.signInAtProvider(subject, { email, email_verified: true, given_name: 'Sam', family_name: 'Member' });
    await signupSso.completeSignup();
    await expect(signup).toHaveURL(/\/welcome/);
    await signup.close();

    await freshAccount.api.inviteUser({ first_name: 'Sam', last_name: 'Member', email, role: 'ORGANIZER' });
    const inviteUrl = await mailpit.waitForLink(email, /accept-invitation/, { subjectContains: 'invited to join' });

    const invitee = await browser.newPage();
    const inviteeSso = new SsoPage(invitee);
    await invitee.goto(inviteUrl.pathname);
    await expect(invitee.getByRole('heading', { name: /^Join / })).toBeVisible();
    await inviteeSso.providerButton().click();
    await inviteeSso.signInAtProvider(subject, { email, email_verified: true });
    await expect(invitee).toHaveURL(/\/manage\//);
    await invitee.close();

    const returning = await browser.newPage();
    const returningSso = new SsoPage(returning);
    await returning.goto('/auth/login');
    await returningSso.providerButton().click();
    await returningSso.signInAtProvider(subject, { email, email_verified: true });
    await expect(returning.getByRole('heading', { name: 'Choose an account' })).toBeVisible();
    await expect(returning.getByTestId('account-chooser-option')).toHaveCount(2);
    await returning.getByTestId('account-chooser-option').first().click();
    await expect(returning).toHaveURL(/\/manage\//);
    await returning.close();
  });
});
