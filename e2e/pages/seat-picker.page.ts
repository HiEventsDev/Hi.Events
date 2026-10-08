import { expect, type Locator, type Page } from '@playwright/test';

export interface SeatPickerOptions {
  fullScreen?: boolean;
  touch?: boolean;
}

export class SeatPickerPage {
  private readonly fullScreen: boolean;
  private readonly touch: boolean;

  constructor(private readonly page: Page, options: SeatPickerOptions = {}) {
    this.fullScreen = options.fullScreen ?? false;
    this.touch = options.touch ?? false;
  }

  root(): Locator {
    return this.fullScreen
      ? this.page.getByRole('dialog', { name: 'Choose your seats' })
      : this.page.getByTestId('seated-products-section');
  }

  map(): Locator {
    return this.fullScreen ? this.root().getByTestId('seat-picker-map') : this.root();
  }

  seat(uid: string): Locator {
    return this.map().locator(`[data-uid="${uid}"]`);
  }

  zone(uid: string): Locator {
    return this.map().locator(`[data-zone-uid="${uid}"]`);
  }

  seatsInState(state: string): Locator {
    return this.map().locator(`[data-state="${state}"]`);
  }

  private async press(locator: Locator): Promise<void> {
    if (this.touch) {
      await locator.tap();
    } else {
      await locator.click();
    }
  }

  async selectSeat(uid: string): Promise<void> {
    const seat = this.seat(uid);
    if (!this.touch) {
      await seat.click();
      return;
    }
    await expect(async () => {
      await seat.tap();
      await expect(seat).toHaveAttribute('data-state', 'selected', { timeout: 1_000 });
    }).toPass({ timeout: 15_000 });
  }

  async selectSeats(uids: string[]): Promise<void> {
    for (const uid of uids) {
      await this.selectSeat(uid);
    }
  }

  async selectZone(uid: string, quantity: number): Promise<void> {
    await this.press(this.zone(uid));
    await this.page.getByLabel('How many?').fill(String(quantity));
    await this.confirmSeatSheet();
  }

  ticketTypeOption(priceId: number): Locator {
    return this.page.getByTestId(`ticket-type-option-${priceId}`);
  }

  lineTicketType(seatUid: string): Locator {
    return this.root().getByTestId(`seat-line-ticket-type-${seatUid}`);
  }

  checkedTicketType(seatUid: string): Locator {
    return this.lineTicketType(seatUid).getByRole('radio', { checked: true });
  }

  async changeTicketType(seatUid: string, optionLabel: string | RegExp): Promise<void> {
    await this.press(this.lineTicketType(seatUid).getByRole('radio', { name: optionLabel }));
  }

  async confirmSeatSheet(): Promise<void> {
    await this.press(this.page.getByTestId('seat-sheet-confirm-button'));
  }

  bestAvailableQuantity(): Locator {
    return this.root().getByTestId('best-available-quantity');
  }

  async bestAvailable(quantity?: number): Promise<void> {
    if (quantity !== undefined) {
      await this.bestAvailableQuantity().fill(String(quantity));
      await this.bestAvailableQuantity().blur();
    }
    await this.press(this.root().getByTestId('best-available-button'));
  }

  basketLine(label: string): Locator {
    return this.root().getByText(label, { exact: true });
  }

  removeButtons(): Locator {
    return this.root().getByRole('button', { name: /^Remove / });
  }

  async chosenLabels(): Promise<string[]> {
    return this.removeButtons().evaluateAll((buttons) =>
      buttons.map((button) => button.getAttribute('aria-label')!.replace(/^Remove /, '')));
  }

  seatTotal(count: number): Locator {
    return this.root().getByText(count === 1 ? '1 seat' : `${count} seats`, { exact: true });
  }

  conflictNotice(): Locator {
    return this.root().getByTestId('seat-conflict-notice');
  }

  continueButton(): Locator {
    return this.fullScreen
      ? this.root().getByTestId('seat-picker-continue-button')
      : this.page.getByTestId('checkout-continue-button');
  }

  extrasStep(): Locator {
    return this.page.getByTestId('seat-picker-extras-step');
  }

  async continueToDetails(): Promise<void> {
    const details = /\/checkout\/\d+\/[^/]+\/details/;
    await this.press(this.continueButton());
    if (!this.fullScreen) {
      await this.page.waitForURL(details);
      return;
    }
    const checkoutButton = this.page.getByTestId('seat-picker-checkout-button');
    await Promise.race([
      this.page.waitForURL(details).catch(() => undefined),
      checkoutButton.waitFor().catch(() => undefined),
    ]);
    if (await checkoutButton.isVisible()) {
      await this.press(checkoutButton);
      await this.page.waitForURL(details);
    }
  }

  async openFullScreen(): Promise<SeatPickerPage> {
    const trigger = this.touch
      ? this.page.getByTestId('choose-seats-button')
      : this.page.getByTestId('seat-map-expand-button');
    await this.press(trigger);
    const picker = new SeatPickerPage(this.page, { fullScreen: true, touch: this.touch });
    await expect(picker.root()).toBeVisible();
    return picker;
  }

  async showListView(): Promise<void> {
    await this.root().getByLabel('List view').click();
  }

  listSeat(name: string | RegExp): Locator {
    return this.root().getByRole('button', { name });
  }
}
