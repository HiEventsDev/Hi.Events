import { test, expect } from '../../fixtures';
import { TwoFactorPage } from '../../pages/two-factor.page';
import { uniqueEmail } from '../../utils/unique';
import { acceptWithPassword, joinAnotherAccount, latestInvitationPath, MEMBER_PASSWORD } from './team-member.shared';

test.describe('account chooser', () => {
  test('a problem signing in to the chosen account is shown on the chooser', async ({ freshAccount, browser, mailpit, publicApi, playwright }) => {
    const memberEmail = uniqueEmail('chooser');
    await freshAccount.api.inviteUser({ first_name: 'Kim', last_name: 'Member', email: memberEmail, role: 'ORGANIZER' });
    await acceptWithPassword(publicApi, await latestInvitationPath(mailpit, memberEmail, 1));
    await joinAnotherAccount(playwright, publicApi, mailpit, memberEmail);

    const context = await browser.newContext({ ignoreHTTPSErrors: true });
    const page = await context.newPage();
    await new TwoFactorPage(page).signInWithPassword(memberEmail, MEMBER_PASSWORD);
    await expect(page.getByRole('heading', { name: 'Choose an account' })).toBeVisible();

    await page.route('**/api/auth/login', (route) => route.fulfill({
      status: 429,
      contentType: 'application/json',
      body: JSON.stringify({ message: 'Too Many Attempts.' }),
    }));
    await page.getByTestId('account-chooser-option').first().click();

    await expect(page.getByRole('alert').filter({ hasText: 'Too many attempts. Please wait a minute and try again.' })).toBeVisible();
    await expect(page.getByTestId('account-chooser-option')).toHaveCount(2);
    await expect(page.getByTestId('account-chooser-option').first()).toBeEnabled();
    await context.close();
  });
});
