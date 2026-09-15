import type { Locator, Page } from '@playwright/test';

export type ProductLedgerRow =
  | 'description'
  | 'sale-window'
  | 'event-page'
  | 'taxes'
  | 'order-limits'
  | 'addons'
  | 'highlight'
  | 'access';

export class ProductCreatePage {
  constructor(private readonly page: Page) {}

  async goto(eventId: number): Promise<void> {
    await this.page.goto(`/manage/event/${eventId}/products`);
    await this.page.waitForLoadState('networkidle');
  }

  async openCreateModal(): Promise<void> {
    await this.page.getByTestId('product-create-button').click();
    await this.page.getByRole('menuitem', { name: 'Ticket or Product' }).click();
    await this.page.getByRole('heading', { name: 'Create Ticket or Product' }).waitFor();
  }

  async selectPriceType(segmentLabel: string): Promise<void> {
    await this.page
      .getByTestId('product-price-type')
      .getByText(segmentLabel, { exact: true })
      .click();
  }

  async fillTier(index: number, price: string, label: string): Promise<void> {
    await this.page.getByLabel(/^Price/).nth(index).fill(price);
    await this.page.getByLabel(/^Label/).nth(index).fill(label);
  }

  async addTier(): Promise<void> {
    await this.page.getByTestId('product-add-tier-button').click();
  }

  async dragTierAbove(fromIndex: number, toIndex: number): Promise<void> {
    const source = await this.page.getByTestId(`product-tier-${fromIndex}-drag-handle`).boundingBox();
    const target = await this.page.getByTestId(`product-tier-${toIndex}-drag-handle`).boundingBox();
    if (!source || !target) {
      throw new Error('Tier drag handles are not visible');
    }

    const startX = source.x + source.width / 2;
    const startY = source.y + source.height / 2;
    const endY = target.y - 10;

    await this.page.mouse.move(startX, startY);
    await this.page.mouse.down();
    await this.page.mouse.move(startX, startY - 5, { steps: 3 });
    await this.page.mouse.move(startX, endY, { steps: 12 });
    await this.page.mouse.up();
  }

  async openLedgerRow(row: ProductLedgerRow): Promise<void> {
    await this.page.getByTestId(`product-ledger-${row}`).click();
  }

  hiddenSwitch(): Locator {
    return this.page.getByLabel('Hide this product from customers');
  }

  sequentialReleaseSwitch(): Locator {
    return this.page.getByLabel('Release tiers in order');
  }

  async submitCreate(): Promise<void> {
    await this.page.getByTestId('product-create-submit-button').click();
  }

  async openEditModal(index = 0): Promise<void> {
    await this.page.getByTestId('product-manage-button').nth(index).click();
    await this.page.getByTestId('product-edit-menu-item').click();
    await this.page.getByRole('heading', { name: 'Edit Product' }).waitFor();
  }

  addonOnlySwitch(): Locator {
    return this.page.getByLabel('Only available as an add-on');
  }

  async selectAddonProduct(productTitle: string): Promise<void> {
    await this.page.getByRole('combobox', { name: 'Add-on products' }).click();
    await this.page.getByRole('option', { name: productTitle }).click();
    await this.page.keyboard.press('Escape');
  }
}
