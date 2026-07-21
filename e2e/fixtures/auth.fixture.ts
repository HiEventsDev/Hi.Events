import type { BrowserContextOptions } from '@playwright/test';
import { cookieDomain } from '../utils/env';

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
    ],
    origins: [],
  };
}
