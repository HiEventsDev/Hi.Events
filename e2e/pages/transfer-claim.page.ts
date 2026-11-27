import type { Locator, Page } from '@playwright/test';

export class TransferClaimPage {
  constructor(private readonly page: Page) {}

  async open(claimUrl: string): Promise<void> {
    await this.page.goto(claimUrl);
    await this.page.waitForLoadState('networkidle');
  }

  heading(name: string): Locator {
    return this.page.getByRole('heading', { name });
  }

  acceptButton(): Locator {
    return this.page.getByTestId('ticket-transfer-accept-button');
  }

  async fillName(firstName: string, lastName: string): Promise<void> {
    await this.page.getByLabel(/^First name/).fill(firstName);
    await this.page.getByLabel(/^Last name/).fill(lastName);
  }

  async accept(firstName: string, lastName: string): Promise<void> {
    await this.fillName(firstName, lastName);
    await this.acceptButton().click();
  }

  async decline(): Promise<void> {
    this.page.once('dialog', (dialog) => dialog.accept());
    await this.page.getByTestId('ticket-transfer-decline-button').click();
  }
}
