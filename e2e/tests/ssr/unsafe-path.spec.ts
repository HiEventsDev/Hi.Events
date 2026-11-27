import {expect, test} from '../../fixtures';

const CRAFTED_TOKENS = [
  ['backslashes', 'x%5C..%5C..%5C..%5Cusers%3Ffirst_name%3DEve%26email%3Dnot-an-email%26role%3DADMIN%26z%3D'],
  ['encoded slashes', 'x%2F..%2F..%2Fusers%3Ffirst_name%3DEve%26role%3DADMIN%26z%3D'],
  ['a query string', 'x%3Ffirst_name%3DEve%26role%3DADMIN'],
  ['encoded dots split by a tab', '%2E%09%2E'],
  ['double-encoded slashes', 'x%252Fattendees%252Fy'],
] as const;

test.describe('links whose path would steer an API request elsewhere', () => {
  for (const [name, token] of CRAFTED_TOKENS) {
    test(`a ticket link built from ${name} is not found and writes nothing`, {tag: '@smoke'}, async ({page}) => {
      const writes: string[] = [];
      page.on('request', (request) => {
        if (request.method() !== 'GET' && request.url().includes('/api/')) {
          writes.push(`${request.method()} ${request.url()}`);
        }
      });

      const response = await page.goto(`/my-tickets/${token}`);

      expect(response?.status()).toBe(404);
      await expect(page.getByRole('heading', {name: 'Page not found'})).toBeVisible();
      await page.waitForLoadState('networkidle');
      expect(writes).toEqual([]);
    });
  }

  test('an ordinary ticket link still loads', async ({page}) => {
    const response = await page.goto('/my-tickets/tl_not_a_real_token');

    expect(response?.status()).toBe(200);
    await expect(page.getByText('Link Expired or Invalid')).toBeVisible();
  });
});
