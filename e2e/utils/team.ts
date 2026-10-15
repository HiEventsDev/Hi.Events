import type { APIRequestContext, Browser, Page } from '@playwright/test';
import { expect } from '@playwright/test';
import { openAuthedPage } from '../fixtures/auth.fixture';
import { login } from '../api/api-client';
import type { InviteUserPayload } from '../api/types';
import type { FreshAccount } from '../fixtures';
import type { MailpitClient } from './mailpit';
import { uniqueEmail } from './unique';

const MEMBER_PASSWORD = 'MemberPass123!';

export async function invitedMember(
  account: FreshAccount,
  mailpit: MailpitClient,
  publicApi: APIRequestContext,
  browser: Browser,
  invite: Omit<InviteUserPayload, 'email' | 'first_name'>,
): Promise<{ page: Page; email: string }> {
  const email = uniqueEmail('member');
  await account.api.inviteUser({ first_name: 'Sam', last_name: 'Member', email, ...invite });
  const inviteUrl = await mailpit.waitForLink(email, /accept-invitation/, { subjectContains: 'invited to join' });
  const accept = await publicApi.post(`auth/invitation/${inviteUrl.pathname.split('/').pop()}`, {
    data: { first_name: 'Sam', last_name: 'Member', password: MEMBER_PASSWORD, password_confirmation: MEMBER_PASSWORD, timezone: 'UTC' },
  });
  expect(accept.ok()).toBeTruthy();
  const { token } = await login(publicApi, email, MEMBER_PASSWORD);
  return { page: await openAuthedPage(browser, token), email };
}
