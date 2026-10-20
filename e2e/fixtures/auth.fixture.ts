import type { Browser, BrowserContextOptions, Page } from '@playwright/test';
import { BASE_URL, cookieDomain } from '../utils/env';
import { grantedConsentCookie } from '../utils/consent';

export function buildStorageState(token: string): BrowserContextOptions['storageState'] {
  return {
    cookies: [
      {
        name: 'token',
        value: token,
        domain: cookieDomain(),
        path: '/',
        expires: -1,
        httpOnly: true,
        secure: true,
        sameSite: 'None',
      },
      grantedConsentCookie(),
    ],
    origins: [],
  };
}

export async function openAuthedPage(browser: Browser, token: string): Promise<Page> {
  const context = await browser.newContext({
    baseURL: BASE_URL,
    ignoreHTTPSErrors: true,
    storageState: buildStorageState(token),
  });
  return context.newPage();
}
