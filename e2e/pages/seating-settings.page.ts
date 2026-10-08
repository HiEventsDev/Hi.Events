import type { Locator, Page } from '@playwright/test';

export interface SeatSelectionRules {
  preventOrphans?: boolean;
  maxSeatsPerOrder?: number | null;
  allowSeatChange?: boolean;
}

export class SeatingSettingsPage {
  constructor(private readonly page: Page) {}

  async goto(eventId: number): Promise<void> {
    await this.page.goto(`/manage/event/${eventId}/seating`);
    await this.page.waitForLoadState('networkidle');
  }

  attachButton(): Locator {
    return this.page.getByTestId('seating-attach-button');
  }

  async attach(seatMapName: string): Promise<void> {
    await this.page.getByRole('button', { name: new RegExp(seatMapName) }).click();
    await this.attachButton().click();
  }

  bandProducts(bandKey: string): Locator {
    return this.page.getByTestId(`seating-band-products-${bandKey}`);
  }

  bandPill(productTitle: string): Locator {
    return this.page.locator('.mantine-MultiSelect-pill').filter({ hasText: productTitle });
  }

  async toggleBandProducts(bandKey: string, productTitles: string[]): Promise<void> {
    await this.bandProducts(bandKey).click();
    for (const title of productTitles) {
      await this.page.getByRole('option', { name: title }).click();
    }
    await this.page.keyboard.press('Escape');
  }

  bandPriceInput(bandKey: string, productId: number): Locator {
    return this.page.getByTestId(`seating-band-adjustment-${bandKey}-${productId}`);
  }

  async setBandPrice(bandKey: string, productId: number, price: number, decimals = 2): Promise<void> {
    const change = this.page.getByTestId(`seating-band-adjust-${bandKey}-${productId}`);
    if (await change.isVisible()) {
      await change.click();
    }
    await this.bandPriceInput(bandKey, productId).fill(price.toFixed(decimals));
  }

  async resetBandPrice(bandKey: string, productId: number): Promise<void> {
    await this.page.getByTestId(`seating-band-reset-${bandKey}-${productId}`).click();
  }

  async saveBands(): Promise<void> {
    await this.page.getByTestId('seating-save-bands-button').click();
  }

  preventOrphansSwitch(): Locator {
    return this.page.getByTestId('seating-prevent-orphans-switch');
  }

  allowSeatChangeSwitch(): Locator {
    return this.page.getByTestId('seating-allow-seat-change-switch');
  }

  maxSeatsInput(): Locator {
    return this.page.getByLabel('Maximum seats per order');
  }

  async setRules(rules: SeatSelectionRules): Promise<void> {
    if (rules.preventOrphans !== undefined && (await this.preventOrphansSwitch().isChecked()) !== rules.preventOrphans) {
      await this.preventOrphansSwitch().click({ force: true });
    }
    if (rules.maxSeatsPerOrder !== undefined) {
      await this.maxSeatsInput().fill(rules.maxSeatsPerOrder === null ? '' : String(rules.maxSeatsPerOrder));
    }
    if (rules.allowSeatChange !== undefined && (await this.allowSeatChangeSwitch().isChecked()) !== rules.allowSeatChange) {
      await this.allowSeatChangeSwitch().click({ force: true });
    }
  }

  async saveRules(): Promise<void> {
    await this.page.getByTestId('seating-save-rules-button').click();
  }

  syncButton(): Locator {
    return this.page.getByTestId('seating-sync-source-button');
  }

  syncDialog(): Locator {
    return this.page.getByRole('dialog', { name: 'Changes from the venue seat map' });
  }

  async reviewSourceChanges(): Promise<Locator> {
    await this.syncButton().click();
    return this.syncDialog();
  }

  async applySourceChanges(): Promise<void> {
    await this.syncDialog().getByTestId('seating-sync-apply-button').click();
  }

  detachButton(): Locator {
    return this.page.getByTestId('seating-detach-button');
  }

  async detach(): Promise<void> {
    await this.detachButton().click();
    await this.page.getByRole('dialog').getByRole('button', { name: 'Confirm' }).click();
  }

  async openSales(): Promise<void> {
    await this.page.getByTestId('seating-sales-button').click();
    await this.page.getByTestId('seating-sales-map').waitFor();
  }

  async openDesigner(): Promise<void> {
    await this.page.getByTestId('seating-edit-map-button').click();
    await this.page.getByTestId('seat-map-designer-seat-count').waitFor();
  }
}
