import type { Locator, Page } from '@playwright/test';

export class AffiliatePage {
  constructor(private readonly page: Page) {}

  async goto(eventId: number): Promise<void> {
    await this.page.goto(`/manage/event/${eventId}/affiliates`);
    await this.page.waitForLoadState('networkidle');
  }

  rowByText(text: string): Locator {
    return this.page.getByRole('row').filter({ hasText: text });
  }

  async createAffiliate(details: { name: string; code: string }): Promise<void> {
    await this.page.getByTestId('affiliate-create-button').click();
    await this.page.getByRole('heading', { name: 'Create Affiliate' }).waitFor();
    await this.page.getByLabel(/^Code/).fill(details.code);
    await this.page.getByLabel(/^Name/).fill(details.name);
    await this.page.getByRole('dialog').getByRole('button', { name: 'Create Affiliate' }).click();
  }

  async clickExport(): Promise<void> {
    await this.page.getByRole('button', { name: 'Export' }).click();
  }
}
