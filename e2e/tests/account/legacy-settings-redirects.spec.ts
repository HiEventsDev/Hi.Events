import {expect, test} from '../../fixtures';

const legacyLinks: [string, string][] = [
  ['/account', '/settings/account'],
  ['/account/users', '/settings/team'],
  ['/account/taxes-and-fees', '/settings/taxes-and-fees'],
  ['/account/danger-zone', '/settings/account#delete-account'],
  ['/account/settings', '/settings/account'],
  ['/manage/account', '/settings/account'],
  ['/manage/profile', '/settings/profile'],
  ['/manage/profile/security', '/settings/security'],
  ['/manage/profile/password', '/settings/security'],
  ['/settings', '/settings/profile'],
];

test.describe('@smoke legacy settings links', () => {
  test('redirect on the server, before any JavaScript runs', async ({request}) => {
    for (const [legacyPath, settingsPath] of legacyLinks) {
      const response = await request.get(legacyPath, {maxRedirects: 0});

      expect(response.status(), legacyPath).toBe(302);
      expect(response.headers()['location'], legacyPath).toBe(settingsPath);
    }
  });
});
