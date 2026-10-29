import { test } from '../fixtures';
import { IS_SAAS_MODE } from './env';

export const nonSaasOnly = (): void =>
  test.skip(IS_SAAS_MODE, 'Runs only against a non-SaaS stack (E2E_SAAS_MODE=false).');

export const saasOnly = (): void =>
  test.skip(!IS_SAAS_MODE, 'Runs only against a SaaS stack (E2E_SAAS_MODE=true).');
