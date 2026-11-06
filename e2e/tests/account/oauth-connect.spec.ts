import type { APIRequestContext } from '@playwright/test';
import { expect, test } from '../../fixtures';
import { ApiKeysPage } from '../../pages/api-keys.page';
import { OAUTH_REDIRECT_URI, OAuthConnectPage, startOAuthAuthorization, type OAuthAuthorization } from '../../pages/oauth-connect.page';
import { BASE_URL } from '../../utils/env';
import { uniqueName } from '../../utils/unique';

const exchangeCode = async (publicApi: APIRequestContext, authorization: OAuthAuthorization, code: string) => {
  const response = await publicApi.post('oauth/token', {
    form: {
      grant_type: 'authorization_code',
      client_id: authorization.clientId,
      code,
      redirect_uri: OAUTH_REDIRECT_URI,
      code_verifier: authorization.verifier,
    },
  });
  expect(response.status()).toBe(200);
  return response.json();
};

const listTools = (publicApi: APIRequestContext, accessToken: string) =>
  publicApi.post('mcp', {
    data: { jsonrpc: '2.0', id: 1, method: 'tools/list', params: {} },
    headers: {
      Authorization: `Bearer ${accessToken}`,
      Accept: 'application/json, text/event-stream',
      'Content-Type': 'application/json',
      'MCP-Protocol-Version': '2025-11-25',
      'Mcp-Method': 'tools/list',
    },
  });

test.describe('Connecting an AI app with OAuth', () => {
  test('a member connects an app, it calls the MCP server, and disconnecting cuts it off @smoke', async ({ freshAccount, publicApi, mailpit }) => {
    const appName = uniqueName('Claude Code');
    const authorization = await startOAuthAuthorization(publicApi, appName);
    const page = await freshAccount.newAuthedPage();
    const connect = new OAuthConnectPage(page);
    const callback = await connect.captureCallback();

    await connect.open(authorization);
    await expect(connect.consent()).toContainText(`Connect ${appName}`);
    await expect(connect.consent()).toContainText('an app on this computer');
    await expect(connect.consent()).toContainText('Only continue if you just started connecting from an app on this computer');

    await connect.chooseReadOnly();
    await connect.approve(freshAccount.password);

    const params = await callback();
    expect(params.get('state')).toBe(authorization.state);
    expect(params.get('iss')).toBeTruthy();

    const tokens = await exchangeCode(publicApi, authorization, params.get('code') ?? '');
    expect(tokens.scope).toBe('mcp:read');

    const tools = await listTools(publicApi, tokens.access_token);
    expect(tools.status()).toBe(200);
    expect((await tools.json()).result.tools.length).toBeGreaterThan(0);

    await mailpit.waitForMessage(freshAccount.email, { subjectContains: 'A new app was connected' });

    const apiKeys = new ApiKeysPage(page);
    await apiKeys.goto();
    await expect(apiKeys.connectedApp(appName)).toContainText('Read-only');
    await expect(apiKeys.connectedApp(appName)).toContainText('App on your computer');

    await apiKeys.disconnectApp(appName);
    await expect(apiKeys.connectedAppsEmpty()).toBeVisible();

    expect((await listTools(publicApi, tokens.access_token)).status()).toBe(401);
  });

  test('a signed-out member signs in and lands back on the consent screen', async ({ browser, freshAccount, publicApi }) => {
    const appName = uniqueName('Cursor');
    const authorization = await startOAuthAuthorization(publicApi, appName);
    const context = await browser.newContext({ baseURL: BASE_URL, ignoreHTTPSErrors: true });
    const page = await context.newPage();
    const connect = new OAuthConnectPage(page);

    await connect.open(authorization);
    await page.waitForURL(/\/auth\/login/);
    await page.getByRole('heading', { name: 'Welcome back' }).waitFor();
    await page.waitForLoadState('networkidle');
    await page.getByLabel(/^Email/).fill(freshAccount.email);
    await page.getByLabel(/^Password/).fill(freshAccount.password);
    await page.getByRole('button', { name: 'Sign in', exact: true }).click();

    await expect(connect.consent()).toContainText(`Connect ${appName}`);
    await expect(page.getByTestId('oauth-connect-access-write')).toBeChecked();

    await context.close();
  });

  test('cancelling sends the app an access_denied error', async ({ freshAccount, publicApi }) => {
    const authorization = await startOAuthAuthorization(publicApi, uniqueName('ChatGPT'));
    const page = await freshAccount.newAuthedPage();
    const connect = new OAuthConnectPage(page);
    const callback = await connect.captureCallback();

    await connect.open(authorization);
    await expect(connect.consent()).toBeVisible();
    await connect.deny();

    const params = await callback();
    expect(params.get('error')).toBe('access_denied');
    expect(params.get('state')).toBe(authorization.state);
    expect(params.has('code')).toBe(false);
  });

  test('a link for an unknown app shows an error instead of a consent screen', async ({ freshAccount }) => {
    const page = await freshAccount.newAuthedPage();
    const connect = new OAuthConnectPage(page);

    await page.goto(`/api/oauth/authorize?client_id=unknown&redirect_uri=${encodeURIComponent(OAUTH_REDIRECT_URI)}`);

    await expect(connect.error()).toContainText("This connection link doesn't work");
    await expect(connect.consent()).toHaveCount(0);
  });
});
