import type { APIRequestContext, Page } from '@playwright/test';
import { expect, test } from '../../fixtures';
import { ApiKeysPage } from '../../pages/api-keys.page';
import { expireApiKey } from '../../utils/db';
import { invitedMember } from '../../utils/team';
import { providerIsConfigured, SsoPage } from '../../pages/sso.page';
import { uniqueEmail } from '../../utils/unique';

const MEMBER_PASSWORD = 'MemberPass123!';

const callApi = (api: APIRequestContext, apiKey: string, method: 'GET' | 'POST', path: string, data?: object) =>
  api.fetch(path, {
    method,
    data,
    headers: { Authorization: `Bearer ${apiKey}`, Accept: 'application/json', 'Content-Type': 'application/json' },
  });

const hasHorizontalOverflow = (page: Page) =>
  page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth);

test.describe('API keys', () => {
  test('an owner creates a key, uses it against the API and revokes it @smoke', async ({ freshAccount, mailpit, publicApi }) => {
    const page = await freshAccount.newAuthedPage();
    const apiKeys = new ApiKeysPage(page);

    await apiKeys.goto();
    await expect(apiKeys.emptyState()).toContainText("You don't have any API keys");

    await apiKeys.openCreate();
    await apiKeys.fillCreateForm({ name: 'Zapier production', expiry: '30 days', password: freshAccount.password });
    await expect(apiKeys.dialog()).toContainText("We'll email you a week before.");
    await apiKeys.submitCreate();

    await expect(apiKeys.dialog()).toContainText("This is the only time you'll see this key");
    const key = (await page.getByTestId('api-key-secret').textContent())?.trim() ?? '';
    expect(key).toMatch(/^hie_[A-Za-z0-9]{40,}$/);
    await expect(apiKeys.dialog()).toContainText(`Authorization: Bearer ${key}`);

    await page.getByTestId('api-key-copy').click();
    await expect(page.getByTestId('api-key-copy')).toHaveText('Copied');
    expect(await page.evaluate(() => navigator.clipboard.readText())).toBe(key);
    await page.getByTestId('api-key-done').click();
    await expect(apiKeys.dialog()).toBeHidden();

    const row = apiKeys.row('Zapier production');
    await expect(row).toContainText('Read and write');
    await expect(row).toContainText(`hie_…${key.slice(-4)}`);
    await expect(row).toContainText('Never used');
    await expect(row).not.toContainText(key);

    const me = await callApi(publicApi, key, 'GET', 'users/me');
    expect(me.status()).toBe(200);
    expect((await me.json()).data.email).toBe(freshAccount.email);

    await mailpit.waitForMessage(freshAccount.email, { subjectContains: 'A new API key was created' });

    await page.reload();
    await expect(apiKeys.row('Zapier production')).not.toContainText('Never used');

    await apiKeys.revoke('Zapier production');
    await expect(apiKeys.row('Zapier production')).toHaveCount(0);
    await expect(apiKeys.emptyState()).toBeVisible();

    const afterRevoke = await callApi(publicApi, key, 'GET', 'users/me');
    expect(afterRevoke.status()).toBe(401);
  });

  test('a read-only key that never expires can read but not change anything', async ({ freshAccount, publicApi }) => {
    const page = await freshAccount.newAuthedPage();
    const apiKeys = new ApiKeysPage(page);

    await apiKeys.goto();
    await apiKeys.openCreate();
    await apiKeys.fillCreateForm({ name: 'Reporting', access: 'Read-only', expiry: 'No expiration', password: freshAccount.password });
    await expect(apiKeys.dialog()).toContainText('Keys that never expire stay valid until you revoke them.');
    await apiKeys.submitCreate();
    const key = (await page.getByTestId('api-key-secret').textContent())?.trim() ?? '';
    await page.getByTestId('api-key-copy').click();
    await page.getByTestId('api-key-done').click();

    const row = apiKeys.row('Reporting');
    await expect(row).toContainText('Read-only');
    await expect(row).toContainText('Never');

    const read = await callApi(publicApi, key, 'GET', `organizers/${freshAccount.organizerId}`);
    expect(read.status()).toBe(200);

    const write = await callApi(publicApi, key, 'POST', 'organizers', { name: 'Nope', email: 'nope@example.com', currency: 'USD', timezone: 'UTC' });
    expect(write.status()).toBe(403);
    expect((await write.json()).error_code).toBe('API_KEY_READ_ONLY');

    const keysEndpoint = await callApi(publicApi, key, 'GET', 'api-keys');
    expect(keysEndpoint.status()).toBe(403);
    expect((await keysEndpoint.json()).error_code).toBe('API_KEY_NOT_ALLOWED');
  });

  test('a wrong password keeps the form open and creates nothing', async ({ freshAccount }) => {
    const page = await freshAccount.newAuthedPage();
    const apiKeys = new ApiKeysPage(page);

    await apiKeys.goto();
    await apiKeys.openCreate();
    await apiKeys.fillCreateForm({ name: 'Wrong password', password: 'not-my-password' });
    await apiKeys.submitCreate();

    await expect(apiKeys.dialog()).toContainText('The password is incorrect.');
    await expect(apiKeys.dialog().getByRole('heading', { name: 'Create API key' })).toBeVisible();
    await expect(page.getByTestId('api-key-name')).toHaveValue('Wrong password');

    await apiKeys.dialog().getByRole('button', { name: 'Cancel' }).click();
    await expect(apiKeys.emptyState()).toBeVisible();
  });

  test('closing the key before copying it asks for confirmation', async ({ freshAccount }) => {
    const page = await freshAccount.newAuthedPage();
    const apiKeys = new ApiKeysPage(page);

    await apiKeys.goto();
    await apiKeys.openCreate();
    await apiKeys.fillCreateForm({ name: 'Careful', password: freshAccount.password });
    await apiKeys.submitCreate();
    await expect(page.getByTestId('api-key-secret')).toBeVisible();

    await page.getByTestId('api-key-done').click();
    const warning = page.getByRole('dialog').filter({ hasText: "You haven't copied this key" });
    await expect(warning).toBeVisible();
    await warning.getByRole('button', { name: 'Go back' }).click();
    await expect(page.getByTestId('api-key-secret')).toBeVisible();

    await page.getByTestId('api-key-done').click();
    await page.getByRole('dialog').getByRole('button', { name: 'Close without copying' }).click();
    await expect(page.getByTestId('api-key-secret')).toHaveCount(0);
    await expect(apiKeys.row('Careful')).toBeVisible();
  });

  test('when the browser blocks copying, the key can still be copied by hand', async ({ freshAccount }) => {
    const page = await freshAccount.newAuthedPage();
    await page.addInitScript(() => {
      Object.defineProperty(navigator, 'clipboard', {
        value: { writeText: () => Promise.reject(new Error('blocked')) },
      });
    });
    const apiKeys = new ApiKeysPage(page);

    await apiKeys.goto();
    await apiKeys.openCreate();
    await apiKeys.fillCreateForm({ name: 'Blocked clipboard', password: freshAccount.password });
    await apiKeys.submitCreate();

    await page.getByTestId('api-key-copy').click();
    await expect(apiKeys.dialog()).toContainText('Your browser blocked copying.');
    await expect(page.getByTestId('api-key-copy')).toHaveText('Copy key');

    await page.getByTestId('api-key-done').click();
    const warning = page.getByRole('dialog').filter({ hasText: "You haven't copied this key" });
    await expect(warning).toBeVisible();
    await warning.getByRole('button', { name: 'Go back' }).click();

    await page.getByTestId('api-key-secret').dispatchEvent('copy');
    await expect(page.getByTestId('api-key-copy')).toHaveText('Copied');
    await page.getByTestId('api-key-done').click();
    await expect(apiKeys.row('Blocked clipboard')).toBeVisible();
  });

  test('an admin sees and revokes a member key, and the member is told', async ({ freshAccount, mailpit, publicApi, browser }) => {
    const member = await invitedMember(freshAccount, mailpit, publicApi, browser, { last_name: 'Member', role: 'ORGANIZER' });
    const memberKeys = new ApiKeysPage(member.page);
    await memberKeys.goto();
    const memberKey = await memberKeys.createKey({ name: 'Member integration', password: MEMBER_PASSWORD });

    const ownerPage = await freshAccount.newAuthedPage();
    const ownerKeys = new ApiKeysPage(ownerPage);
    await ownerKeys.goto();
    await expect(ownerKeys.row('Member integration')).toHaveCount(0);
    await expect(ownerPage.getByTestId('api-key-create-button')).toHaveCount(1);

    await ownerKeys.showEveryone();
    const row = ownerKeys.row('Member integration');
    await expect(row).toContainText('Sam Member');
    await ownerKeys.revoke('Member integration');
    await expect(row).toHaveCount(0);

    await mailpit.waitForMessage(member.email, { subjectContains: 'One of your API keys was revoked' });
    expect((await callApi(publicApi, memberKey, 'GET', 'users/me')).status()).toBe(401);

    await memberKeys.goto();
    await expect(memberKeys.emptyState()).toBeVisible();
  });

  test('members only see their own keys', async ({ freshAccount, mailpit, publicApi, browser }) => {
    await freshAccount.api.createApiKey({ name: 'Owner key', access: 'READ_WRITE', expires_in_days: 30, password: freshAccount.password });
    const member = await invitedMember(freshAccount, mailpit, publicApi, browser, { last_name: 'Member', role: 'ORGANIZER' });

    const memberKeys = new ApiKeysPage(member.page);
    await memberKeys.goto();
    await expect(memberKeys.emptyState()).toBeVisible();
    await expect(memberKeys.row('Owner key')).toHaveCount(0);
    await expect(member.page.getByTestId('api-key-scope')).toHaveCount(0);
  });

  test('a viewer can only create read-only keys', async ({ freshAccount, mailpit, publicApi, browser }) => {
    const viewer = await invitedMember(freshAccount, mailpit, publicApi, browser, { last_name: 'Viewer', role: 'VIEWER' });
    const viewerKeys = new ApiKeysPage(viewer.page);

    await viewerKeys.goto();
    await viewerKeys.openCreate();
    await expect(viewer.page.getByTestId('api-key-access-read-write')).toBeDisabled();
    await expect(viewerKeys.dialog()).toContainText('Not available: you have view-only access.');

    await viewerKeys.fillCreateForm({ name: 'Viewer reporting', password: MEMBER_PASSWORD });
    await viewerKeys.submitCreate();
    await viewer.page.getByTestId('api-key-copy').click();
    await viewer.page.getByTestId('api-key-done').click();

    await expect(viewerKeys.row('Viewer reporting')).toContainText('Read-only');
  });

  test('a read and write key can make changes, and stops working once it expires', async ({ freshAccount, publicApi }) => {
    const created = await freshAccount.api.createApiKey({ name: 'Website sync', access: 'READ_WRITE', expires_in_days: 30, password: freshAccount.password });

    const write = await callApi(publicApi, created.key, 'POST', 'organizers', { name: 'Made by the API', email: uniqueEmail('api-organizer'), currency: 'USD', timezone: 'UTC' });
    expect(write.status()).toBe(201);
    const organizerId = (await write.json()).data.id;

    const page = await freshAccount.newAuthedPage();
    await page.goto(`/manage/organizer/${organizerId}`);
    await expect(page.getByText('Made by the API').first()).toBeVisible();

    expireApiKey(created.id);
    const afterExpiry = await callApi(publicApi, created.key, 'GET', 'users/me');
    expect(afterExpiry.status()).toBe(401);
  });

  test('expired keys are grouped separately and can be removed', async ({ freshAccount }) => {
    const created = await freshAccount.api.createApiKey({ name: 'Old CRM', access: 'READ_WRITE', expires_in_days: 30, password: freshAccount.password });
    await freshAccount.api.createApiKey({ name: 'Current sync', access: 'READ_WRITE', expires_in_days: 7, password: freshAccount.password });
    expireApiKey(created.id);

    const page = await freshAccount.newAuthedPage();
    const apiKeys = new ApiKeysPage(page);
    await apiKeys.goto();

    await expect(apiKeys.row('Current sync')).toContainText(/Expires in \d+ days/);
    await expect(page.getByText(/^Expired\s*1$/)).toBeVisible();
    await expect(apiKeys.row('Old CRM')).toContainText('Expired 2 days ago');

    await apiKeys.revoke('Old CRM');
    await expect(apiKeys.row('Old CRM')).toHaveCount(0);
    await expect(apiKeys.row('Current sync')).toBeVisible();
  });

  test('a single sign-on user confirms it is them and picks up where they left off', async ({ page }) => {
    test.skip(!(await providerIsConfigured(page)), 'No OIDC provider is configured on this stack');

    const email = uniqueEmail('sso-api');
    const subject = `subject-${email}`;
    const sso = new SsoPage(page);
    await page.goto('/auth/register');
    await sso.providerButton().click();
    await sso.signInAtProvider(subject, { email, email_verified: true, given_name: 'Ada', family_name: 'Lovelace' });
    await sso.completeSignup();
    await expect(page).toHaveURL(/\/welcome/);

    const apiKeys = new ApiKeysPage(page);
    await apiKeys.goto();
    await apiKeys.openCreate();
    await page.getByTestId('api-key-name').fill('Signed in with SSO');
    await page.getByTestId('api-key-access-read-only').click();
    await expect(page.getByTestId('api-key-password')).toHaveCount(0);
    await expect(page.getByTestId('api-key-create-submit')).toBeDisabled();

    await apiKeys.dialog().getByTestId('sso-provider-button-oidc').click();
    await sso.signInAtProvider(subject, { email, email_verified: true });

    await expect(apiKeys.dialog()).toContainText("You confirmed it's you.");
    await expect(page.getByTestId('api-key-name')).toHaveValue('Signed in with SSO');
    await expect(page.getByTestId('api-key-access-read-only')).toBeChecked();
    await apiKeys.submitCreate();

    await expect(page.getByTestId('api-key-secret')).toContainText('hie_');
    await page.getByTestId('api-key-copy').click();
    await page.getByTestId('api-key-done').click();
    await expect(apiKeys.row('Signed in with SSO')).toContainText('Read-only');
  });

  test('the API reference opens from the API keys page and only lists what keys can call', async ({ freshAccount }) => {
    const page = await freshAccount.newAuthedPage();
    const apiKeys = new ApiKeysPage(page);

    await apiKeys.goto();
    await page.getByTestId('api-reference-link').click();
    await expect(page).toHaveURL(/\/settings\/api-keys\/reference/);

    const reference = page.getByTestId('api-reference');
    await expect(reference.getByRole('heading', { name: /API$/, level: 1 })).toBeVisible({ timeout: 30_000 });
    await expect(reference).toContainText('API_KEY_READ_ONLY');
    await expect(reference.getByRole('link', { name: 'Events', exact: true })).toBeVisible();
    await expect(reference.getByRole('link', { name: 'Webhooks', exact: false }).first()).toBeVisible();
    await expect(reference.getByRole('link', { name: 'Api Keys', exact: true })).toHaveCount(0);
    await expect(reference.getByRole('link', { name: 'Accounts / Sso', exact: true })).toHaveCount(0);
    await expect(reference.getByText('Ask AI')).toHaveCount(0);
    await expect(page.getByRole('link', { name: 'API keys' }).first()).toBeVisible();
  });

  test.describe('on a phone', () => {
    test.use({ viewport: { width: 375, height: 812 } });

    test('the whole flow fits the screen', async ({ freshAccount }) => {
      const page = await freshAccount.newAuthedPage();
      await page.setViewportSize({ width: 375, height: 812 });
      const apiKeys = new ApiKeysPage(page);

      await apiKeys.goto();
      expect(await hasHorizontalOverflow(page)).toBe(false);

      await apiKeys.openCreate();
      await apiKeys.fillCreateForm({ name: 'A key with a rather long descriptive name for a phone', password: freshAccount.password });
      await apiKeys.submitCreate();
      await expect(page.getByTestId('api-key-secret')).toBeVisible();
      await expect(page.getByTestId('api-key-copy')).toBeInViewport();
      expect(await hasHorizontalOverflow(page)).toBe(false);
      await page.getByTestId('api-key-copy').click();
      await page.getByTestId('api-key-done').click();

      const row = apiKeys.row('A key with a rather long');
      await expect(row).toBeVisible();
      await expect(row.getByText('Last used')).toBeVisible();
      await expect(row.getByTestId('api-key-revoke')).toBeInViewport();
      expect(await hasHorizontalOverflow(page)).toBe(false);

      await page.getByTestId('api-reference-link').click();
      await expect(page.getByTestId('api-reference').getByRole('heading', { name: /API$/, level: 1 })).toBeVisible({ timeout: 30_000 });
      expect(await hasHorizontalOverflow(page)).toBe(false);
    });
  });
});
