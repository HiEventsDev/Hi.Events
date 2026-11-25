import type { Locator, Page } from '@playwright/test';

export class TicketPage {
  constructor(private readonly page: Page) {}

  async goto(eventId: number, attendeeShortId: string): Promise<void> {
    await this.page.goto(`/product/${eventId}/${attendeeShortId}`);
    await this.page.waitForLoadState('networkidle');
  }

  heading(eventTitle: string): Locator {
    return this.page.getByRole('heading', { name: `Your ticket for ${eventTitle}` });
  }

  transferButton(): Locator {
    return this.page.getByTestId('ticket-transfer-button');
  }

  async startTransfer(recipientEmail: string): Promise<void> {
    await this.transferButton().click();
    const dialog = this.page.getByRole('dialog', { name: 'Transfer this ticket' });
    await dialog.getByLabel(/^Who are you giving it to/).fill(recipientEmail);
    await dialog.getByTestId('ticket-transfer-submit-button').click();
  }

  qrCode(): Locator {
    return this.page.getByTestId('ticket-qr-code');
  }

  unusableNotice(): Locator {
    return this.page.getByTestId('ticket-code-message');
  }

  pendingTransfer(): Locator {
    return this.page.getByTestId('ticket-transfer-pending');
  }
}
