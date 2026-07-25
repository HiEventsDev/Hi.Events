export const BASE_URL = process.env.E2E_BASE_URL ?? 'http://localhost:8123';
export const API_BASE_URL = `${BASE_URL}/api/`;
export const MAILPIT_URL = process.env.MAILPIT_URL ?? 'http://localhost:8225';
export const IS_SAAS_MODE = (process.env.E2E_SAAS_MODE ?? 'false') === 'true';
export const STRIPE_PUBLIC_KEY = process.env.STRIPE_PUBLIC_KEY ?? '';
export const STRIPE_SECRET_KEY = process.env.STRIPE_SECRET_KEY ?? '';
export const STRIPE_WEBHOOK_SECRET = process.env.STRIPE_WEBHOOK_SECRET ?? 'whsec_e2e_local_secret';
export const SUPERADMIN_EMAIL = process.env.E2E_SUPERADMIN_EMAIL ?? 'superadmin@e2e.test';
export const SUPERADMIN_PASSWORD = process.env.E2E_SUPERADMIN_PASSWORD ?? 'SuperAdminPass123!';

export const cookieDomain = (): string => new URL(BASE_URL).hostname;
