import type { Locator, Page } from '@playwright/test';
import { test, expect } from '../../fixtures';
import { ProductCreatePage } from '../../pages/product-create.page';
import { createDraftEvent } from '../../api/factory';
import { uniqueName } from '../../utils/unique';

const gotoTaxesAndFees = async (page: Page): Promise<void> => {
  await page.goto('/account/taxes-and-fees');
  await page.waitForLoadState('networkidle');
};

const taxBlock = (page: Page, name: string): Locator =>
  page.locator('[class*="taxBlock"]').filter({ hasText: name });

const chooseOption = async (page: Page, targetDescription: string, optionDescription: string): Promise<void> => {
  await page.getByRole('dialog').getByText(targetDescription, { exact: true }).click();
  await page.getByRole('option').filter({ hasText: optionDescription }).click();
};

test.describe('taxes and fees', () => {
  test('an organizer creates a percentage tax and a fixed fee', async ({ freshAccount }) => {
    const page = await freshAccount.newAuthedPage();
    const taxName = uniqueName('VAT');
    const feeName = uniqueName('Booking Fee');

    await gotoTaxesAndFees(page);

    await page.getByRole('button', { name: 'Add Tax or Fee' }).click();
    await page.getByRole('dialog').getByLabel(/^Name/).fill(taxName);
    await page.getByRole('dialog').getByLabel(/^Percentage Amount/).fill('10');
    await page.getByRole('button', { name: 'Create Tax' }).click();

    await expect(taxBlock(page, taxName)).toBeVisible();
    await expect(taxBlock(page, taxName).getByText(/^10(\.0+)?%$/)).toBeVisible();

    await page.getByRole('button', { name: 'Add Tax or Fee' }).click();
    await chooseOption(page, 'A standard tax, like VAT or GST', 'A fee, like a booking fee or a service fee');
    await chooseOption(
      page,
      'A percentage of the product price. E.g., 3.5% of the product price',
      'A fixed amount per product',
    );
    await page.getByRole('dialog').getByLabel(/^Name/).fill(feeName);
    await page.getByRole('dialog').getByLabel(/^Amount/).fill('2.50');
    await page.getByRole('button', { name: 'Create Fee' }).click();

    await expect(taxBlock(page, feeName)).toBeVisible();
    await expect(taxBlock(page, feeName).getByText('$2.50')).toBeVisible();
  });

  test('an organizer edits a tax rate', async ({ freshAccount }) => {
    const { id: accountId } = await freshAccount.api.getAccount();
    const taxName = uniqueName('Edit Tax');
    await freshAccount.api.createTaxOrFee(accountId, {
      name: taxName,
      calculation_type: 'PERCENTAGE',
      type: 'TAX',
      rate: 10,
      is_active: true,
      is_default: false,
    });

    const page = await freshAccount.newAuthedPage();
    await gotoTaxesAndFees(page);

    await taxBlock(page, taxName).getByRole('button').click();
    await page.getByRole('menuitem', { name: 'Edit' }).click();

    const rateInput = page.getByRole('dialog').getByLabel(/^Percentage Amount/);
    await expect(rateInput).toHaveValue(/^10/);
    await rateInput.fill('15');
    await page.getByRole('button', { name: 'Update Tax' }).click();

    await expect(taxBlock(page, taxName).getByText(/^15(\.0+)?%$/)).toBeVisible();
  });

  test('an organizer deletes a fee', async ({ freshAccount }) => {
    const { id: accountId } = await freshAccount.api.getAccount();
    const feeName = uniqueName('Doomed Fee');
    await freshAccount.api.createTaxOrFee(accountId, {
      name: feeName,
      calculation_type: 'FIXED',
      type: 'FEE',
      rate: 1.5,
      is_active: true,
      is_default: false,
    });

    const page = await freshAccount.newAuthedPage();
    await gotoTaxesAndFees(page);

    await expect(taxBlock(page, feeName)).toBeVisible();
    await taxBlock(page, feeName).getByRole('button').click();
    await page.getByRole('menuitem', { name: 'Delete' }).click();
    await page.getByRole('button', { name: 'Confirm' }).click();

    await expect(taxBlock(page, feeName)).toHaveCount(0);
  });

  test('a tax selected during product creation shows attached when reopening the edit modal', async ({ freshAccount }) => {
    const { id: accountId } = await freshAccount.api.getAccount();
    const taxName = uniqueName('Attach Tax');
    await freshAccount.api.createTaxOrFee(accountId, {
      name: taxName,
      calculation_type: 'PERCENTAGE',
      type: 'TAX',
      rate: 12.5,
      is_active: true,
      is_default: false,
    });
    const event = await createDraftEvent(freshAccount.api, freshAccount.organizerId);
    const title = uniqueName('Taxed Ticket');

    const page = await freshAccount.newAuthedPage();
    const products = new ProductCreatePage(page);
    await products.goto(event.eventId);
    await products.openCreateModal();
    await page.getByLabel(/^Name/).fill(title);
    await page.getByLabel(/^Price/).fill('30');
    await products.openLedgerRow('taxes');

    await page.getByRole('combobox', { name: 'Taxes and Fees' }).click();
    await page.getByRole('option', { name: new RegExp(`^${taxName}`) }).click();
    await page.getByRole('heading', { name: 'Create Ticket or Product' }).click();
    await products.submitCreate();

    await expect(page.getByRole('heading', { name: title })).toBeVisible();

    await products.openEditModal();
    await products.openLedgerRow('taxes');
    await expect(page.getByRole('dialog').getByText(new RegExp(`^${taxName}`))).toBeVisible();
  });
});
