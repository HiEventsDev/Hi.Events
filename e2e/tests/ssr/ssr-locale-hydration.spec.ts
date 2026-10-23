import {expect, test} from '../../fixtures';
import type {Page} from '@playwright/test';
import {createLiveEventWithProduct, type SeededEvent} from '../../api/factory';
import type {ApiClient} from '../../api/api-client';

const nineInTheEveningUtc = (): string => {
  const date = new Date();
  date.setUTCDate(date.getUTCDate() + 30);
  date.setUTCHours(21, 0, 0, 0);
  return date.toISOString();
};

const hydrationErrorsOn = (page: Page): string[] => {
  const errors: string[] = [];
  page.on('pageerror', error => errors.push(error.message));
  page.on('console', message => {
    if (message.type() === 'error') {
      errors.push(message.text());
    }
  });
  return errors;
};

const expectCleanHydration = async (page: Page, errors: string[]) => {
  await expect.poll(() => page.evaluate(() => Array.from(document.querySelectorAll('#app div'))
    .some(element => Object.keys(element).some(key => key.startsWith('__reactFiber$'))))).toBe(true);
  await page.evaluate(() => new Promise(resolve => requestAnimationFrame(() => setTimeout(resolve, 250))));

  expect(errors.filter(error => /hydrat|#418|#423|#425/i.test(error))).toEqual([]);
};

const createPaidEvent = (api: ApiClient, organizerId: number, startDate = nineInTheEveningUtc()): Promise<SeededEvent> =>
  createLiveEventWithProduct(api, {organizerId, price: 25, startDate});

const SWEDISH_MONTHS = ['jan', 'feb', 'mar', 'apr', 'maj', 'jun', 'jul', 'aug', 'sep', 'okt', 'nov', 'dec'];
const POLISH_MONTHS = ['sty', 'lut', 'mar', 'kwi', 'maj', 'cze', 'lip', 'sie', 'wrz', 'paź', 'lis', 'gru'];

const monthOf = (isoDate: string, months: string[]): string => months[new Date(isoDate).getUTCMonth()];

test.describe('server-rendered dates and prices follow the visitor locale', () => {
  test.describe('German browser', () => {
    test.use({locale: 'de-DE'});

    test('the event page is rendered in German on the server and hydrates cleanly', async ({page, request, api, account}) => {
      const event = await createPaidEvent(api, account.organizerId);
      const path = `/event/${event.eventId}/${event.slug}`;

      const html = await (await request.get(path, {headers: {'Accept-Language': 'de-DE,de;q=0.9'}})).text();
      expect(html).toMatch(/[\s>]21:00</);
      expect(html).not.toContain('9:00 PM');

      const errors = hydrationErrorsOn(page);
      await page.goto(path);
      await expectCleanHydration(page, errors);
      await expect(page.locator('html')).toHaveAttribute('lang', 'de');
    });

    test('the ticket widget formats prices in German on the server and hydrates cleanly', async ({page, request, api, account}) => {
      const event = await createPaidEvent(api, account.organizerId);
      const path = `/widget/${event.eventId}`;

      const html = await (await request.get(path, {headers: {'Accept-Language': 'de-DE,de;q=0.9'}})).text();
      expect(html).toMatch(/\d+,\d{2}\s\$/);

      const errors = hydrationErrorsOn(page);
      await page.goto(path);
      await expectCleanHydration(page, errors);
    });
  });

  test.describe('Swedish browser', () => {
    test.use({locale: 'sv-SE'});

    test('the event page is rendered in Swedish on the server and hydrates cleanly', async ({page, request, api, account}) => {
      const startDate = nineInTheEveningUtc();
      const event = await createPaidEvent(api, account.organizerId, startDate);
      const path = `/event/${event.eventId}/${event.slug}`;

      const html = await (await request.get(path, {headers: {'Accept-Language': 'sv-SE,sv;q=0.9'}})).text();
      expect(html).toContain('<html lang="sv"');
      expect(html).toContain(`>${monthOf(startDate, SWEDISH_MONTHS)}<`);
      expect(html).toMatch(/[\s>]21:00</);

      const errors = hydrationErrorsOn(page);
      await page.goto(path);
      await expectCleanHydration(page, errors);
      await expect(page.locator('html')).toHaveAttribute('lang', 'sv');
    });
  });

  test.describe('British browser', () => {
    test.use({locale: 'en-GB'});

    test('the ticket widget hydrates cleanly', async ({page, api, account}) => {
      const event = await createPaidEvent(api, account.organizerId);

      const errors = hydrationErrorsOn(page);
      await page.goto(`/widget/${event.eventId}`);
      await expectCleanHydration(page, errors);
    });
  });

  test.describe('American browser', () => {
    test.use({locale: 'en-US'});

    test('a French language cookie renders the event page in French on the server and hydrates cleanly', async ({page, request, api, account, baseURL}) => {
      const event = await createPaidEvent(api, account.organizerId);
      const path = `/event/${event.eventId}/${event.slug}`;

      const html = await (await request.get(path, {headers: {'Accept-Language': 'en-US', Cookie: 'locale=fr'}})).text();
      expect(html).toMatch(/[\s>]21:00</);
      expect(html).not.toContain('9:00 PM');

      await page.context().addCookies([{name: 'locale', value: 'fr', url: baseURL!}]);
      const errors = hydrationErrorsOn(page);
      await page.goto(path);
      await expectCleanHydration(page, errors);
      await expect(page.locator('html')).toHaveAttribute('lang', 'fr');
    });

    test('a Swedish language cookie tags the page with the sv language code', async ({page, baseURL}) => {
      await page.context().addCookies([{name: 'locale', value: 'se', url: baseURL!}]);
      const errors = hydrationErrorsOn(page);
      await page.goto('/auth/login');
      await expectCleanHydration(page, errors);
      await expect(page.locator('html')).toHaveAttribute('lang', 'sv');
    });

    test('a Polish language cookie renders Polish dates on the server and hydrates cleanly', async ({page, request, api, account, baseURL}) => {
      const startDate = nineInTheEveningUtc();
      const event = await createPaidEvent(api, account.organizerId, startDate);
      const path = `/event/${event.eventId}/${event.slug}`;

      const html = await (await request.get(path, {headers: {'Accept-Language': 'en-US', Cookie: 'locale=pl'}})).text();
      expect(html).toContain(`>${monthOf(startDate, POLISH_MONTHS)}<`);

      await page.context().addCookies([{name: 'locale', value: 'pl', url: baseURL!}]);
      const errors = hydrationErrorsOn(page);
      await page.goto(path);
      await expectCleanHydration(page, errors);
      await expect(page.locator('html')).toHaveAttribute('lang', 'pl');
    });

    test('a widget embedded in French renders French prices on the server and hydrates cleanly', async ({page, request, api, account}) => {
      const event = await createPaidEvent(api, account.organizerId);
      const path = `/widget/${event.eventId}?Locale=fr`;

      const html = await (await request.get(path, {headers: {'Accept-Language': 'en-US'}})).text();
      expect(html).toMatch(/\d+,\d{2}\s\$US/);

      const errors = hydrationErrorsOn(page);
      await page.goto(path);
      await expectCleanHydration(page, errors);
    });

    test('a schedule widget embedded in German renders German days and times on the server and hydrates cleanly', async ({page, request, api, account}) => {
      const event = await createPaidEvent(api, account.organizerId);
      await api.importLineup(event.eventId, {
        people: [{name: 'Velvet Harbour', billing_tier: 'HEADLINER'}, {name: 'Ada Byron'}],
        slots: [
          {starts_at: '2030-06-14 21:30:00', ends_at: '2030-06-14 23:00:00', stage: 'Main Stage', people: ['Velvet Harbour']},
          {starts_at: '2030-06-15 18:00:00', ends_at: '2030-06-15 19:00:00', stage: 'Main Stage', people: ['Ada Byron']},
        ],
      });
      const path = `/widget/${event.eventId}/schedule?Locale=de`;

      const html = await (await request.get(path, {headers: {'Accept-Language': 'en-US'}})).text();
      expect(html).toContain('Freitag');
      expect(html).toMatch(/>21:30</);
      expect(html).not.toContain('9:30 PM');

      const errors = hydrationErrorsOn(page);
      await page.goto(path);
      await expectCleanHydration(page, errors);
    });
  });
});
