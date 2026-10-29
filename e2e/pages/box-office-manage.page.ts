import type { Locator, Page } from '@playwright/test';

export class BoxOfficeManagePage {
  constructor(private readonly page: Page) {}

  async goto(eventId: number): Promise<void> {
    await this.page.goto(`/manage/event/${eventId}/box-office`);
    await this.page.waitForLoadState('networkidle');
  }

  async create(name: string): Promise<void> {
    await this.page.getByTestId('box-office-create-button').click();
    await this.page.getByRole('dialog').getByLabel(/^Name/).fill(name);
    await this.page.getByTestId('box-office-submit-button').click();
  }

  pinValue(): Locator {
    return this.page.getByTestId('box-office-pin-value');
  }

  async dismissPinModal(): Promise<void> {
    await this.page.getByTestId('box-office-pin-done-button').click();
    await this.page.getByRole('dialog').waitFor({ state: 'hidden' });
  }

  row(name: string): Locator {
    return this.page.getByRole('row').filter({ hasText: name });
  }

  async openRowAction(name: string, action: string): Promise<void> {
    await this.row(name).getByTestId('box-office-actions-menu').click();
    await this.page.getByRole('menuitem', { name: action }).click();
  }

  editNameInput(): Locator {
    return this.page.getByRole('dialog').getByLabel(/^Name/);
  }

  async submitEdit(): Promise<void> {
    await this.page.getByTestId('box-office-submit-button').click();
    await this.page.getByRole('dialog').waitFor({ state: 'hidden' });
  }

  openDefaultButton(): Locator {
    return this.page.getByTestId('box-office-open-default-button');
  }

  async openSalesSummary(name: string): Promise<void> {
    await this.row(name).getByTestId('box-office-actions-menu').click();
    await this.page.getByTestId('box-office-stats-menu-item').click();
    await this.statsModal().waitFor();
  }

  statsModal(): Locator {
    return this.page.getByTestId('box-office-stats-modal');
  }

  statsOrders(): Locator {
    return this.page.getByTestId('box-office-stats-orders');
  }

  statsGross(): Locator {
    return this.page.getByTestId('box-office-stats-gross');
  }

  statsRefunded(): Locator {
    return this.page.getByTestId('box-office-stats-refunded');
  }

  statsRow(label: string): Locator {
    return this.statsModal().getByRole('row').filter({ has: this.page.getByRole('cell', { name: label, exact: true }) });
  }
}
