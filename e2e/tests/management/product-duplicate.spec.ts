import { test, expect } from '../../fixtures';
import type { ProductRecord } from '../../api/types';
import { ProductEditPage } from '../../pages/product-edit.page';
import { uniqueName } from '../../utils/unique';

interface SaleWindow {
  sale_start_date?: string | null;
  sale_end_date?: string | null;
}

type ProductWithSaleWindows = Omit<ProductRecord, 'prices'> & SaleWindow & { prices?: (SaleWindow & { label?: string })[] };

const saleWindows = (product: ProductWithSaleWindows) => ({
  product: [product.sale_start_date, product.sale_end_date],
  tiers: (product.prices ?? []).map((price) => [price.label, price.sale_start_date, price.sale_end_date]),
});

test.describe('product duplication', () => {
  test('duplicating a tiered product on a New York event keeps every sale window', async ({ authedPage, api, account }) => {
    const event = await api.createEvent({
      title: uniqueName('E2E New York Event'),
      type: 'SINGLE',
      organizer_id: account.organizerId,
      start_date: '2030-07-01T23:00:00Z',
      category: 'MUSIC',
      currency: 'USD',
      timezone: 'America/New_York',
    });
    const categories = await api.listProductCategories(event.id);
    const earlyBird = { price: 10, label: 'Early bird', sale_start_date: '2030-05-01 09:00:00', sale_end_date: '2030-05-15 17:30:00' };
    const standard = { price: 20, label: 'Standard', sale_start_date: '2030-05-15 17:30:00', sale_end_date: '2030-06-30 22:00:00' };
    const created = await api.createProduct(event.id, {
      title: 'Festival Pass',
      product_type: 'TICKET',
      type: 'TIERED',
      product_category_id: categories[0].id,
      prices: [earlyBird, standard],
      sale_start_date: '2030-04-20 10:00:00',
      sale_end_date: '2030-06-30 23:00:00',
    });
    const original = await api.getProduct(event.id, created.id) as ProductWithSaleWindows;
    expect(original.sale_start_date).toMatch(/^2030-04-20[T ]14:00:00/);
    expect(original.prices?.[0].sale_start_date).toMatch(/^2030-05-01[T ]13:00:00/);

    const products = new ProductEditPage(authedPage);
    await products.goto(event.id);
    await authedPage.getByTestId('product-manage-button').first().click();
    await authedPage.getByRole('menuitem', { name: 'Duplicate' }).click();
    await authedPage.getByRole('heading', { name: 'Duplicate Product' }).waitFor();
    await expect(authedPage.getByLabel(/^Name/)).toHaveValue('Festival Pass (Copy)');
    await authedPage.getByTestId('product-duplicate-submit-button').click();
    await expect(authedPage.getByText('Successfully Duplicated Product')).toBeVisible();

    const copySummary = (await api.listProducts(event.id)).find((product) => product.title === 'Festival Pass (Copy)');
    expect(copySummary).toBeDefined();
    const copy = await api.getProduct(event.id, copySummary!.id) as ProductWithSaleWindows;

    expect(saleWindows(copy)).toEqual(saleWindows(original));
  });
});
