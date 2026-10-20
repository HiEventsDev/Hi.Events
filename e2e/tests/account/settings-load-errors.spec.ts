import type { Page } from '@playwright/test';
import { test, expect } from '../../fixtures';

async function failGetRequests(page: Page, url: RegExp): Promise<() => void> {
  let failing = true;
  await page.route(url, (route) => (failing && route.request().method() === 'GET'
    ? route.fulfill({ status: 500, json: { message: 'Server Error' } })
    : route.continue()));
  return () => {
    failing = false;
  };
}

test.describe('settings load errors', () => {
  test('taxes and fees offers a retry instead of an empty state when loading fails', async ({ freshAccount }) => {
    const page = await freshAccount.newAuthedPage();
    const recover = await failGetRequests(page, /\/api\/accounts\/\d+\/taxes-and-fees(\?.*)?$/);

    await page.goto('/settings/taxes-and-fees');

    await expect(page.getByText("Couldn't load your taxes and fees")).toBeVisible();
    await expect(page.getByText('No taxes or fees yet')).toHaveCount(0);
    await expect(page.getByRole('button', { name: 'Add tax or fee' })).toHaveCount(0);

    recover();
    await page.getByRole('button', { name: 'Try again' }).click();
    await expect(page.getByText('No taxes or fees yet')).toBeVisible();
  });

  test('organizers offers a retry instead of an empty list when loading fails', async ({ freshAccount }) => {
    const page = await freshAccount.newAuthedPage();
    const recover = await failGetRequests(page, /\/api\/organizers(\?.*)?$/);

    await page.goto('/settings/organizers');

    await expect(page.getByText("Couldn't load your organizers")).toBeVisible();
    await expect(page.getByText('No organizers yet.')).toHaveCount(0);

    recover();
    await page.getByRole('button', { name: 'Try again' }).click();
    await expect(page.getByTestId('settings-organizer-row')).toHaveCount(1);
  });

  test('team offers a retry instead of endless skeletons when loading fails', async ({ freshAccount }) => {
    const page = await freshAccount.newAuthedPage();
    const recover = await failGetRequests(page, /\/api\/users(\?.*)?$/);

    await page.goto('/settings/team');

    await expect(page.getByText("Couldn't load your team")).toBeVisible();

    recover();
    await page.getByRole('button', { name: 'Try again' }).click();
    await expect(page.getByTestId('team-member-row').filter({ hasText: freshAccount.email })).toBeVisible();
  });
});
