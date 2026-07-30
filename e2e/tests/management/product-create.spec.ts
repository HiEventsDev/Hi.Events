import { test, expect } from '../../fixtures';
import { ProductCreatePage } from '../../pages/product-create.page';
import { createDraftEvent } from '../../api/factory';
import { uniqueName } from '../../utils/unique';

test.describe('product creation', () => {
  test('an organizer creates a paid ticket and sees it listed with its price', { tag: '@smoke' }, async ({ authedPage, api, account }) => {
    const event = await createDraftEvent(api, account.organizerId);
    const title = uniqueName('Paid Ticket');

    const products = new ProductCreatePage(authedPage);
    await products.goto(event.eventId);
    await products.openCreateModal();
    await authedPage.getByLabel(/^Name/).fill(title);
    await authedPage.getByLabel(/^Price/).fill('30');
    await products.submitCreate();

    await expect(authedPage.getByRole('heading', { name: title })).toBeVisible();
    await expect(authedPage.getByText('$30.00', { exact: true })).toBeVisible();
  });

  test('an organizer creates a donation product and the list flags it as a donation', async ({ authedPage, api, account }) => {
    const event = await createDraftEvent(api, account.organizerId);
    const title = uniqueName('Donation');

    const products = new ProductCreatePage(authedPage);
    await products.goto(event.eventId);
    await products.openCreateModal();
    await products.selectPriceType('Donation');
    await authedPage.getByLabel(/^Name/).fill(title);
    await authedPage.getByLabel(/^Minimum Price/).fill('5');
    await products.submitCreate();

    await expect(authedPage.getByRole('heading', { name: title })).toBeVisible();
    await expect(authedPage.getByText('Donation', { exact: true })).toBeVisible();
  });

  test('an organizer creates a tiered product and the list shows the tier price range', async ({ authedPage, api, account }) => {
    const event = await createDraftEvent(api, account.organizerId);
    const title = uniqueName('Tiered');

    const products = new ProductCreatePage(authedPage);
    await products.goto(event.eventId);
    await products.openCreateModal();
    await products.selectPriceType('Tiers');
    await authedPage.getByLabel(/^Name/).fill(title);
    await products.fillTier(0, '10', 'Early Bird');
    await products.addTier();
    await products.fillTier(1, '20', 'Standard');
    await products.submitCreate();

    await expect(authedPage.getByRole('heading', { name: title })).toBeVisible();
    await expect(authedPage.getByText('$10.00 – $20.00', { exact: true })).toBeVisible();
  });

  test('ledger settings persist and are shown when reopening the edit modal', async ({ authedPage, api, account }) => {
    const event = await createDraftEvent(api, account.organizerId);
    const title = uniqueName('Hidden Ticket');
    const saleStart = new Date(Date.now() + 7 * 24 * 60 * 60 * 1000).toISOString().slice(0, 16);

    const products = new ProductCreatePage(authedPage);
    await products.goto(event.eventId);
    await products.openCreateModal();
    await authedPage.getByLabel(/^Name/).fill(title);
    await authedPage.getByLabel(/^Price/).fill('15');
    await products.openLedgerRow('order-limits');
    await authedPage.getByLabel('Minimum Per Order').fill('1');
    await authedPage.getByLabel('Maximum Per Order').fill('2');
    await products.openLedgerRow('sale-window');
    await authedPage.getByRole('textbox', { name: 'Sale Start Date' }).fill(saleStart);
    await products.openLedgerRow('access');
    await products.hiddenSwitch().check();
    await products.submitCreate();

    await expect(authedPage.getByRole('heading', { name: title })).toBeVisible();
    await expect(authedPage.getByText('Hidden', { exact: true }).first()).toBeVisible();

    await products.openEditModal();
    await expect(authedPage.getByLabel(/^Name/)).toHaveValue(title);
    await products.openLedgerRow('order-limits');
    await expect(authedPage.getByLabel('Minimum Per Order')).toHaveValue('1');
    await expect(authedPage.getByLabel('Maximum Per Order')).toHaveValue('2');
    await products.openLedgerRow('sale-window');
    await expect(authedPage.getByRole('textbox', { name: 'Sale Start Date' })).toHaveValue(saleStart);
    await products.openLedgerRow('access');
    await expect(products.hiddenSwitch()).toBeChecked();
  });
});
