import { test, expect } from '../fixtures';

test.describe('@smoke stack wiring', () => {
  test('public register page renders', async ({ page }) => {
    await page.goto('/auth/register');
    await expect(page.getByRole('button', { name: /register/i })).toBeVisible();
  });

  test('account fixture arranges an authenticated organizer', async ({ account, api }) => {
    expect(account.organizerId).toBeGreaterThan(0);
    expect(account.token).not.toEqual('');
    expect(api).toBe(account.api);
  });
});
