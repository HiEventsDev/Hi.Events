import {expect, test} from '../../fixtures';
import type {Page} from '@playwright/test';
import {createLiveEventWithFreeTicket} from '../../api/factory';

const PHONE = {width: 390, height: 844};
const DESKTOP = {width: 1440, height: 900};

const collectHydrationErrors = (page: Page): string[] => {
  const errors: string[] = [];
  page.on('pageerror', error => errors.push(error.message));
  page.on('console', message => {
    if (message.type() === 'error') {
      errors.push(message.text());
    }
  });
  return errors;
};

const waitForHydration = async (page: Page) => {
  await expect.poll(() => page.evaluate(() => Array.from(document.querySelectorAll('#app div'))
    .some(element => Object.keys(element).some(key => key.startsWith('__reactFiber$'))))).toBe(true);
  await page.evaluate(() => new Promise(resolve => requestAnimationFrame(() => setTimeout(resolve, 250))));
};

test.describe('app shell', () => {
  test('settings and event pages hydrate without errors on a phone', async ({authedPage: page, api, account}) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    const errors = collectHydrationErrors(page);
    await page.setViewportSize(PHONE);

    for (const path of ['/settings/profile', `/manage/event/${event.eventId}/dashboard`]) {
      errors.length = 0;
      await page.goto(path);
      await waitForHydration(page);

      expect(errors.filter(error => /hydrat|#418|#423|#425/i.test(error)), path).toEqual([]);
    }
  });

  test('on a phone the menu button is labelled and the closed navigation is out of reach', async ({authedPage: page}) => {
    await page.setViewportSize(PHONE);
    await page.goto('/settings/profile');
    await waitForHydration(page);

    await expect(page.getByRole('main')).toHaveCount(1);
    const navigation = page.getByRole('navigation', {name: 'Main navigation'});
    const menuButton = page.getByRole('button', {name: 'Open sidebar'});

    await expect(navigation).toBeHidden();
    await expect(menuButton).toHaveAttribute('aria-expanded', 'false');

    await menuButton.click();
    await expect(navigation).toBeVisible();
    await expect(page.getByRole('button', {name: 'Close sidebar'}).first()).toHaveAttribute('aria-expanded', 'true');

    await navigation.getByRole('link', {name: 'Security'}).click();
    await expect(page).toHaveURL(/\/settings\/security$/);
    await expect(navigation).toBeHidden();
  });

  test('the skip link moves focus to the page content', async ({authedPage: page}) => {
    await page.setViewportSize(DESKTOP);
    await page.goto('/settings/profile');
    await waitForHydration(page);

    await expect(page.getByRole('navigation', {name: 'Main navigation'})).toBeVisible();

    const skipLink = page.getByRole('link', {name: 'Skip to main content'});
    await skipLink.focus();
    await expect(skipLink).toBeInViewport();
    await page.keyboard.press('Enter');

    await expect(page.getByRole('main')).toBeFocused();
  });
});
