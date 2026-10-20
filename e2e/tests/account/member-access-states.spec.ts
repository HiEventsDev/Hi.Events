import type { Page } from '@playwright/test';
import { test, expect } from '../../fixtures';
import { createLiveEventWithFreeTicket, createRecurringLiveEvent } from '../../api/factory';
import { ProductCreatePage } from '../../pages/product-create.page';
import { invitedMember } from '../../utils/team';

const NO_ACCESS = "You don't have access to this page";

async function statGridGap(page: Page): Promise<number> {
  const firstCell = page.getByTestId('event-stat-attendees');
  await expect(firstCell).toBeVisible();
  return firstCell.evaluate((cell) => {
    const grid = cell.parentElement as HTMLElement;
    const cells = Array.from(grid.children);
    const lastCell = cells[cells.length - 1].getBoundingClientRect();
    return Math.abs(grid.getBoundingClientRect().right - lastCell.right);
  });
}

test.describe('member access states', () => {
  test('a viewer gets no dead create or select controls', async ({ freshAccount, mailpit, publicApi, browser }) => {
    const event = await createLiveEventWithFreeTicket(freshAccount.api, freshAccount.organizerId);
    const recurring = await createRecurringLiveEvent(freshAccount.api, freshAccount.organizerId, { count: 2 });
    const { page } = await invitedMember(freshAccount, mailpit, publicApi, browser, { role: 'VIEWER' });

    await page.goto(`/manage/event/${event.eventId}/messages`);
    await expect(page.getByRole('heading', { name: 'No messages to show' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Send your first message' })).toHaveCount(0);

    for (const width of [1440, 800, 390]) {
      await page.setViewportSize({ width, height: 900 });
      await page.goto(`/manage/event/${event.eventId}/dashboard`);
      await expect(page.getByTestId('event-stat-orders')).toBeVisible();
      await expect(page.getByTestId('event-stat-gross_sales')).toHaveCount(0);
      expect(await statGridGap(page), `stat grid at ${width}px`).toBeLessThan(2);
    }
    await page.setViewportSize({ width: 1440, height: 900 });

    await page.goto('/settings/organizers');
    const organizerRow = page.getByTestId('settings-organizer-row').first();
    await expect(organizerRow).toContainText('Open');
    await expect(organizerRow).not.toContainText('Open settings');
    await expect(organizerRow).toHaveAttribute('href', `/manage/organizer/${freshAccount.organizerId}/dashboard`);

    await page.goto(`/manage/event/${recurring.eventId}/occurrences`);
    await expect(page.getByText(/Showing \d+–\d+ of \d+/)).toBeVisible();
    await expect(page.getByRole('checkbox', { name: 'Select date' })).toHaveCount(0);
    await expect(page.getByRole('checkbox', { name: 'Select all' })).toHaveCount(0);

    const ownerPage = await freshAccount.newAuthedPage();
    await ownerPage.goto(`/manage/event/${recurring.eventId}/occurrences`);
    await expect(ownerPage.getByRole('checkbox', { name: 'Select date' })).toHaveCount(2);
    await ownerPage.goto('/settings/organizers');
    await expect(ownerPage.getByTestId('settings-organizer-row').first())
      .toHaveAttribute('href', `/manage/organizer/${freshAccount.organizerId}/settings`);
  });

  test('a member without financials gets an access message on reports and webhooks', async ({ freshAccount, mailpit, publicApi, browser }) => {
    const event = await createLiveEventWithFreeTicket(freshAccount.api, freshAccount.organizerId);
    const { page } = await invitedMember(freshAccount, mailpit, publicApi, browser, {
      role: 'ORGANIZER',
      permissions: { VIEW_FINANCIALS: false },
    });
    const base = `/manage/event/${event.eventId}`;
    const organizerBase = `/manage/organizer/${freshAccount.organizerId}`;

    for (const path of [`${base}/reports`, `${base}/report/product_sales`, `${organizerBase}/report/revenue_summary`]) {
      await page.goto(path);
      await expect(page.getByRole('heading', { name: NO_ACCESS }), path).toBeVisible();
      await expect(page.getByText('Nothing to show yet')).toHaveCount(0);
      await expect(page.getByText('No data found')).toHaveCount(0);
    }

    for (const path of [`${base}/webhooks`, `${organizerBase}/webhooks`]) {
      await page.goto(path);
      await expect(page.getByRole('heading', { name: NO_ACCESS }), path).toBeVisible();
      await expect(page.getByRole('button', { name: 'Add Webhook' })).toHaveCount(0);
    }

    await page.goto(`${organizerBase}/report/check_in_summary`);
    await expect(page.getByText('Check-in Summary').first()).toBeVisible();
    await expect(page.getByRole('heading', { name: NO_ACCESS })).toHaveCount(0);
    await expect(page).not.toHaveURL(/\/auth\/login/);

    const ownerPage = await freshAccount.newAuthedPage();
    await ownerPage.goto(`${base}/reports`);
    await expect(ownerPage.getByText('Product Sales').first()).toBeVisible();
    await expect(ownerPage.getByRole('heading', { name: NO_ACCESS })).toHaveCount(0);
  });

  test('an event-only member sees why an event is closed to them and no account-level tax control', async ({ freshAccount, mailpit, publicApi, browser }) => {
    const granted = await createLiveEventWithFreeTicket(freshAccount.api, freshAccount.organizerId);
    const hidden = await createLiveEventWithFreeTicket(freshAccount.api, freshAccount.organizerId);
    const { page } = await invitedMember(freshAccount, mailpit, publicApi, browser, {
      role: 'ORGANIZER',
      access_scope: 'RESTRICTED',
      event_ids: [granted.eventId],
    });

    await page.goto(`/manage/event/${hidden.eventId}/dashboard`);
    await expect(page.getByRole('heading', { name: "You don't have access to this event" })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Go to your events' })).toHaveAttribute('href', '/manage/events');
    await expect(page).toHaveURL(new RegExp(`/manage/event/${hidden.eventId}/dashboard`));

    const products = new ProductCreatePage(page);
    await products.goto(granted.eventId);
    await products.openCreateModal();
    await products.openLedgerRow('taxes');
    await expect(page.getByRole('combobox', { name: 'Taxes and Fees' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Create Tax or Fee' })).toHaveCount(0);

    const ownerPage = await freshAccount.newAuthedPage();
    const ownerProducts = new ProductCreatePage(ownerPage);
    await ownerProducts.goto(granted.eventId);
    await ownerProducts.openCreateModal();
    await ownerProducts.openLedgerRow('taxes');
    await expect(ownerPage.getByRole('button', { name: 'Create Tax or Fee' })).toBeVisible();
  });
});
