import { type Page } from '@playwright/test';

export interface BuyerDetails {
  firstName: string;
  lastName: string;
  email: string;
}

export class CheckoutPage {
  constructor(private readonly page: Page) {}

  async gotoPublicEvent(eventId: number, slug: string): Promise<void> {
    await this.page.goto(`/event/${eventId}/${slug}`);
    await this.page.waitForLoadState('networkidle');
  }

  async setFirstProductQuantity(quantity: number): Promise<void> {
    const input = this.page.locator('.hi-product-quantity-selector input').first();
    await input.fill(String(quantity));
  }

  async continueToCheckout(): Promise<void> {
    await this.page.getByTestId('checkout-continue-button').click();
    await this.page.waitForURL(/\/checkout\/\d+\/[^/]+\/details/);
  }

  private async fillContact(index: number, details: BuyerDetails): Promise<void> {
    await this.page.getByLabel(/^First Name/).nth(index).fill(details.firstName);
    await this.page.getByLabel(/^Last Name/).nth(index).fill(details.lastName);
    await this.page.getByLabel(/^Email Address/).nth(index).fill(details.email);
    await this.page.getByLabel(/^Confirm Email Address/).nth(index).fill(details.email);
  }

  async fillOrderDetails(details: BuyerDetails): Promise<void> {
    await this.fillContact(0, details);
  }

  async fillFirstAttendee(details: BuyerDetails): Promise<void> {
    await this.fillContact(1, details);
  }

  async completeFreeOrder(): Promise<void> {
    await this.page.getByRole('button', { name: 'Complete Order' }).click();
    await this.page.waitForURL(/\/checkout\/\d+\/[^/]+\/summary/);
    await this.page.waitForLoadState('networkidle');
  }

  async continueToPayment(): Promise<void> {
    await this.page.getByRole('button', { name: 'Continue to Payment' }).click();
    await this.page.waitForURL(/\/checkout\/\d+\/[^/]+\/payment/);
  }

  async payWithStripeTestCard(card = '4242424242424242'): Promise<void> {
    const stripeFrame = this.page.frameLocator('iframe[title="Secure payment input frame"]');
    await stripeFrame.getByPlaceholder('1234 1234 1234 1234').fill(card);
    await stripeFrame.getByPlaceholder('MM / YY').fill('12 / 34');
    await stripeFrame.getByPlaceholder('CVC').fill('123');
    const zip = stripeFrame.getByPlaceholder('12345');
    if (await zip.count()) {
      await zip.fill('12345');
    }
    await this.page.getByRole('button', { name: /^Pay\b/ }).click();
    await this.page.waitForURL(/\/checkout\/\d+\/[^/]+\/summary/, { timeout: 30_000 });
    await this.page.waitForLoadState('networkidle');
  }
}
