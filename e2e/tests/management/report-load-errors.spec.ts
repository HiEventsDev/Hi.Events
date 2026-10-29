import type { Page } from '@playwright/test';
import { test, expect } from '../../fixtures';
import { createLiveEventWithFreeTicket } from '../../api/factory';

async function failGetRequests(page: Page, url: RegExp): Promise<() => void> {
  let failing = true;
  await page.route(url, (route) => (failing && route.request().method() === 'GET'
    ? route.fulfill({ status: 500, json: { message: 'Server Error' } })
    : route.continue()));
  return () => {
    failing = false;
  };
}

test.describe('report load errors', () => {
  test('an event report offers a retry instead of an empty state when loading fails', async ({ freshAccount }) => {
    const event = await createLiveEventWithFreeTicket(freshAccount.api, freshAccount.organizerId);
    const page = await freshAccount.newAuthedPage();
    const recover = await failGetRequests(page, /\/api\/events\/\d+\/reports\/product_sales(\?.*)?$/);

    await page.goto(`/manage/event/${event.eventId}/report/product_sales`);

    await expect(page.getByText("Couldn't load this report")).toBeVisible({ timeout: 20_000 });
    await expect(page.getByText('Nothing to show yet')).toHaveCount(0);

    recover();
    await page.getByRole('button', { name: 'Try again' }).click();
    await expect(page.getByRole('heading', { name: 'Product Sales Report' }).or(page.getByText('Nothing to show yet'))).toBeVisible();
    await expect(page.getByText("Couldn't load this report")).toHaveCount(0);
  });

  test('an organizer report offers a retry instead of a no-data message when loading fails', async ({ freshAccount }) => {
    const page = await freshAccount.newAuthedPage();
    const recover = await failGetRequests(page, /\/api\/organizers\/\d+\/reports\/events_performance(\?.*)?$/);

    await page.goto(`/manage/organizer/${freshAccount.organizerId}/report/events_performance`);

    await expect(page.getByText("Couldn't load this report")).toBeVisible({ timeout: 20_000 });
    await expect(page.getByText(/No data found for the selected filters/)).toHaveCount(0);

    recover();
    await page.getByRole('button', { name: 'Try again' }).click();
    await expect(page.getByText("Couldn't load this report")).toHaveCount(0);
  });
});
