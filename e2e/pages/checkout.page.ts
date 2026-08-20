import { type Frame, type FrameLocator, type Locator, type Page } from '@playwright/test';

export type CheckoutSurface = Page | Frame;

export interface BuyerDetails {
  firstName: string;
  lastName: string;
  email: string;
}

export async function setWidgetQuantity(scope: Locator | FrameLocator | CheckoutSurface, quantity: number): Promise<void> {
  const selector = scope.locator('.hi-product-quantity-selector').first();
  const input = selector.locator('input');
  if (!(await input.isVisible())) {
    if (quantity === 0) {
      return;
    }
    await selector.getByRole('button', { name: 'Increase quantity' }).click();
  }
  await input.fill(String(quantity));
  if (quantity !== 0) {
    await input.blur();
  }
}

export class CheckoutPage {
  private readonly surface: CheckoutSurface;

  constructor(private readonly page: Page, surface?: CheckoutSurface) {
    this.surface = surface ?? page;
  }

  private async reloadSurface(): Promise<void> {
    if (this.surface === this.page) {
      await this.page.reload();
    } else {
      await (this.surface as Frame).goto(this.surface.url());
    }
    await this.surface.waitForLoadState('networkidle');
  }

  async gotoPublicEvent(eventId: number, slug: string): Promise<void> {
    await this.page.goto(`/event/${eventId}/${slug}`);
    await this.page.waitForLoadState('networkidle');
  }

  async setFirstProductQuantity(quantity: number): Promise<void> {
    await setWidgetQuantity(this.surface, quantity);
  }

  async setQuantityForProduct(productTitle: string, quantity: number): Promise<void> {
    const row = this.surface.locator('.hi-product-row').filter({ hasText: productTitle });
    await setWidgetQuantity(row, quantity);
  }

  async setAddonQuantity(addonTitle: string, quantity: number): Promise<void> {
    const addonRow = this.surface.locator('.hi-product-addon').filter({ hasText: addonTitle });
    await setWidgetQuantity(addonRow, quantity);
  }

  async applyPromoCode(code: string): Promise<void> {
    await this.surface.getByText('Have a promo code?').click();
    await this.surface.locator('.hi-promo-code-input').fill(code);
    await this.surface.getByTestId('promo-code-apply-button').click();
  }

  async answerTextQuestion(title: string, value: string): Promise<void> {
    await this.surface.getByLabel(new RegExp(`^${title}`)).fill(value);
  }

  async chooseRadioOption(option: string): Promise<void> {
    await this.surface.getByRole('radio', { name: option }).check();
  }

  async chooseOfflinePayment(): Promise<void> {
    const offlineTab = this.surface.getByRole('button', { name: 'Offline' });
    if (await offlineTab.isVisible()) {
      await offlineTab.click();
    }
    await this.surface.getByTestId('offline-payment-button').click();
    await this.surface.waitForURL(/\/checkout\/\d+\/[^/]+\/summary/);
    await this.reloadSurface();
  }

  async continueToCheckout(): Promise<void> {
    await this.surface.getByTestId('checkout-continue-button').click();
    await this.surface.waitForURL(/\/checkout\/\d+\/[^/]+\/details/);
  }

  private async fillContact(index: number, details: BuyerDetails): Promise<void> {
    await this.surface.getByLabel(/^First Name/).nth(index).fill(details.firstName);
    await this.surface.getByLabel(/^Last Name/).nth(index).fill(details.lastName);
    await this.surface.getByLabel(/^Email Address/).nth(index).fill(details.email);
    await this.surface.getByLabel(/^Confirm Email Address/).nth(index).fill(details.email);
  }

  async fillOrderDetails(details: BuyerDetails): Promise<void> {
    await this.fillContact(0, details);
  }

  async fillFirstAttendee(details: BuyerDetails): Promise<void> {
    await this.fillContact(1, details);
  }

  async completeFreeOrder(): Promise<void> {
    await this.surface.getByRole('button', { name: 'Complete Order' }).click();
    await this.surface.waitForURL(/\/checkout\/\d+\/[^/]+\/summary/);
    await this.reloadSurface();
  }

  async continueToPayment(): Promise<void> {
    await this.surface.getByRole('button', { name: 'Continue to Payment' }).click();
    await this.surface.waitForURL(/\/checkout\/\d+\/[^/]+\/payment/);
  }

  async fillStripeCard(card = '4242424242424242'): Promise<void> {
    const stripeFrame = this.page.frameLocator('iframe[title="Secure payment input frame"]');
    await stripeFrame.getByPlaceholder('1234 1234 1234 1234').fill(card);
    await stripeFrame.getByPlaceholder('MM / YY').fill('12 / 34');
    await stripeFrame.getByPlaceholder('CVC').fill('123');
    const country = stripeFrame.getByLabel('Country', { exact: true });
    if (await country.count()) {
      await country.selectOption({ label: 'United States' });
    }
    const zip = stripeFrame.getByPlaceholder('12345');
    if (await zip.count()) {
      await zip.fill('12345');
    }
  }

  private async waitForStripeFrameToSettle(): Promise<void> {
    const frame = this.page.locator('iframe[title="Secure payment input frame"]');
    await this.page.waitForTimeout(500);
    let previousHeight = -1;
    for (let attempt = 0; attempt < 20; attempt++) {
      const height = (await frame.boundingBox())?.height ?? -1;
      if (height === previousHeight) {
        return;
      }
      previousHeight = height;
      await this.page.waitForTimeout(250);
    }
  }

  async clickPay(): Promise<void> {
    await this.page.getByRole('heading', { name: 'Payment' }).click();
    await this.waitForStripeFrameToSettle();
    await this.page.getByRole('button', { name: /^Pay\b/ }).dispatchEvent('click');
  }

  async payWithStripeTestCard(card = '4242424242424242'): Promise<void> {
    await this.fillStripeCard(card);
    await this.clickPay();
    try {
      await this.page.waitForURL(/\/checkout\/\d+\/[^/]+\/(payment_return|summary)/, { timeout: 15_000 });
    } catch {
      await this.page.getByRole('button', { name: /^Pay\b/ }).dispatchEvent('click');
      await this.page.waitForURL(/\/checkout\/\d+\/[^/]+\/(payment_return|summary)/, { timeout: 30_000 });
    }
  }
}
