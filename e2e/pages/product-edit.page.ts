import { expect, type Page } from '@playwright/test';

export class ProductEditPage {
  constructor(private readonly page: Page) {}

  async goto(eventId: number): Promise<void> {
    await this.page.goto(`/manage/event/${eventId}/products`);
    await this.page.waitForLoadState('networkidle');
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
