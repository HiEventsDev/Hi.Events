import { type Locator, type Page } from '@playwright/test';

export class WaitlistPage {
  constructor(private readonly page: Page) {}

  async goto(eventId: number): Promise<void> {
    await this.page.goto(`/manage/event/${eventId}/sold-out-waitlist`);
    await this.page.waitForLoadState('networkidle');
  }

  entryRow(email: string): Locator {
    return this.page.getByRole('row').filter({ hasText: email });
  }

  entryStatus(email: string, status: string): Locator {
    return this.entryRow(email).locator(`[data-status="${status}"]`);
  }

  statsCard(label: string): Locator {
    return this.page.locator('[class*="Paper"]').filter({ has: this.page.getByText(label, { exact: true }) });
  }

  async offerTickets(productTitle: string): Promise<void> {
    await this.page.getByTestId('waitlist-offer-next-button').click();
    const dialog = this.page.getByRole('dialog');
    const offerButton = dialog.getByRole('row').filter({ hasText: productTitle }).getByRole('button', { name: 'Offer' });
    await offerButton.click();
    await offerButton.waitFor({ state: 'visible' });
    await this.page.keyboard.press('Escape');
    await dialog.waitFor({ state: 'hidden' });
  }

  async removeEntry(email: string): Promise<void> {
    await this.entryRow(email).getByRole('button').click();
    await this.page.getByRole('menuitem', { name: 'Remove' }).click();
    await this.page.getByRole('dialog').getByRole('button', { name: 'Remove' }).click();
  }
}
