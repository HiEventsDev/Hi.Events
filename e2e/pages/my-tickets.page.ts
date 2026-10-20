import type { Locator, Page } from '@playwright/test';

export class MyTicketsPage {
  constructor(private readonly page: Page) {}

  async open(lookupUrl: string): Promise<void> {
    await this.page.goto(lookupUrl);
    await this.page.waitForLoadState('networkidle');
  }

  orderCardHeading(eventTitle: string): Locator {
    return this.page.getByRole('heading', { name: eventTitle });
  }

  async viewOrder(): Promise<void> {
    await this.page.getByRole('link', { name: 'View Order' }).click();
  }
}
