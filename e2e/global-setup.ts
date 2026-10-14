import { request } from '@playwright/test';
import { login } from './api/api-client';
import { API_BASE_URL, SUPERADMIN_EMAIL, SUPERADMIN_PASSWORD } from './utils/env';

export default async function globalSetup(): Promise<void> {
  const anon = await request.newContext({
    baseURL: API_BASE_URL,
    ignoreHTTPSErrors: true,
    extraHTTPHeaders: { Accept: 'application/json' },
  });
  try {
    const { token } = await login(anon, SUPERADMIN_EMAIL, SUPERADMIN_PASSWORD);
    process.env.E2E_SUPERADMIN_TOKEN = token;
  } catch (error) {
    if (process.env.CI) {
      throw new Error(`Superadmin login failed in CI — check the "Provisioning superadmin" step in e2e/run-e2e.sh. ${error}`);
    }
  } finally {
    await anon.dispose();
  }
}
