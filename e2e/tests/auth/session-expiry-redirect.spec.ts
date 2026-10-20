import { test, expect } from '../../fixtures';
import { openAuthedPage } from '../../fixtures/auth.fixture';

test.describe('expired session', () => {
  for (const path of ['/manage/organizer/1/widget', '/manage/organizer/1/events/archived', '/manage/events/upcoming']) {
    test(`${path} sends the user to sign in`, async ({ browser }) => {
      const page = await openAuthedPage(browser, 'expired-session-token');

      await page.goto(path);

      await expect(page).toHaveURL(/\/auth\/login/);
      await expect(page.getByRole('button', { name: /sign in|log in/i }).first()).toBeVisible();
      await page.context().close();
    });
  }
});
