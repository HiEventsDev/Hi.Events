import type { Page } from '@playwright/test';

interface CreatePromoCodeOptions {
  discountType?: 'Percentage' | 'Fixed amount';
  discount?: number;
  appliesTo?: 'Entire order' | 'Each product';
  expiryDate?: string;
}

export class PromoCodePage {
  constructor(private readonly page: Page) {}

  async goto(eventId: number): Promise<void> {
    await this.page.goto(`/manage/event/${eventId}/promo-codes`);
    await this.page.waitForLoadState('networkidle');
  }

  async createPromoCode(code: string, options: CreatePromoCodeOptions = {}): Promise<void> {
    await this.page.getByTestId('promo-code-create-button').click();
    await this.page.getByRole('heading', { name: 'Create Promo Code' }).waitFor();
    await this.page.getByLabel(/^Code/).fill(code);

    if (options.discountType) {
      await this.page.getByRole('combobox', { name: 'Discount Type' }).click();
      await this.page.getByRole('option', { name: options.discountType }).click();
    }

    if (options.discount !== undefined) {
      await this.page.getByLabel(/^Discount (%|in)/).fill(String(options.discount));
    }

    if (options.appliesTo) {
      await this.page.getByTestId('promo-code-discount-applies-to').getByText(options.appliesTo).click();
    }

    if (options.expiryDate) {
      await this.page.getByTestId('promo-code-advanced-toggle').click();
      await this.page.getByLabel('Expiry Date').fill(options.expiryDate);
    }

    await this.page.getByTestId('promo-code-submit-button').click();
  }

  async openEditModal(code: string): Promise<void> {
    const row = this.page.getByRole('row').filter({ hasText: code.toUpperCase() });
    await row.getByTestId('promo-code-actions-button').click();
    await this.page.getByRole('menuitem', { name: 'Edit Code' }).click();
    await this.page.getByRole('heading', { name: 'Edit Promo Code' }).waitFor();
  }
}
