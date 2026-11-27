import { expect, type Locator, type Page } from '@playwright/test';

export class PublicEventPage {
  constructor(private readonly page: Page) {}

  async goto(eventId: number, slug: string): Promise<void> {
    await this.page.goto(`/event/${eventId}/${slug}`);
    await this.page.waitForLoadState('networkidle');
  }

  ticketRow(productTitle: string): Locator {
    return this.page.locator('.hi-product-row').filter({ hasText: productTitle });
  }

  async expectTicketsAvailable(event: { eventId: number; slug: string; productTitle: string }, available: number): Promise<void> {
    await expect(async () => {
      await this.goto(event.eventId, event.slug);
      await expect(this.ticketRow(event.productTitle).locator('.hi-scarcity-pill')).toHaveText(`${available} available`, { timeout: 2_000 });
    }).toPass({ timeout: 30_000 });
  }

  async expectTicketSoldOut(event: { eventId: number; slug: string; productTitle: string }): Promise<void> {
    await expect(async () => {
      await this.goto(event.eventId, event.slug);
      await expect(this.ticketRow(event.productTitle)).toHaveAttribute('data-availability', 'sold-out', { timeout: 2_000 });
    }).toPass({ timeout: 30_000 });
  }

  ticketsRegion(): Locator {
    return this.page.getByRole('region', { name: 'Tickets' });
  }

  sectionBar(): Locator {
    return this.page.getByRole('navigation', { name: 'Sections' });
  }

  moreEventsSection(): Locator {
    return this.page.locator('section#more-events');
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
