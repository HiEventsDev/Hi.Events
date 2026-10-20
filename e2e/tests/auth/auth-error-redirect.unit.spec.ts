import { expect, test } from '@playwright/test';
import { resolveAuthErrorAction } from '../../../frontend/src/api/authErrorRedirect';

const LOGIN = { type: 'login' };
const NONE = { type: 'none' };

test.describe('api client auth error handling', () => {
  test('an expired session on any manage page sends the user to sign in', () => {
    for (const path of [
      '/manage/organizer/1/widget',
      '/manage/organizer/1/events',
      '/manage/organizer/1/events/archived',
      '/manage/events',
      '/manage/events/upcoming',
      '/manage/event/1/dashboard',
      '/manage/event/1/check-in',
      '/manage/event/1/widget',
      '/manage/event/1/ticket-designer/print',
      '/settings/team',
      '/admin/accounts',
      '/welcome',
    ]) {
      expect(resolveAuthErrorAction(401, undefined, path), path).toEqual(LOGIN);
      expect(resolveAuthErrorAction(401, 'SSO_SESSION_EXPIRED', path), path).toEqual(LOGIN);
      expect(resolveAuthErrorAction(403, undefined, path), path).toEqual(LOGIN);
      expect(resolveAuthErrorAction(403, 'SSO_REQUIRED', path), path).toEqual(LOGIN);
    }
  });

  test('public pages never redirect to sign in', () => {
    for (const path of [
      '/widget/1',
      '/widget/organizer/1',
      '/checkout/1/abc/details',
      '/event/1/my-event',
      '/events/1/my-organizer',
      '/order/1/abc/print',
      '/product/1/abc',
      '/check-in/abc',
      '/box-office/abc',
      '/my-tickets/abc',
      '/emails/unsubscribe/abc',
      '/auth/login',
      '/auth/register',
      '/auth/accept-invitation/abc',
      '/sso/callback',
    ]) {
      expect(resolveAuthErrorAction(401, undefined, path), path).toEqual(NONE);
      expect(resolveAuthErrorAction(403, undefined, path), path).toEqual(NONE);
    }
  });

  test('access errors stay on the page everywhere', () => {
    for (const code of ['FEATURE_UNAVAILABLE', 'ACCESS_RESTRICTED', 'PERMISSION_REQUIRED', 'READ_ONLY_ROLE', 'IMPERSONATION_NOT_ALLOWED']) {
      for (const path of ['/manage/event/1/dashboard', '/manage/organizer/1/widget', '/settings/team', '/widget/1']) {
        expect(resolveAuthErrorAction(403, code, path), `${code} ${path}`).toEqual(NONE);
      }
    }
  });

  test('other statuses are left to the caller', () => {
    for (const status of [400, 404, 409, 422, 429, 500]) {
      expect(resolveAuthErrorAction(status, undefined, '/manage/event/1/dashboard')).toEqual(NONE);
    }
  });

  test('a required two-factor setup redirects once', () => {
    expect(resolveAuthErrorAction(403, 'TWO_FACTOR_SETUP_REQUIRED', '/manage/events'))
      .toEqual({ type: 'redirect', to: '/auth/two-factor-setup' });
    expect(resolveAuthErrorAction(403, 'TWO_FACTOR_SETUP_REQUIRED', '/auth/two-factor-setup')).toEqual(NONE);
  });

  test('an account pending deletion redirects to the delete section, but never away from settings', () => {
    for (const path of ['/manage/events', '/manage/event/1/dashboard', '/manage/organizer/1/widget', '/welcome']) {
      expect(resolveAuthErrorAction(403, 'ACCOUNT_PENDING_DELETION', path), path)
        .toEqual({ type: 'redirect', to: '/settings/account#delete-account' });
    }

    for (const path of ['/settings/account', '/settings/profile', '/settings/security', '/settings/team']) {
      expect(resolveAuthErrorAction(403, 'ACCOUNT_PENDING_DELETION', path), path).toEqual(NONE);
    }
  });
});
