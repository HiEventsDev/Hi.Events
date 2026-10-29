import { test, expect } from '../../fixtures';
import { RegisterPage } from '../../pages/register.page';
import { uniqueEmail } from '../../utils/unique';
import { IS_SAAS_MODE } from '../../utils/env';

test.describe('registration', () => {
  test('a new organizer can register and reach the welcome page', { tag: '@smoke' }, async ({ page, mailpit }) => {
    const email = uniqueEmail();

    const register = new RegisterPage(page);
    await register.goto();
    await register.register({ firstName: 'New', lastName: 'Organizer', email, password: 'Password123!' });

    await expect(page).toHaveURL(/\/welcome/);

    if (IS_SAAS_MODE) {
      await expect(page.getByRole('heading', { name: 'Check your email' })).toBeVisible();
      const code = await mailpit.waitForVerificationCode(email);
      await page.getByRole('group', { name: 'Verification code' }).getByRole('textbox').first().pressSequentially(code);
      await page.getByRole('button', { name: 'Verify Email' }).click();
      await expect(page.getByRole('heading', { name: 'Check your email' })).toHaveCount(0);
      return;
    }

    const welcomeEmail = await mailpit.waitForMessage(email, { subjectContains: 'Welcome to Hi.Events' });
    expect(welcomeEmail.Subject).toContain('Welcome to Hi.Events');
  });

  test('a name containing a phone number or link is refused', async ({ page, mailpit }) => {
    const email = uniqueEmail();

    const register = new RegisterPage(page);
    await register.goto();
    await register.register({
      firstName: 'Your crypto purchase USD 364 success. Call +1(805)891-5965',
      lastName: 'Organizer',
      email,
      password: 'Password123!',
    });

    await expect(page.getByText('may only contain letters, spaces and simple punctuation')).toBeVisible();
    await expect(page).toHaveURL(/\/auth\/register/);
    expect(await mailpit.search(email)).toHaveLength(0);
  });
});
