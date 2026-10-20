import type { Locator, Page } from '@playwright/test';
import { test, expect } from '../../fixtures';
import { createDraftEvent, createDraftEventWithTicket, createRecurringLiveEvent } from '../../api/factory';
import { EventSettingsPage } from '../../pages/event-settings.page';
import { OccurrencePage } from '../../pages/occurrence.page';
import { ProductCreatePage } from '../../pages/product-create.page';
import { ProductEditPage } from '../../pages/product-edit.page';
import { PromoCodePage } from '../../pages/promo-code.page';
import { expectDateTime, pickDate } from '../../utils/datePicker';
import { uniqueCode } from '../../utils/unique';

const datePicker = (page: Page): Locator =>
  page.getByRole('dialog').filter({ has: page.locator('select[data-select="year"]') });

const fieldWrapper = (dateField: Locator): Locator =>
  dateField.locator('xpath=ancestor::div[contains(@class, "mantine-InputWrapper-root")][2]');

async function expectOpensFromItsCentre(page: Page, dateField: Locator): Promise<void> {
  await dateField.scrollIntoViewIfNeeded();
  const box = await dateField.boundingBox();
  await page.mouse.click(box!.x + box!.width / 2, box!.y + box!.height / 2);
  await expect(datePicker(page)).toBeVisible({ timeout: 3_000 });
  await page.keyboard.press('Escape');
  await expect(datePicker(page)).toBeHidden();
}

async function expectDateOnOneLine(dateField: Locator): Promise<void> {
  await dateField.scrollIntoViewIfNeeded();
  const date = await dateField.boundingBox();
  const time = await fieldWrapper(dateField).locator('.mantine-TimePicker-input').boundingBox();
  expect(date!.width).toBeGreaterThanOrEqual(150);
  expect(Math.round(date!.height)).toBe(Math.round(time!.height));
  expect(await dateField.evaluate((el) => el.scrollWidth <= el.clientWidth)).toBe(true);
}

async function expectStandaloneDateOnOneLine(dateField: Locator): Promise<void> {
  await dateField.scrollIntoViewIfNeeded();
  const date = await dateField.boundingBox();
  expect(Math.round(date!.height)).toBe(36);
  expect(await dateField.evaluate((el) => el.scrollWidth <= el.clientWidth)).toBe(true);
}

