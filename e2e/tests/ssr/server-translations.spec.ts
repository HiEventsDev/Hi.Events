import {expect, test} from '../../fixtures';

test.describe('@smoke server-rendered translations', () => {
  test('each request is translated in its own locale on the server', async ({request}) => {
    for (const [language, feature] of [['de', 'Wiederkehrende Veranstaltungen'], ['en', 'Recurring events']]) {
      const html = await (await request.get('/auth/login', {headers: {'Accept-Language': language}})).text();
      expect(html).toContain(feature);
    }
  });

  for (const path of ['/auth/login', '/auth/register']) {
    test(`${path} hydrates without errors`, async ({page}) => {
      const errors: string[] = [];
      page.on('pageerror', error => errors.push(error.message));
      page.on('console', message => {
        if (message.type() === 'error') {
          errors.push(message.text());
        }
      });

      await page.goto(path);
      const feature = page.getByText('Recurring events').first();
      await expect(feature).toBeVisible();
      await expect.poll(() => feature.evaluate(node => Object.keys(node).some(key => key.startsWith('__reactFiber$')))).toBe(true);
      await page.evaluate(() => new Promise(resolve => requestAnimationFrame(() => setTimeout(resolve))));

      expect(errors.filter(error => /hydrat|#418|#423|#425/i.test(error))).toEqual([]);
    });
  }
});
