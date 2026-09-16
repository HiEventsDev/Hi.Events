import { expect, type Locator, type Page } from '@playwright/test';

export class ProductEditPage {
  constructor(private readonly page: Page) {}

  async goto(eventId: number): Promise<void> {
    await this.page.goto(`/manage/event/${eventId}/products`);
    await this.page.waitForLoadState('networkidle');
  }

  async openProductAt(index: number): Promise<void> {
    await this.page.getByTestId('product-manage-button').nth(index).click();
    await this.page.getByTestId('product-edit-menu-item').click();
    await this.page.getByRole('heading', { name: 'Edit Product' }).waitFor();
  }

  salesCount(index = 0): Locator {
    return this.page.locator('[class*="salesCount"]').nth(index);
  }

  async openFirstProduct(): Promise<void> {
    await this.page.getByTestId('product-manage-button').first().click();
    await this.page.getByTestId('product-edit-menu-item').click();
    await this.page.getByRole('heading', { name: 'Edit Product' }).waitFor();
  }

  async setQuantity(quantity: number): Promise<void> {
    await this.page.getByLabel(/^Quantity Available/).fill(String(quantity));
  }

  quantityScopeButton(): Locator {
    return this.page.getByTestId('product-quantity-applies-to');
  }

  async chooseQuantityScope(scope: 'OCCURRENCE' | 'EVENT'): Promise<void> {
    await this.quantityScopeButton().click();
    await this.page.getByTestId(`product-quantity-applies-to-option-${scope}`).click();
  }

  async clickSubmit(): Promise<void> {
    await this.page.getByTestId('product-edit-submit-button').click();
  }

  async submitEdit(): Promise<void> {
    await this.clickSubmit();
    await this.page.getByRole('heading', { name: 'Edit Product' }).waitFor({ state: 'hidden' });
  }

  async renameFirstProduct(currentName: string, newName: string): Promise<void> {
    await this.page.getByTestId('product-manage-button').click();
    await this.page.getByTestId('product-edit-menu-item').click();
    await this.page.getByRole('heading', { name: 'Edit Product' }).waitFor();

    const nameInput = this.page.getByLabel(/^Name/);
    await expect(nameInput).toHaveValue(currentName);
    await nameInput.fill(newName);

    await this.page.getByTestId('product-edit-submit-button').click();
  }
}
