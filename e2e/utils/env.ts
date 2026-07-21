export const BASE_URL = process.env.E2E_BASE_URL ?? 'http://localhost:8123';
export const API_BASE_URL = `${BASE_URL}/api/`;
export const MAILPIT_URL = process.env.MAILPIT_URL ?? 'http://localhost:8225';
export const IS_SAAS_MODE = (process.env.E2E_SAAS_MODE ?? 'false') === 'true';
export const STRIPE_PUBLIC_KEY = process.env.STRIPE_PUBLIC_KEY ?? '';

export const cookieDomain = (): string => new URL(BASE_URL).hostname;
