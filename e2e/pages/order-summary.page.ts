import type { Locator, Page } from '@playwright/test';

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

  async editOrderEmail(email: string): Promise<void> {
    await this.page.getByTestId('order-edit-button').click();
    await this.page.getByRole('dialog').getByLabel(/^Email/).fill(email);
    await this.page.getByRole('dialog').getByRole('button', { name: 'Save' }).click();
  }

  async editFirstAttendeeEmail(email: string): Promise<void> {
    await this.page.getByTestId('attendee-edit-button').first().click();
    await this.page.getByRole('dialog').getByLabel(/^Email/).fill(email);
    await this.page.getByRole('dialog').getByRole('button', { name: 'Save' }).click();
  }

  transferButton(): Locator {
    return this.page.getByTestId('ticket-transfer-button');
  }

  async startTransfer(recipientEmail: string): Promise<void> {
    await this.transferButton().first().click();
    const dialog = this.page.getByRole('dialog', { name: 'Transfer this ticket' });
    await dialog.getByLabel(/^Who are you giving it to/).fill(recipientEmail);
    await dialog.getByTestId('ticket-transfer-submit-button').click();
  }

  pendingTransfer(): Locator {
    return this.page.getByTestId('ticket-transfer-pending');
  }

  async cancelTransfer(): Promise<void> {
    this.page.once('dialog', (dialog) => dialog.accept());
    await this.page.getByTestId('ticket-transfer-cancel-button').click();
  }

  guest(name: string): Locator {
    return this.page.getByTestId('order-guest').filter({ hasText: name });
  }

  sellButton(): Locator {
    return this.page.getByTestId('ticket-resale-button');
  }

  sellDialog(): Locator {
    return this.page.getByRole('dialog', { name: 'Sell this ticket' });
  }

  async confirmSell(): Promise<void> {
    await this.sellDialog().getByTestId('ticket-resale-submit-button').click();
    await this.sellDialog().waitFor({ state: 'hidden' });
  }

  listedForResale(): Locator {
    return this.page.getByTestId('ticket-resale-listed');
  }

  async withdrawListing(): Promise<void> {
    await this.page.getByTestId('ticket-resale-withdraw-button').click();
    await this.page.getByRole('dialog', { name: 'Withdraw this listing?' }).getByRole('button', { name: 'Withdraw listing' }).click();
  }

  transferredTickets(): Locator {
    return this.page.getByTestId('transferred-ticket');
  }

  async resendConfirmation(): Promise<void> {
    this.page.once('dialog', (dialog) => dialog.accept());
    await this.page.getByTestId('resend-confirmation-button').click();
  }
}
