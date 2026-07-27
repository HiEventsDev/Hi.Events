import type { Locator, Page } from '@playwright/test';

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

  async selectPriceType(optionName: RegExp): Promise<void> {
    await this.page
      .getByLabel('Create Ticket or Product')
      .getByText('Paid Product', { exact: true })
      .click();
    await this.page.getByRole('option', { name: optionName }).click();
  }

  async fillTier(index: number, price: string, label: string): Promise<void> {
    await this.page.getByLabel(/^Price/).nth(index).fill(price);
    await this.page.getByLabel(/^Label/).nth(index).fill(label);
  }

  async addTier(): Promise<void> {
    await this.page.getByTestId('product-add-tier-button').click();
  }

  async openAdvancedOptions(): Promise<void> {
    await this.page.getByRole('button', { name: /Taxes, Fees, Visibility/ }).click();
  }

  hiddenSwitch(): Locator {
    return this.page.getByLabel('Hide this product from customers');
  }

  async submitCreate(): Promise<void> {
    await this.page.getByRole('button', { name: 'Create Product' }).click();
  }

  async openEditModal(): Promise<void> {
    await this.page.getByTestId('product-manage-button').click();
    await this.page.getByTestId('product-edit-menu-item').click();
    await this.page.getByRole('heading', { name: 'Edit Product' }).waitFor();
  }
}
