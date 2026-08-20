import type { APIRequestContext } from '@playwright/test';
import type { ApiClient } from '../api/api-client';
import { confirmEmailWithCode, login, registerAccount } from '../api/api-client';
import type { MailpitClient } from '../utils/mailpit';
import { uniqueEmail } from '../utils/unique';
import { IS_SAAS_MODE } from '../utils/env';

export interface AccountIdentity {
  email: string;
  password: string;
  token: string;
  userId: number;
}

export interface AccountContext extends AccountIdentity {
  organizerId: number;
  api: ApiClient;
}

const PASSWORD = 'Password123!';

export async function bootstrapAccount(anon: APIRequestContext, mailpit: MailpitClient): Promise<AccountIdentity> {
  const email = uniqueEmail();

  await registerAccount(anon, {
    first_name: 'E2E',
    last_name: 'Organizer',
    email,
    password: PASSWORD,
    password_confirmation: PASSWORD,
    timezone: 'UTC',
    currency_code: 'USD',
    locale: 'en',
  });

  const { token, user } = await login(anon, email, PASSWORD);

  if (IS_SAAS_MODE && !user.is_email_verified) {
    const code = await mailpit.waitForVerificationCode(email);
    await confirmEmailWithCode(anon, token, user.id, code);
  }

  return { email, password: PASSWORD, token, userId: user.id };
}
