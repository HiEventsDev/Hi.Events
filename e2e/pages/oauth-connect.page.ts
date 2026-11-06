import { createHash, randomBytes } from 'node:crypto';
import type { APIRequestContext, Locator, Page } from '@playwright/test';
import { API_BASE_URL } from '../utils/env';

export const OAUTH_REDIRECT_URI = 'http://127.0.0.1:53999/callback';

export interface OAuthAuthorization {
  clientId: string;
  verifier: string;
  state: string;
  url: string;
}

const base64Url = (buffer: Buffer): string => buffer.toString('base64').replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');

export const startOAuthAuthorization = async (publicApi: APIRequestContext, clientName: string): Promise<OAuthAuthorization> => {
  const registration = await publicApi.post('oauth/register', {
    data: { client_name: clientName, redirect_uris: [OAUTH_REDIRECT_URI] },
  });
  const { client_id: clientId } = await registration.json();
  const verifier = base64Url(randomBytes(48));
  const state = base64Url(randomBytes(8));
  const query = new URLSearchParams({
    client_id: clientId,
    redirect_uri: OAUTH_REDIRECT_URI,
    response_type: 'code',
    code_challenge: base64Url(createHash('sha256').update(verifier).digest()),
    code_challenge_method: 'S256',
    state,
    scope: 'mcp:read mcp:write',
  });

  return { clientId, verifier, state, url: `${API_BASE_URL}oauth/authorize?${query}` };
};

export class OAuthConnectPage {
  constructor(private readonly page: Page) {}

  async open(authorization: OAuthAuthorization): Promise<void> {
    await this.page.goto(authorization.url);
  }

  consent(): Locator {
    return this.page.getByTestId('oauth-connect');
  }

  error(): Locator {
    return this.page.getByTestId('oauth-connect-error');
  }

  async captureCallback(): Promise<() => Promise<URLSearchParams>> {
    let resolveCallback: (params: URLSearchParams) => void;
    const received = new Promise<URLSearchParams>((resolve) => {
      resolveCallback = resolve;
    });

    await this.page.route(`${OAUTH_REDIRECT_URI}**`, async (route) => {
      resolveCallback(new URL(route.request().url()).searchParams);
      await route.fulfill({ status: 200, contentType: 'text/plain', body: 'Callback received' });
    });

    return () => received;
  }

  async chooseReadOnly(): Promise<void> {
    await this.page.getByTestId('oauth-connect-access-read').check();
  }

  async approve(password: string): Promise<void> {
    await this.page.getByTestId('oauth-connect-password').fill(password);
    await this.page.getByTestId('oauth-connect-approve').click();
  }

  async deny(): Promise<void> {
    await this.page.getByTestId('oauth-connect-deny').click();
  }
}
