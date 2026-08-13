import { test as base, type APIRequest, type APIRequestContext, type BrowserContext, type Page } from '@playwright/test';
import { AdminApiClient, ApiClient, login } from '../api/api-client';
import { MailpitClient } from '../utils/mailpit';
import { bootstrapAccount, type AccountContext, type AccountIdentity } from './account.fixture';
import { buildStorageState, openAuthedPage } from './auth.fixture';
import { API_BASE_URL, BASE_URL, IS_SAAS_MODE, SUPERADMIN_EMAIL, SUPERADMIN_PASSWORD } from '../utils/env';
import { uniqueName } from '../utils/unique';

export interface FreshAccount extends AccountIdentity {
  organizerId: number;
  api: ApiClient;
  newAuthedPage(): Promise<Page>;
}

interface SuperAdminAuth {
  token: string;
}

const TRUSTED_MESSAGING_TIER_ID = 2;

/**
 * In SaaS mode a new account cannot message attendees or manage email templates until it is
 * verified, and its messages are held for review on the default tier. Grant both so specs
 * exercise the feature rather than the gate.
 *
 * A missing superadmin is tolerated (local stacks without `dev:bootstrap`); a failing admin
 * call is not, so a broken grant surfaces here rather than as a confusing gate assertion.
 */
const grantMessagingPrivileges = async (
  playwright: { request: APIRequest },
  anon: APIRequestContext,
  account: { id: number },
): Promise<void> => {
  let token: string;
  try {
    ({ token } = await login(anon, SUPERADMIN_EMAIL, SUPERADMIN_PASSWORD));
  } catch {
    return;
  }

  const adminContext = await newApiContext(playwright, { Authorization: `Bearer ${token}` });
  try {
    const adminApi = new AdminApiClient(adminContext);
    await adminApi.setAccountVerification(account.id, true);
    await adminApi.setMessagingTier(account.id, TRUSTED_MESSAGING_TIER_ID);
  } finally {
    await adminContext.dispose();
  }
};

interface WorkerFixtures {
  /** A registered, verified account with an organizer and an authorized API client. Shared per worker. */
  account: AccountContext;
  /** Authorized API client for arranging data. Alias of `account.api`. */
  api: ApiClient;
  /** Superadmin token from the provisioned dev:bootstrap account, or null if unavailable. */
  superAdminAuth: SuperAdminAuth | null;
}

interface TestFixtures {
  /** A page pre-authenticated as `account` (auth cookie injected — no UI login). */
  authedPage: Page;
  /** Mailpit client for asserting on outbound email. */
  mailpit: MailpitClient;
  /** Anonymous API context rooted at the API base URL, for public (buyer-side) endpoints. */
  publicApi: APIRequestContext;
  /** A brand-new account + organizer, isolated to this test. Use for account/auth-level mutations. */
  freshAccount: FreshAccount;
  /** Admin API client authenticated as the provisioned superadmin. Skips the test if unavailable locally. */
  adminApi: AdminApiClient;
  /** A page pre-authenticated as the superadmin. Skips the test if unavailable locally. */
  superAdminPage: Page;
}

const newApiContext = (
  playwright: { request: APIRequest },
  extraHeaders: Record<string, string> = {},
): Promise<APIRequestContext> =>
  playwright.request.newContext({
    baseURL: API_BASE_URL,
    ignoreHTTPSErrors: true,
    extraHTTPHeaders: { Accept: 'application/json', ...extraHeaders },
  });

export const test = base.extend<TestFixtures, WorkerFixtures>({
  account: [
    async ({ playwright }, use) => {
      const anon = await newApiContext(playwright);
      const mailpit = new MailpitClient(anon);
      const identity = await bootstrapAccount(anon, mailpit);

      const authed = await newApiContext(playwright, { Authorization: `Bearer ${identity.token}` });
      const api = new ApiClient(authed);
      const organizer = await api.createOrganizer(uniqueName('E2E Org'));

      if (IS_SAAS_MODE) {
        await grantMessagingPrivileges(playwright, anon, await api.getAccount());
      }

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

  superAdminAuth: [
    async ({ playwright }, use) => {
      const anon = await newApiContext(playwright);
      let auth: SuperAdminAuth | null = null;
      try {
        const { token } = await login(anon, SUPERADMIN_EMAIL, SUPERADMIN_PASSWORD);
        auth = { token };
      } catch (error) {
        if (process.env.CI) {
          throw new Error(
            `Superadmin login failed in CI — check the "Provisioning superadmin" step in e2e/run-e2e.sh. ${error}`,
          );
        }
      } finally {
        await anon.dispose();
      }
      await use(auth);
    },
    { scope: 'worker' },
  ],

  mailpit: async ({ request }, use) => {
    await use(new MailpitClient(request));
  },

  publicApi: async ({ playwright }, use) => {
    const context = await newApiContext(playwright);
    await use(context);
    await context.dispose();
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

  freshAccount: async ({ playwright, browser }, use) => {
    const anon = await newApiContext(playwright);
    const mailpit = new MailpitClient(anon);
    const identity = await bootstrapAccount(anon, mailpit);

    const authed = await newApiContext(playwright, { Authorization: `Bearer ${identity.token}` });
    const api = new ApiClient(authed);
    const organizer = await api.createOrganizer(uniqueName('E2E Fresh Org'));

    const contexts: BrowserContext[] = [];
    await use({
      ...identity,
      organizerId: organizer.id,
      api,
      newAuthedPage: async () => {
        const page = await openAuthedPage(browser, identity.token);
        contexts.push(page.context());
        return page;
      },
    });

    for (const context of contexts) {
      await context.close();
    }
    await anon.dispose();
    await authed.dispose();
  },

  adminApi: async ({ playwright, superAdminAuth }, use, testInfo) => {
    testInfo.skip(
      !superAdminAuth,
      `No superadmin available — provision one with: php artisan dev:bootstrap --email=${SUPERADMIN_EMAIL}`,
    );
    const context = await newApiContext(playwright, { Authorization: `Bearer ${superAdminAuth!.token}` });
    await use(new AdminApiClient(context));
    await context.dispose();
  },

  superAdminPage: async ({ browser, superAdminAuth }, use, testInfo) => {
    testInfo.skip(
      !superAdminAuth,
      `No superadmin available — provision one with: php artisan dev:bootstrap --email=${SUPERADMIN_EMAIL}`,
    );
    const page = await openAuthedPage(browser, superAdminAuth!.token);
    await use(page);
    await page.context().close();
  },
});

export { expect } from '@playwright/test';
