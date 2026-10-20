import fs from 'node:fs';
import type { Download, Locator, Page } from '@playwright/test';

export class ProductPurchasesPage {
  constructor(private readonly page: Page) {}

  async gotoProducts(eventId: number): Promise<void> {
    await this.page.goto(`/manage/event/${eventId}/products`);
    await this.page.waitForLoadState('networkidle');
  }

  async open(eventId: number, productIndex = 0): Promise<void> {
    await this.gotoProducts(eventId);
    await this.page.getByTestId('product-manage-button').nth(productIndex).click();
    await this.page.getByTestId('product-purchases-menu-item').click();
    await this.drawer().getByText('Gross sales').waitFor();
  }

  drawer(): Locator {
    return this.page.getByRole('dialog').filter({ has: this.page.getByTestId('product-purchases-export-button') });
  }

  stat(label: string): Locator {
    return this.drawer()
      .locator('[class*="statLabel"]')
      .filter({ hasText: new RegExp(`^${label}$`) })
      .locator('xpath=following-sibling::*[1]');
  }

  rows(): Locator {
    return this.drawer().getByTestId('product-purchase-row');
  }

  row(email: string): Locator {
    return this.rows().filter({ hasText: email });
  }

  async showView(label: 'Active' | 'Awaiting payment' | 'Refunded' | 'Cancelled' | 'All'): Promise<void> {
    await this.drawer().getByTestId('product-purchases-view').getByText(label, { exact: true }).click();
  }

  async search(text: string): Promise<void> {
    await this.drawer().getByLabel('Search purchases').fill(text);
  }

  async exportFromDrawer(): Promise<Download> {
    return this.download(() => this.drawer().getByTestId('product-purchases-export-button').click());
  }

  async exportActivePurchasesForAllProducts(): Promise<Download> {
    await this.page.getByTestId('product-purchases-export-all-button').click();
    return this.download(() => this.page.getByTestId('product-purchases-export-active-menu-item').click());
  }

  async cancelOrderFromRow(email: string): Promise<void> {
    await this.row(email).click();
    const orderDrawer = this.page.getByRole('dialog').filter({ hasText: 'Order Summary' });
    await orderDrawer.getByRole('button', { name: 'More actions' }).click();
    await this.page.getByRole('menuitem', { name: 'Cancel order' }).click();
    await this.page.getByRole('heading', { name: /^Cancel Order/ }).waitFor();
    await this.page.getByRole('button', { name: 'Cancel Order' }).click();
    await this.page.getByRole('heading', { name: /^Cancel Order/ }).waitFor({ state: 'hidden' });
    await orderDrawer.getByRole('button', { name: 'Close' }).click();
    await orderDrawer.waitFor({ state: 'hidden' });
  }

  private async download(trigger: () => Promise<void>): Promise<Download> {
    const [download] = await Promise.all([this.page.waitForEvent('download'), trigger()]);
    return download;
  }
}

export async function readCsv(download: Download): Promise<string[][]> {
  const contents = (await fs.promises.readFile(await download.path(), 'utf8')).replace(/^﻿/, '');
  return contents
    .trim()
    .split('\n')
    .map((line) => line.split(',').map((cell) => cell.replace(/^"|"$/g, '')));
}
