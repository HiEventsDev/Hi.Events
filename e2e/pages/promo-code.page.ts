import type { Page } from '@playwright/test';

export class PromoCodePage {
  constructor(private readonly page: Page) {}

  async goto(eventId: number): Promise<void> {
    await this.page.goto(`/manage/event/${eventId}/promo-codes`);
    await this.page.waitForLoadState('networkidle');
  }

  async createPromoCode(code: string): Promise<void> {
    await this.page.getByTestId('promo-code-create-button').click();
    await this.page.getByRole('heading', { name: 'Create Promo Code' }).waitFor();
    await this.page.getByLabel(/^Code/).fill(code);
    await this.page.getByTestId('promo-code-submit-button').click();
  }
}
