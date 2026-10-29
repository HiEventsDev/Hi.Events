import { expect, type APIRequest, type APIRequestContext } from '@playwright/test';
import { ApiClient, login } from '../../api/api-client';
import { bootstrapAccount } from '../../fixtures/account.fixture';
import { API_BASE_URL } from '../../utils/env';
import type { MailpitClient } from '../../utils/mailpit';

export const MEMBER_PASSWORD = 'MemberPass123!';

export const latestInvitationPath = async (mailpit: MailpitClient, email: string, invitationCount: number): Promise<string> => {
  const invitations = async () => (await mailpit.search(email)).filter((message) => message.Subject.includes('invited to join'));
  await expect.poll(async () => (await invitations()).length, { timeout: 15_000 }).toBeGreaterThanOrEqual(invitationCount);
  const [latest] = await invitations();
  const link = (await mailpit.getLinks(latest.ID)).find((href) => href.includes('accept-invitation'));
  return new URL(link as string).pathname;
};

export const acceptWithPassword = async (publicApi: APIRequestContext, invitationPath: string): Promise<void> => {
  const accept = await publicApi.post(`auth/invitation/${invitationPath.split('/').pop()}`, {
    data: { first_name: 'Kim', last_name: 'Member', password: MEMBER_PASSWORD, password_confirmation: MEMBER_PASSWORD, timezone: 'UTC' },
  });
  expect(accept.ok()).toBeTruthy();
};

export const apiAs = (playwright: { request: APIRequest }, token: string): Promise<APIRequestContext> =>
  playwright.request.newContext({
    baseURL: API_BASE_URL,
    ignoreHTTPSErrors: true,
    extraHTTPHeaders: { Accept: 'application/json', Authorization: `Bearer ${token}` },
  });

export const joinAnotherAccount = async (
  playwright: { request: APIRequest },
  publicApi: APIRequestContext,
  mailpit: MailpitClient,
  memberEmail: string,
): Promise<void> => {
  const otherOwner = await bootstrapAccount(publicApi, mailpit);
  const otherOwnerApi = await apiAs(playwright, otherOwner.token);
  const invitationCount = (await mailpit.search(memberEmail)).filter((message) => message.Subject.includes('invited to join')).length;
  await new ApiClient(otherOwnerApi).inviteUser({ first_name: 'Kim', last_name: 'Member', email: memberEmail, role: 'ORGANIZER' });
  const invitationPath = await latestInvitationPath(mailpit, memberEmail, invitationCount + 1);

  const memberApi = await apiAs(playwright, (await login(publicApi, memberEmail, MEMBER_PASSWORD)).token);
  expect((await memberApi.post(`auth/invitation/${invitationPath.split('/').pop()}/join`)).ok()).toBeTruthy();
  await otherOwnerApi.dispose();
  await memberApi.dispose();
};
