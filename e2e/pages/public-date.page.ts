import { expect, type Locator, type Page } from '@playwright/test';
import { CheckoutPage, type BuyerDetails } from './checkout.page';
import { occurrenceDayLabel, PublicOccurrenceSelector } from './occurrence.page';
import type { Occurrence } from '../api/types';

export interface PublicDateEvent {
  eventId: number;
  slug: string;
  productTitle: string;
}

export class PublicDateView {
  private readonly checkout: CheckoutPage;
  private readonly selector: PublicOccurrenceSelector;

  constructor(private readonly page: Page, private readonly event: PublicDateEvent) {
    this.checkout = new CheckoutPage(page);
    this.selector = new PublicOccurrenceSelector(page);
  }

  async open(date: Occurrence): Promise<void> {
    await this.checkout.gotoPublicEvent(this.event.eventId, this.event.slug);
    await this.selector.selectDay(occurrenceDayLabel(date.start_date));
    await expect(this.selector.productsLoadingOverlay()).toHaveCount(0);
  }

  productRow(title = this.event.productTitle): Locator {
    return this.page.locator('.hi-product-row').filter({ hasText: title });
  }

  tierRow(productTitle: string, tierLabel: string): Locator {
    return this.productRow(productTitle).locator('.hi-price-tier-row').filter({ hasText: tierLabel });
  }

  remainingPill(title = this.event.productTitle): Locator {
    return this.productRow(title).locator('.hi-scarcity-pill');
  }

  async expectRemaining(date: Occurrence, remaining: number, title?: string): Promise<void> {
    await this.open(date);
    await expect(this.remainingPill(title)).toHaveText(`${remaining} available`);
  }

  async expectSoldOut(date: Occurrence, title?: string): Promise<void> {
    await this.open(date);
    await expect(this.productRow(title).getByText(/sold out/i)).toBeVisible();
  }

  async expectAvailable(date: Occurrence, title?: string): Promise<void> {
    await this.open(date);
    await expect(this.productRow(title)).toBeVisible();
    await expect(this.productRow(title).getByText(/sold out/i)).toHaveCount(0);
    await expect(this.productRow(title).locator('.hi-product-quantity-selector')).toBeVisible();
  }

  async reserve(date: Occurrence, quantity = 1, title = this.event.productTitle): Promise<void> {
    await this.open(date);
    await this.checkout.setQuantityForProduct(title, quantity);
    await this.checkout.continueToCheckout();
    await expect(this.page.getByLabel(/^First Name/).first()).toBeVisible();
  }

  async reserveOne(date: Occurrence, title?: string): Promise<void> {
    await this.reserve(date, 1, title);
  }

  async completeFreeOrder(date: Occurrence, quantity: number, buyer: BuyerDetails, title = this.event.productTitle): Promise<void> {
    await this.reserve(date, quantity, title);
    await this.checkout.fillOrderDetails(buyer);
    await this.checkout.completeFreeOrder();
    await expect(this.page.getByText(/You're going to/)).toBeVisible();
  }
}
