import {expect, test} from '../../fixtures';

test.describe('server-rendered document language', () => {
  for (const [name, headers, lang] of [
    ['the Accept-Language header', {'Accept-Language': 'de-DE,de;q=0.9'}, 'de'],
    ['the locale cookie', {Cookie: 'locale=pt-br'}, 'pt-br'],
    ['a locale with an Intl override', {Cookie: 'locale=se'}, 'sv'],
    ['an unsupported language', {'Accept-Language': 'xx-YY'}, 'en'],
  ] as const) {
    test(`the html element declares the language picked from ${name}`, async ({request}) => {
      const html = await (await request.get('/auth/login', {headers})).text();

      expect(html).toContain(`<html lang="${lang}">`);
    });
  }

  test('the language survives hydration', async ({page}) => {
    await page.context().addCookies([{name: 'locale', value: 'fr', url: test.info().project.use.baseURL!}]);
    await page.goto('/auth/login');

    await expect(page.locator('html')).toHaveAttribute('lang', 'fr');
  });
});
