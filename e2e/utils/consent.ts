import type { BrowserContextOptions } from '@playwright/test';
import { cookieDomain } from './env';

type StorageState = Exclude<BrowserContextOptions['storageState'], string | undefined>;
type StorageCookie = StorageState['cookies'][number];

export const CONSENT_COOKIE = 'hi_cookie_consent';

export const grantedConsentCookie = (): StorageCookie => ({
  name: CONSENT_COOKIE,
  value: 'analytics=1&advertising=1',
  domain: cookieDomain(),
  path: '/',
  expires: -1,
  httpOnly: false,
  secure: false,
  sameSite: 'Lax',
});

export const noStoredConsent: StorageState = { cookies: [], origins: [] };
