import type { Locator, Page } from '@playwright/test';
import { test, expect } from '../../fixtures';
import { ProductCreatePage } from '../../pages/product-create.page';
import { createDraftEvent } from '../../api/factory';
import { uniqueName } from '../../utils/unique';

const gotoTaxesAndFees = async (page: Page): Promise<void> => {
  await page.goto('/settings/taxes-and-fees');
  await page.waitForLoadState('networkidle');
};

const taxBlock = (page: Page, name: string): Locator =>
  page.getByTestId('tax-or-fee-row').filter({ hasText: name });

test.describe('taxes and fees', () => {
  test('an organizer creates a percentage tax and a fixed fee', async ({ freshAccount }) => {
    const page = await freshAccount.newAuthedPage();
    const taxName = uniqueName('VAT');
    const feeName = uniqueName('Booking Fee');

    await gotoTaxesAndFees(page);

    await page.getByRole('button', { name: 'Add tax or fee' }).click();
    await page.getByRole('dialog').getByLabel(/^Name/).fill(taxName);
    await page.getByRole('dialog').getByLabel(/^Rate/).fill('10');
    await page.getByTestId('tax-or-fee-submit').click();

    await expect(taxBlock(page, taxName)).toBeVisible();
    await expect(taxBlock(page, taxName).getByText(/^10(\.0+)?%$/)).toBeVisible();

    await page.getByRole('button', { name: 'Add tax or fee' }).click();
    await page.getByTestId('tax-or-fee-type-fee').click();
    await page.getByRole('dialog').getByText('Fixed amount', { exact: true }).click();
    await page.getByRole('dialog').getByLabel(/^Name/).fill(feeName);
    await page.getByRole('dialog').getByLabel(/^Rate/).fill('2.50');
    await page.getByRole('button', { name: 'Create fee' }).click();

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

    const rateInput = page.getByRole('dialog').getByLabel(/^Rate/);
    await expect(rateInput).toHaveValue(/^10/);
    await rateInput.fill('15');
    await page.getByTestId('tax-or-fee-submit').click();

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
    await expect(page.getByTestId('product-form-column').getByText(new RegExp(`^${taxName}`))).toBeVisible();
  });

  test('a tax created from the product form is attached without submitting the product', async ({ freshAccount }) => {
    const event = await createDraftEvent(freshAccount.api, freshAccount.organizerId);
    const taxName = uniqueName('Inline Tax');

    const page = await freshAccount.newAuthedPage();
    const products = new ProductCreatePage(page);
    await products.goto(event.eventId);
    await products.openCreateModal();
    await products.openLedgerRow('taxes');

    await page.getByRole('button', { name: 'Create Tax or Fee' }).click();
    const taxDialog = page.getByRole('dialog', { name: 'Add tax or fee' });
    await taxDialog.getByLabel(/^Name/).fill(taxName);
    await taxDialog.getByLabel(/^Rate/).fill('5');
    await taxDialog.getByTestId('tax-or-fee-submit').click();

    await expect(taxDialog).toHaveCount(0);
    await expect(page.getByTestId('product-form-column').getByText(new RegExp(`^${taxName}`))).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Create Ticket or Product' })).toBeVisible();
  });
});
