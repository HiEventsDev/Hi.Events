import { type Locator, type Page } from '@playwright/test';

export class PublicEventPage {
  constructor(private readonly page: Page) {}

  async goto(eventId: number, slug: string): Promise<void> {
    await this.page.goto(`/event/${eventId}/${slug}`);
    await this.page.waitForLoadState('networkidle');
  }

  joinWaitlistButton(): Locator {
    return this.page.getByTestId('join-waitlist-button');
  }

  async joinWaitlist(details: { firstName: string; email: string }): Promise<void> {
    await this.joinWaitlistButton().click();
    const dialog = this.page.getByRole('dialog');
    await dialog.getByLabel(/^First Name/).fill(details.firstName);
    await dialog.getByLabel(/^Email/).fill(details.email);
    await dialog.getByLabel(/agree to receive email notifications/).check();
    await dialog.getByRole('button', { name: 'Join Waitlist' }).click();
  }

  async closeWaitlistSuccessModal(): Promise<void> {
    await this.page.getByRole('dialog').getByRole('button', { name: 'Close' }).click();
  }
}
