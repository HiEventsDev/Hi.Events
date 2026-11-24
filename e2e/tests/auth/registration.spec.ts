import { test, expect } from '../../fixtures';
import { RegisterPage } from '../../pages/register.page';
import { uniqueEmail } from '../../utils/unique';
import { IS_SAAS_MODE } from '../../utils/env';

test.describe('registration', () => {
  test('a new organizer can register, verify their email and create a first event', { tag: '@smoke' }, async ({ page, mailpit }) => {
    const email = uniqueEmail();

    const register = new RegisterPage(page);
    await register.goto();
    await register.register({ firstName: 'New', lastName: 'Organizer', email, password: 'Password123!' });

    await expect(page).toHaveURL(/\/welcome/);

    if (IS_SAAS_MODE) {
      await expect(page.getByRole('heading', { name: 'Check your email' })).toBeVisible();
      const code = await mailpit.waitForVerificationCode(email);
      await page.getByRole('group', { name: 'Verification code' }).getByRole('textbox').first().pressSequentially(code);
    } else {
      const welcomeEmail = await mailpit.waitForMessage(email, { subjectContains: 'Welcome to Hi.Events' });
      expect(welcomeEmail.Subject).toContain('Welcome to Hi.Events');
    }

    await expect(page.getByRole('heading', { name: 'Create your first event' })).toBeVisible();
    await expect(page.getByLabel(/^Organizer name/)).toHaveValue('New Organizer');
    await page.getByLabel(/^Event name/).fill('Harbour Lights Supper Club');
    await page.getByRole('button', { name: 'Create event' }).click();

    await expect(page).toHaveURL(/\/manage\/event\/\d+\/dashboard/);
    await expect(page.getByRole('heading', { name: 'Harbour Lights Supper Club' })).toBeVisible();
  });

  test('an organizer who mistyped their email can go back and register again', async ({ page }) => {
    test.skip(!IS_SAAS_MODE, 'Email verification is only enforced in SaaS mode');

    const register = new RegisterPage(page);
    await register.goto();
    await register.register({ firstName: 'New', email: uniqueEmail(), password: 'Password123!' });

    await expect(page.getByRole('heading', { name: 'Check your email' })).toBeVisible();
    await page.getByTestId('welcome-use-different-email-button').click();

    await expect(page).toHaveURL(/\/auth\/register/);
    await expect(page.getByLabel(/^First Name/)).toBeVisible();
  });

  test('a name containing a phone number or link is refused', async ({ page, mailpit }) => {
    const email = uniqueEmail();

    const register = new RegisterPage(page);
    await register.goto();
    await register.register({
      firstName: 'Your crypto purchase USD 364 success. Call +1(805)891-5965',
      email,
      password: 'Password123!',
    });

    await expect(page.getByText('may only contain letters, spaces and simple punctuation')).toBeVisible();
    await expect(page).toHaveURL(/\/auth\/register/);
    expect(await mailpit.search(email)).toHaveLength(0);
  });
});
