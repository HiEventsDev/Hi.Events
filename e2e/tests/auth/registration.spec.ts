import { test, expect } from '../../fixtures';
import { RegisterPage } from '../../pages/register.page';
import { uniqueEmail } from '../../utils/unique';

test.describe('registration', () => {
  test('a new organizer can register and reach the welcome page', { tag: '@smoke' }, async ({ page, mailpit }) => {
    const email = uniqueEmail();

    const register = new RegisterPage(page);
    await register.goto();
    await register.register({ firstName: 'New', lastName: 'Organizer', email, password: 'Password123!' });

    await expect(page).toHaveURL(/\/welcome/);

    const welcomeEmail = await mailpit.waitForMessage(email, { subjectContains: 'Welcome to Hi.Events' });
    expect(welcomeEmail.Subject).toContain('Welcome to Hi.Events');
  });
});
