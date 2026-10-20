import { test, expect } from '../../fixtures';

test.describe('sign-in methods load errors', () => {
  test('offers a retry instead of Connect buttons when connected identities fail to load', async ({ freshAccount }) => {
    const page = await freshAccount.newAuthedPage();
    const providersResponse = await page.request.get('/api/auth/sso/providers');
    expect(providersResponse.ok()).toBeTruthy();
    const providerKey: string | undefined = (await providersResponse.json()).data.providers[0]?.key;
    test.skip(!providerKey, 'No sign-in provider is configured on this stack');

    let failing = true;
    await page.route(/\/api\/users\/me\/sso-identities(\?.*)?$/, (route) => (failing && route.request().method() === 'GET'
      ? route.fulfill({ status: 500, json: { message: 'Server Error' } })
      : route.continue()));

    await page.goto('/settings/security');

    await expect(page.getByTestId('sso-identities-retry')).toBeVisible({ timeout: 20_000 });
    await expect(page.getByTestId(`sso-connect-${providerKey}`)).toHaveCount(0);

    failing = false;
    await page.getByTestId('sso-identities-retry').click();
    await expect(page.getByTestId(`sso-connect-${providerKey}`)).toBeVisible();
    await expect(page.getByTestId('sso-identities-retry')).toHaveCount(0);
  });
});
