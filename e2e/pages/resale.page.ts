import type { Locator, Page } from '@playwright/test';

export class ResalePage {
  constructor(private readonly page: Page) {}

  async goto(eventId: number): Promise<void> {
    await this.page.goto(`/manage/event/${eventId}/resale`);
    await this.page.getByRole('heading', { name: 'Ticket resale' }).waitFor();
  }

  table(): Locator {
    return this.page.getByTestId('resale-listings-table');
  }

  row(text: string): Locator {
    return this.table().getByRole('row').filter({ hasText: text });
  }

  status(text: string): Locator {
    return this.row(text).getByTestId('resale-listing-status');
  }

  refund(text: string): Locator {
    return this.row(text).getByTestId('resale-listing-refund');
  }

  actionsTrigger(): Locator {
    return this.page.getByTestId('resale-listing-actions-trigger');
  }

  attentionSummary(): Locator {
    return this.page.getByTestId('resale-attention-summary');
  }

  async filterBy(status: 'All' | 'Open' | 'Sold' | 'Closed'): Promise<void> {
    await this.page.getByRole('radiogroup', { name: 'Filter by status' }).getByText(status, { exact: true }).click();
  }

  async closeListing(text: string): Promise<void> {
    await this.row(text).getByTestId('resale-listing-actions-trigger').click();
    await this.page.getByTestId('resale-close-listing-button').click();
    await this.page.getByRole('dialog').getByRole('button', { name: 'Close listing' }).click();
  }

  async retryRefund(text: string): Promise<void> {
    await this.row(text).getByTestId('resale-listing-actions-trigger').click();
    await this.page.getByTestId('resale-retry-refund-button').click();
  }
}
