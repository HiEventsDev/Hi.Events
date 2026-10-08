import type { APIRequest, Page } from '@playwright/test';
import { ApiClient } from '../api/api-client';
import { API_BASE_URL } from './env';

export const LICENCE_SIMULATION_HEADER = 'X-Hi-Licence-Simulation';

export type SimulatedLicence = 'ACTIVE' | 'EXPIRING' | 'GRACE' | 'LAPSED' | 'NONE' | 'INVALID' | 'DEV' | 'CLOUD' | `${'ACTIVE' | 'LAPSED'}:${string}`;

export async function simulateLicence(page: Page, licence: SimulatedLicence): Promise<void> {
  await page.context().setExtraHTTPHeaders({ [LICENCE_SIMULATION_HEADER]: licence });
}

export async function withLicence<T>(
  playwright: { request: APIRequest },
  token: string,
  licence: SimulatedLicence,
  run: (api: ApiClient) => Promise<T>,
): Promise<T> {
  const context = await playwright.request.newContext({
    baseURL: API_BASE_URL,
    ignoreHTTPSErrors: true,
    extraHTTPHeaders: {
      Accept: 'application/json',
      Authorization: `Bearer ${token}`,
      [LICENCE_SIMULATION_HEADER]: licence,
    },
  });
  try {
    return await run(new ApiClient(context));
  } finally {
    await context.dispose();
  }
}
