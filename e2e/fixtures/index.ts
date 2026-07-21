import { test as base, type Page } from '@playwright/test';
import { ApiClient } from '../api/api-client';
import { MailpitClient } from '../utils/mailpit';
import { bootstrapAccount, type AccountContext } from './account.fixture';
import { buildStorageState } from './auth.fixture';
import { API_BASE_URL, BASE_URL } from '../utils/env';
import { uniqueName } from '../utils/unique';

interface WorkerFixtures {
  /** A registered, verified account with an organizer and an authorized API client. Shared per worker. */
  account: AccountContext;
  /** Authorized API client for arranging data. Alias of `account.api`. */
  api: ApiClient;
}

interface TestFixtures {
  /** A page pre-authenticated as `account` (auth cookie injected — no UI login). */
  authedPage: Page;
  /** Mailpit client for asserting on outbound email. */
  mailpit: MailpitClient;
}

export const test = base.extend<TestFixtures, WorkerFixtures>({
  account: [
    async ({ playwright }, use) => {
      const anon = await playwright.request.newContext({
        baseURL: API_BASE_URL,
        ignoreHTTPSErrors: true,
        extraHTTPHeaders: { Accept: 'application/json' },
      });
      const mailpit = new MailpitClient(anon);
      const identity = await bootstrapAccount(anon, mailpit);

      const authed = await playwright.request.newContext({
        baseURL: API_BASE_URL,
        ignoreHTTPSErrors: true,
        extraHTTPHeaders: { Accept: 'application/json', Authorization: `Bearer ${identity.token}` },
      });
      const api = new ApiClient(authed);
      const organizer = await api.createOrganizer(uniqueName('E2E Org'));

      await use({ ...identity, organizerId: organizer.id, api });

      await anon.dispose();
      await authed.dispose();
    },
    { scope: 'worker' },
  ],

  api: [
    async ({ account }, use) => {
      await use(account.api);
    },
    { scope: 'worker' },
  ],

  mailpit: async ({ request }, use) => {
    await use(new MailpitClient(request));
  },

  authedPage: async ({ browser, account }, use) => {
    const context = await browser.newContext({
      baseURL: BASE_URL,
      ignoreHTTPSErrors: true,
      storageState: buildStorageState(account.token),
    });
    const page = await context.newPage();
    await use(page);
    await context.close();
  },
});

export { expect } from '@playwright/test';
