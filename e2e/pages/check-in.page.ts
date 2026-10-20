import type { Locator, Page } from '@playwright/test';

export class CheckInPage {
  constructor(private readonly page: Page) {}

  async goto(shortId: string): Promise<void> {
    await this.page.goto(`/check-in/${shortId}`);
    await this.page.waitForLoadState('networkidle');
  }

  private navButton(label: string): Locator {
    return this.page
      .getByRole('navigation', { name: 'Check-in navigation' })
      .getByRole('button', { name: label });
  }

  async openSearchTab(): Promise<void> {
    await this.navButton('Search').click();
  }

  async openStatsTab(): Promise<void> {
    await this.navButton('Stats').click();
  }

  async search(query: string): Promise<void> {
    await this.page.getByLabel('Search attendees').fill(query);
  }

  attendeeRow(publicId: string): Locator {
    return this.page
      .getByRole('button', { name: /^View details for/ })
      .filter({ hasText: publicId });
  }

  checkInButton(publicId: string): Locator {
    return this.attendeeRow(publicId).getByRole('button', { name: /^Check in$/ });
  }

  checkOutButton(publicId: string): Locator {
    return this.attendeeRow(publicId).getByRole('button', { name: /^Check out$/ });
  }

  progressChip(): Locator {
    return this.page.getByLabel('Check-in progress');
  }
}
