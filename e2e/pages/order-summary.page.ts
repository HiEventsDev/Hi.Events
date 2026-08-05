import type { Page } from '@playwright/test';

export class OrderSummaryPage {
  constructor(private readonly page: Page) {}

  async goto(eventId: number, orderShortId: string): Promise<void> {
    await this.page.goto(`/checkout/${eventId}/${orderShortId}/summary`);
    await this.page.waitForLoadState('networkidle');
  }

  async editOrderFirstName(firstName: string): Promise<void> {
    await this.page.getByTestId('order-edit-button').click();
    await this.page.getByRole('dialog').getByLabel(/^First Name/).fill(firstName);
    await this.page.getByRole('dialog').getByRole('button', { name: 'Save' }).click();
  }

  async editFirstAttendeeFirstName(firstName: string): Promise<void> {
    await this.page.getByTestId('attendee-edit-button').first().click();
    await this.page.getByRole('dialog').getByLabel(/^First Name/).fill(firstName);
    await this.page.getByRole('dialog').getByRole('button', { name: 'Save' }).click();
  }

  async resendConfirmation(): Promise<void> {
    this.page.once('dialog', (dialog) => dialog.accept());
    await this.page.getByTestId('resend-confirmation-button').click();
  }
}
