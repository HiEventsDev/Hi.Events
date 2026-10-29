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

  async openAttendee(publicId: string): Promise<void> {
    await this.attendeeRow(publicId).getByText(publicId).click();
  }

  printBadgeButton(): Locator {
    return this.page.getByTestId('check-in-print-badge-button');
  }

  badgePrintNote(): Locator {
    return this.page.getByTestId('check-in-badge-print-note');
  }

  async enableAutoPrint(): Promise<void> {
    await this.page.getByTestId('check-in-badge-printer-button').click();
    await this.page.getByTestId('check-in-auto-print-switch').click({ force: true });
    await this.page.getByRole('button', { name: 'Done' }).click();
  }

  async chooseBackPrinting(option: 'Front only' | 'Two-sided printer' | 'Fold-over'): Promise<void> {
    await this.page.getByTestId('check-in-badge-printer-button').click();
    await this.page.getByTestId('check-in-back-printing-select').click();
    await this.page.getByRole('option', { name: option, exact: true }).click();
    await this.page.getByRole('button', { name: 'Done' }).click();
  }

  async scanWithUsbScanner(publicId: string): Promise<void> {
    await this.page.evaluate(async (code) => {
      for (const key of code) {
        window.dispatchEvent(new KeyboardEvent('keypress', { key }));
        await new Promise((resolve) => setTimeout(resolve, 10));
      }
      window.dispatchEvent(new KeyboardEvent('keypress', { key: 'Enter' }));
    }, publicId);
  }

  kiosk(): Locator {
    return this.page.getByTestId('kiosk');
  }
}
