import { test, expect } from '../../fixtures';
import type { FreshAccount } from '../../fixtures';
import { EnterpriseSsoSettings, MOCK_OIDC_URL, mockOidcIsReachable } from '../../pages/enterprise-sso.page';
import { enableFeatureFlag, markSsoConnectionTested } from '../../utils/db';

const uniqueSuffix = (): string => `${Date.now().toString(36)}${Math.random().toString(36).slice(2, 6)}`;

const connectedAccount = async (account: FreshAccount): Promise<number> => {
  const { id } = await account.api.getAccount();
  enableFeatureFlag(id, 'enterprise_sso');
  await account.api.createSsoConnection(id, {
    type: 'OIDC',
    issuer: `${MOCK_OIDC_URL}/access-${uniqueSuffix()}`,
    client_id: 'hievents-e2e',
    client_secret: 'e2e-secret',
  });
  markSsoConnectionTested(id);
  return id;
};

test.describe('single sign-on access settings', () => {
  test.beforeEach(async ({ page }) => {
    test.skip(!(await mockOidcIsReachable(page)), `No mock identity provider at ${MOCK_OIDC_URL}`);
  });

  test('changing the session length with the keyboard keeps focus in the drawer', async ({ freshAccount }) => {
    await connectedAccount(freshAccount);
    const page = await freshAccount.newAuthedPage();
    const settings = new EnterpriseSsoSettings(page);
    await settings.open();

    const sessionLength = settings.drawer().getByRole('combobox', { name: 'Sign people out after' });
    await expect(sessionLength).toHaveValue('12 hours');
    await sessionLength.focus();
    await page.keyboard.press('ArrowDown');
    await page.getByRole('option', { name: '1 day' }).waitFor();
    await page.keyboard.press('ArrowDown');
    await page.keyboard.press('Enter');

    await expect(page.getByText('Single sign-on settings updated')).toBeVisible();
    await expect(sessionLength).toHaveValue('1 day');
    await expect(sessionLength).toBeFocused();
    expect(await page.evaluate(() => !!document.activeElement?.closest('[role="dialog"]'))).toBe(true);

    const jit = settings.drawer().getByTestId('sso-jit-switch');
    await jit.focus();
    await page.keyboard.press('Space');
    await expect(jit).toBeChecked();
    await expect(jit).toBeFocused();
  });

  test('a session length that fails to save goes back to the saved value', async ({ freshAccount }) => {
    await connectedAccount(freshAccount);
    const page = await freshAccount.newAuthedPage();
    const settings = new EnterpriseSsoSettings(page);
    await settings.open();

    await page.route(/\/accounts\/\d+\/sso\/connection$/, route => route.request().method() === 'PUT'
      ? route.fulfill({
        status: 422,
        contentType: 'application/json',
        body: JSON.stringify({ message: 'Rejected', errors: { session_ttl_minutes: ['Choose a shorter session.'] } }),
      })
      : route.fallback());

    const sessionLength = settings.drawer().getByRole('combobox', { name: 'Sign people out after' });
    await sessionLength.click();
    await page.getByRole('option', { name: '7 days' }).click();

    await expect(page.getByText('Choose a shorter session.')).toBeVisible();
    await expect(sessionLength).toHaveValue('12 hours');
  });
});
