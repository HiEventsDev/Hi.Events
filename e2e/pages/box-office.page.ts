import { expect, type Locator, type Page } from '@playwright/test';

export class BoxOfficePage {
  constructor(private readonly page: Page) {}

  async goto(shortId: string): Promise<void> {
    await this.page.goto(`/box-office/${shortId}`);
    await this.page.waitForLoadState('networkidle');
  }

  async startSession(operatorName: string, pin: string): Promise<void> {
    await this.page.getByTestId('box-office-operator-name').fill(operatorName);
    await this.page.getByTestId('box-office-pin').fill(pin);
    await this.page.getByTestId('box-office-start-button').click();
  }

  async startSessionAsAccount(): Promise<void> {
    await this.page.getByTestId('box-office-start-button').click();
  }

  async chooseStartDate(index: number): Promise<void> {
    await this.page.getByLabel('Date').click();
    await this.page.getByRole('option').nth(index).click();
  }

  occurrenceChip(): Locator {
    return this.page.getByTestId('box-office-occurrence-switch');
  }

  async switchDate(index: number): Promise<void> {
    await this.occurrenceChip().click();
    const sheet = this.page.getByRole('dialog');
    await sheet.getByLabel('Date').click();
    await this.page.getByRole('option').nth(index).click();
    await this.page.getByTestId('box-office-occurrence-switch-submit').click();
    await sheet.waitFor({ state: 'hidden' });
  }

  async addProduct(priceId: number, times = 1): Promise<void> {
    for (let i = 0; i < times; i += 1) {
      await this.page.getByTestId(`box-office-qty-plus-${priceId}`).click();
    }
  }

  seatMap(): Locator {
    return this.page.getByTestId('box-office-seat-map');
  }

  seat(uid: string): Locator {
    return this.seatMap().locator(`[data-uid="${uid}"]`);
  }

  async tapZone(uid: string): Promise<void> {
    await this.seatMap().locator(`[data-zone-uid="${uid}"]`).click();
  }

  async bestAvailable(): Promise<void> {
    await this.page.getByTestId('box-office-best-available-button').click();
  }

  seatsToChoose(): Locator {
    return this.page.getByTestId('box-office-seats-to-choose');
  }

  cartSeats(priceId: number): Locator {
    return this.page.getByTestId(`box-office-cart-seats-${priceId}`).first();
  }

  chargeButton(): Locator {
    return this.page.getByTestId('box-office-charge-button').filter({ visible: true }).first();
  }

  async charge(): Promise<void> {
    await this.chargeButton().click();
  }

  async payCash(amount?: number): Promise<void> {
    await this.page.getByTestId('box-office-tender-cash').click();
    if (amount !== undefined) {
      await this.page.getByTestId('box-office-cash-tendered').fill(String(amount));
    }
    await this.page.getByTestId('box-office-cash-complete-button').click();
  }

  async payComp(): Promise<void> {
    await this.page.getByTestId('box-office-tender-comp').click();
    await this.page.getByTestId('box-office-comp-confirm-button').click();
  }

  async payOther(reference: string): Promise<void> {
    await this.page.getByTestId('box-office-tender-other').click();
    await expect(this.page.getByTestId('box-office-other-complete-button')).toBeDisabled();
    await this.page.getByLabel('Reference').fill(reference);
    await this.page.getByTestId('box-office-other-complete-button').click();
  }

  async completeFreeSale(): Promise<void> {
    await this.page.getByTestId('box-office-free-complete-button').click();
  }

  async applyPercentDiscount(percent: number): Promise<void> {
    await this.page.getByTestId('box-office-discount-button').click();
    await this.page.getByLabel('Percent off').fill(String(percent));
    await this.page.getByTestId('box-office-discount-apply-button').click();
  }

  async overrideLinePrice(priceId: number, price: number): Promise<void> {
    await this.page.getByTestId(`box-office-cart-line-${priceId}`).click();
    await this.page.getByLabel('Price per item').fill(String(price));
    await this.page.getByTestId('box-office-override-apply-button').click();
  }

  cartLine(priceId: number): Locator {
    return this.page.getByTestId(`box-office-cart-line-${priceId}`);
  }

  discountButton(): Locator {
    return this.page.getByTestId('box-office-discount-button');
  }

  async voidSale(): Promise<void> {
    await this.page.getByTestId('box-office-void-button').click();
    await this.page.getByRole('button', { name: 'Confirm' }).click();
  }

  async sendTicketsTo(email: string): Promise<void> {
    await this.page.getByTestId('box-office-send-tickets-email').fill(email);
    await this.page.getByTestId('box-office-send-tickets-button').click();
  }

  saleCompleteHeading(): Locator {
    return this.page.getByRole('heading', { name: 'Sale complete' });
  }

  checkInNowButton(): Locator {
    return this.page.getByTestId('box-office-check-in-now-button');
  }

  async newSale(): Promise<void> {
    await this.page.getByTestId('box-office-new-sale-button').click();
  }

  private navButton(label: string): Locator {
    return this.page
      .getByRole('navigation', { name: 'Box office navigation' })
      .getByRole('button', { name: label });
  }

  async openOrdersTab(): Promise<void> {
    await this.navButton('Orders').click();
  }

  async openScanTab(): Promise<void> {
    await this.navButton('Scan').click();
  }

  async openSellTab(): Promise<void> {
    await this.navButton('Sell').click();
  }

  async setScanMode(label: 'Scan' | 'Search'): Promise<void> {
    await this.page.getByTestId('box-office-scan-mode').getByText(label, { exact: true }).click();
  }

  async searchOrders(query: string): Promise<void> {
    await this.page.getByTestId('box-office-orders-search').fill(query);
  }

  async openOrder(publicId: string): Promise<void> {
    await this.orderRow(publicId).click();
  }

  async signOut(): Promise<void> {
    await this.page.getByRole('button', { name: 'Box office info' }).click();
    await this.page.getByTestId('box-office-sign-out-button').click();
  }

  pinInput(): Locator {
    return this.page.getByTestId('box-office-pin');
  }

  detailSheet(): Locator {
    return this.page.getByRole('dialog');
  }

  orderRow(publicId: string): Locator {
    return this.page.getByTestId(`box-office-order-${publicId}`);
  }

  productError(priceId: number): Locator {
    return this.page.getByTestId(`box-office-qty-plus-${priceId}`).locator('..').locator('..');
  }
}
