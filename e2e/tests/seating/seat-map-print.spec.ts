import type {Page} from '@playwright/test';
import {test, expect} from '../../fixtures';
import {createDraftEvent, createSeatedEvent, createSeatedOrder} from '../../api/factory';

const SOLD_SEAT = 'e2.0.9';
const BLOCKED_SEAT = 'e2.0.5';

const firstPage = (page: Page) => page.getByTestId('seating-print-page').first();

test.describe('printable seat map', () => {
  test.beforeEach(async ({adminApi, account}) => {
    await adminApi.setAccountFeatureFlag(await adminApi.findAccountIdByEmail(account.email), 'seating', true);
  });

  test('the sales print view shows sold, held-back and free seats', async ({authedPage: page, api, account, publicApi}) => {
    const event = await createSeatedEvent(api, account.organizerId);
    await createSeatedOrder(publicApi, event, [SOLD_SEAT]);
    await api.blockSeats(event.eventId, event.occurrenceId, [BLOCKED_SEAT], 'Sound desk');

    await page.goto(`/manage/event/${event.eventId}/seating/print?mode=sales&occurrence=${event.occurrenceId}`);

    await expect(page.getByTestId('seating-print-page')).toHaveCount(2);
    await expect(firstPage(page).locator(`[data-uid="${SOLD_SEAT}"]`)).toHaveAttribute('data-state', 'sold');
    await expect(firstPage(page).locator(`[data-uid="${BLOCKED_SEAT}"]`)).toHaveAttribute('data-state', 'blocked');
    await expect(firstPage(page).getByTestId('seating-print-counts')).toContainText('1 sold');
    await expect(firstPage(page).getByTestId('seating-print-counts')).toContainText('1 held back');
    await expect(page.getByTestId('seating-print-held-back')).toContainText('Sound desk');
    await expect(page.getByTestId('seating-print-page-count')).toContainText('3');
  });

  test('the house map leaves sales data off the page', async ({authedPage: page, api, account, publicApi}) => {
    const event = await createSeatedEvent(api, account.organizerId);
    await createSeatedOrder(publicApi, event, [SOLD_SEAT]);
    await api.blockSeats(event.eventId, event.occurrenceId, [BLOCKED_SEAT], 'Sound desk');

    await page.goto(`/manage/event/${event.eventId}/seating/print?mode=house`);

    await expect(page.getByTestId('seating-print-page')).toHaveCount(2);
    await expect(firstPage(page).locator(`[data-uid="${SOLD_SEAT}"]`)).toHaveAttribute('data-state', 'free');
    await expect(firstPage(page).getByTestId('seating-print-counts')).not.toContainText('sold');
    await expect(page.getByTestId('seating-print-held-back')).toHaveCount(0);
    await expect(page.getByTestId('seating-print-page-count')).toContainText('2');
  });

  test('the sold seat list is opt-in and names the attendee', async ({authedPage: page, api, account, publicApi}) => {
    const event = await createSeatedEvent(api, account.organizerId);
    await createSeatedOrder(publicApi, event, [SOLD_SEAT], {buyerFirstName: 'Orla', buyerLastName: 'Nolan'});

    await page.goto(`/manage/event/${event.eventId}/seating/print?mode=sales&occurrence=${event.occurrenceId}`);
    await expect(firstPage(page)).toBeVisible();
    await expect(page.getByTestId('seating-print-manifest')).toHaveCount(0);

    await page.getByTestId('seating-print-manifest-toggle').click();

    await expect(page.getByTestId('seating-print-manifest')).toContainText('Orla Nolan');
    await expect(page.getByText('Attendee names appear on the printout')).toBeVisible();
  });

  test('every option is kept in the url so the sheet can be shared', async ({authedPage: page, api, account}) => {
    const event = await createSeatedEvent(api, account.organizerId);

    await page.goto(`/manage/event/${event.eventId}/seating/print`);
    await expect(firstPage(page)).toBeVisible();

    await page.getByTestId('seating-print-mode').getByText('House map').click();
    await page.getByText('Portrait').click();
    await page.getByText('Letter').click();

    await expect(page).toHaveURL(/mode=house/);
    await expect(page).toHaveURL(/orientation=portrait/);
    await expect(page).toHaveURL(/paper=letter/);

    await page.reload();

    await expect(page.getByTestId('seating-print-manifest-toggle')).toHaveCount(0);
    await expect(firstPage(page).getByTestId('seating-print-counts')).not.toContainText('sold');
  });

  test('an event without a seat map explains itself instead of printing nothing', async ({authedPage: page, api, account}) => {
    const event = await createDraftEvent(api, account.organizerId);

    await page.goto(`/manage/event/${event.eventId}/seating/print`);

    await expect(page.getByTestId('seating-print-empty')).toContainText('Nothing to print yet');
    await expect(page.getByRole('link', {name: 'Go to seating settings'}))
      .toHaveAttribute('href', `/manage/event/${event.eventId}/seating`);
  });

  test('the print view links back to seat sales', async ({authedPage: page, api, account}) => {
    const event = await createSeatedEvent(api, account.organizerId);

    await page.goto(`/manage/event/${event.eventId}/seating/print`);

    await expect(page.getByTestId('seating-print-back'))
      .toHaveAttribute('href', `/manage/event/${event.eventId}/seating/sales`);
  });

  test('the seat sales page links to the print view for the chosen date', async ({authedPage: page, api, account}) => {
    const event = await createSeatedEvent(api, account.organizerId);

    await page.goto(`/manage/event/${event.eventId}/seating/sales`);

    await expect(page.getByTestId('seating-sales-print-button'))
      .toHaveAttribute('href', `/manage/event/${event.eventId}/seating/print?mode=sales&occurrence=${event.occurrenceId}`);
  });
});
