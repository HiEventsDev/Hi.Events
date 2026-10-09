import type { Locator, Page } from '@playwright/test';

export class OrderPage {
  constructor(private readonly page: Page) {}

  async goto(eventId: number): Promise<void> {
    await this.page.goto(`/manage/event/${eventId}/orders`);
    await this.page.waitForLoadState('networkidle');
  }

  rowByEmail(email: string): Locator {
    return this.page.getByRole('row').filter({ hasText: email });
  }

  async chooseRowAction(email: string, action: string): Promise<void> {
    const row = this.rowByEmail(email);
    await row.getByTestId('order-actions-trigger').click();
    await this.page.getByRole('menuitem', { name: action }).click();
  }

  detailsDrawer(): Locator {
    return this.page.getByRole('dialog');
  }

  async sendMessageToBuyer(subject: string, body: string): Promise<void> {
    await this.page.getByRole('heading', { name: 'Send a message' }).waitFor();
    await this.page.getByLabel(/^Subject/).fill(subject);

    const editor = this.page.locator('.ProseMirror').first();
    await editor.click();
    await editor.fill(body);

    await this.page.getByRole('checkbox', { name: /I confirm this is a transactional message/ }).check();
    await this.page.getByTestId('message-send-button').click();
  }

  async confirmCancelOrder(): Promise<void> {
    await this.page.getByRole('heading', { name: /^Cancel Order/ }).waitFor();
    await this.page.getByRole('button', { name: 'Cancel Order' }).click();
  }

  async filterByProduct(productTitle: string): Promise<void> {
    await this.page.getByRole('button', { name: /Filters/ }).click();
    const dialog = this.page.getByRole('dialog');
    await dialog.getByRole('combobox', { name: 'Product' }).click();
    await this.page.getByRole('option', { name: productTitle }).click();
    await dialog.getByRole('button', { name: 'Apply' }).click();
    await dialog.waitFor({ state: 'hidden' });
  }

  exportButton(): Locator {
    return this.page.getByRole('button', { name: 'Export' });
  }
}