test.describe('date and time fields', () => {
  test.describe('in two-column forms at 1440px', () => {
    test.use({ viewport: { width: 1440, height: 900 } });

    test('a price tier sale date opens from its centre and shows the whole date on one line', async ({ authedPage, api, account }) => {
      const event = await createDraftEvent(api, account.organizerId);
      const products = new ProductCreatePage(authedPage);
      await products.goto(event.eventId);
      await products.openCreateModal();
      await products.selectPriceType('Tiers');

      const saleStart = authedPage.getByLabel('Sale Start Date', { exact: true }).first();
      await expectOpensFromItsCentre(authedPage, saleStart);
      await pickDate(authedPage, saleStart, '2030-11-18');
      await expectDateOnOneLine(saleStart);
    });

    test('the product sale window shows the whole date on one line', async ({ authedPage, api, account }) => {
      const event = await createDraftEvent(api, account.organizerId);
      const products = new ProductCreatePage(authedPage);
      await products.goto(event.eventId);
      await products.openCreateModal();
      await products.openLedgerRow('sale-window');

      const saleStart = authedPage.getByLabel('Sale Start Date', { exact: true });
      await expectOpensFromItsCentre(authedPage, saleStart);
      await pickDate(authedPage, saleStart, '2030-11-18');
      await expectDateOnOneLine(saleStart);
    });

    test('a date-only pick opens the product sale window at midnight and closes it at 23:59', async ({ authedPage, api, account }) => {
      const event = await createDraftEvent(api, account.organizerId);
      const products = new ProductCreatePage(authedPage);
      await products.goto(event.eventId);
      await products.openCreateModal();
      await products.openLedgerRow('sale-window');

      const saleStart = authedPage.getByLabel('Sale Start Date', { exact: true });
      const saleEnd = authedPage.getByLabel('Sale End Date', { exact: true });
      await pickDate(authedPage, saleStart, '2030-12-01');
      await pickDate(authedPage, saleEnd, '2030-12-24');

      await expectDateTime(authedPage, saleStart, '2030-12-01T00:00');
      await expectDateTime(authedPage, saleEnd, '2030-12-24T23:59');
    });

    test('the Edit Date modal shows the whole start date on one line', async ({ authedPage, api, account }) => {
      const event = await createRecurringLiveEvent(api, account.organizerId, { count: 2 });
      const occurrences = new OccurrencePage(authedPage);
      await occurrences.goto(event.eventId);
      await occurrences.chooseRowAction(occurrences.occurrenceRows().first(), 'Edit');

      const start = authedPage.getByRole('dialog').getByLabel(/^Start Date/);
      await expect(start).toContainText(/\d{4}/);
      await expectDateOnOneLine(start);
      await expectOpensFromItsCentre(authedPage, start);
    });

    test('the schedule drawer shows its from and until dates on one line', async ({ authedPage, api, account }) => {
      const event = await createRecurringLiveEvent(api, account.organizerId, { count: 2 });
      const occurrences = new OccurrencePage(authedPage);
      await occurrences.goto(event.eventId);
      await authedPage.getByRole('button', { name: /Add Dates/ }).first().click();
      await authedPage.getByRole('menuitem', { name: /Edit Schedule|Set Up Schedule/ }).click();
      await authedPage.getByRole('dialog').getByText('Until a date', { exact: true }).click();

      const from = authedPage.getByRole('dialog').getByLabel('Schedule starts on');
      const until = authedPage.getByRole('dialog').getByLabel('Schedule ends on');
      await pickDate(authedPage, from, '2030-11-28');
      await pickDate(authedPage, until, '2031-05-28');
      await expectStandaloneDateOnOneLine(from);
      await expectStandaloneDateOnOneLine(until);
    });
  });

  test.describe('in event settings at 768px', () => {
    test.use({ viewport: { width: 768, height: 1024 } });

    test('the start date opens from its centre and shows the whole date on one line', async ({ authedPage, api, account }) => {
      const event = await createDraftEvent(api, account.organizerId);
      const settings = new EventSettingsPage(authedPage);
      await settings.goto(event.eventId);

      await expect(settings.detailsStartDate).toContainText(/\d{4}/);
      await expectDateOnOneLine(settings.detailsStartDate);
      await expectOpensFromItsCentre(authedPage, settings.detailsStartDate);
    });
  });

  test('a keyboard pick keeps focus on the field, which announces its value, and each time part names its field', async ({ authedPage, api, account }) => {
    const event = await createDraftEvent(api, account.organizerId);
    const settings = new EventSettingsPage(authedPage);
    await settings.goto(event.eventId);

    const start = settings.detailsStartDate;
    await start.focus();
    await authedPage.keyboard.press('Enter');
    const picker = datePicker(authedPage);
    await expect(picker).toBeVisible();
    await picker.locator('select[data-select="year"]').selectOption('2030');
    await picker.locator('select[data-select="month"]').selectOption('5');
    await picker.getByRole('button', { name: '14 June 2030', exact: true }).focus();
    await authedPage.keyboard.press('Enter');
    await expect(picker).toBeHidden();

    await expect(start).toBeFocused();
    await expect(start).toHaveAccessibleName('Start Date Jun 14, 2030');

    const end = settings.detailsEndDate;
    await pickDate(authedPage, end, '2030-06-15');
    await expect(fieldWrapper(end).getByRole('button', { name: 'Clear', exact: true })).toBeVisible();

    await expect(fieldWrapper(start).getByLabel('Hours')).toHaveAccessibleDescription('Start Date');
    await expect(fieldWrapper(end).getByLabel('Hours')).toHaveAccessibleDescription('End Date');
    await expect(fieldWrapper(end).getByLabel('Minutes')).toHaveAccessibleDescription('End Date');
    await expect(fieldWrapper(end).getByLabel('AM/PM')).toHaveAccessibleDescription('End Date');
  });

  test('the edit promo code modal shows the saved expiry time inside the collapsed advanced options', async ({ authedPage, api, account }) => {
    const event = await createDraftEvent(api, account.organizerId);
    const code = uniqueCode('EXPIRY');
    await api.createPromoCode(event.eventId, {
      code,
      discount_type: 'PERCENTAGE',
      discount: 10,
      expiry_date: '2030-12-31 19:30:00',
    });

    const promoCodes = new PromoCodePage(authedPage);
    await promoCodes.goto(event.eventId);
    await promoCodes.openEditModal(code);

    await expectDateTime(authedPage, authedPage.getByLabel('Expiry Date'), '2030-12-31T19:30');
  });

  test('the edit product drawer shows the saved sale window times when the row is expanded', async ({ authedPage, api, account }) => {
    const seeded = await createDraftEventWithTicket(api, account.organizerId, { productTitle: 'Timed Ticket' });
    await api.createProduct(seeded.eventId, {
      title: 'Windowed Ticket',
      product_type: 'TICKET',
      type: 'FREE',
      product_category_id: seeded.categoryId,
      prices: [{ price: 0 }],
      sale_start_date: '2030-05-01 09:15:00',
      sale_end_date: '2030-05-20 21:45:00',
    });

    const products = new ProductEditPage(authedPage);
    await products.goto(seeded.eventId);
    await products.openProductByTitle('Windowed Ticket');
    await authedPage.getByTestId('product-ledger-sale-window').click();

    await expectDateTime(authedPage, authedPage.getByLabel('Sale Start Date', { exact: true }), '2030-05-01T09:15');
    await expectDateTime(authedPage, authedPage.getByLabel('Sale End Date', { exact: true }), '2030-05-20T21:45');
  });
});
