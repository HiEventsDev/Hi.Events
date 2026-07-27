import type { Locator, Page } from '@playwright/test';

export class OccurrencePage {
  constructor(private readonly page: Page) {}

  async goto(eventId: number): Promise<void> {
    await this.page.goto(`/manage/event/${eventId}/occurrences`);
    await this.page.waitForLoadState('networkidle');
  }

  dialog(): Locator {
    return this.page.getByRole('dialog');
  }

  async openScheduleSetup(): Promise<void> {
    await this.page.getByRole('button', { name: 'Set Up Schedule' }).click();
  }

  async pickWeekday(label: string): Promise<void> {
    await this.dialog().getByRole('checkbox', { name: label, exact: true }).check({ force: true });
  }

  async chooseFixedNumberOfDates(count: number): Promise<void> {
    await this.dialog().getByText('Set number of dates').click();
    await this.dialog().getByLabel(/^Number of dates to create/).fill(String(count));
  }

  async submitSchedule(): Promise<void> {
    await this.dialog().getByRole('button', { name: 'Create Schedule' }).click();
  }

  occurrenceRows(): Locator {
    return this.page.getByRole('row').filter({ has: this.page.getByTestId('occurrence-actions-menu') });
  }

  statusBadges(status: 'ACTIVE' | 'CANCELLED'): Locator {
    return this.page.locator(`[class*="statusBadge"][data-status="${status}"]`);
  }

  rowWithStatus(status: 'ACTIVE' | 'CANCELLED'): Locator {
    return this.occurrenceRows().filter({ has: this.statusBadges(status) });
  }

  async chooseRowAction(row: Locator, action: string): Promise<void> {
    await row.getByTestId('occurrence-actions-menu').click();
    await this.page.getByRole('menuitem', { name: action, exact: true }).click();
  }

  async confirmModalAction(buttonName: string): Promise<void> {
    await this.dialog().getByRole('button', { name: buttonName }).click();
  }

  async openProductsTab(): Promise<void> {
    await this.page.getByRole('tab', { name: 'Products' }).click();
  }

  productCard(title: string): Locator {
    return this.page.locator('[class*="productCard"]').filter({ hasText: title });
  }

  productSwitch(title: string): Locator {
    return this.productCard(title).getByRole('switch');
  }

  overrideInput(): Locator {
    return this.page.locator('[class*="overrideInput"] input');
  }

  async saveProductSettings(): Promise<void> {
    await this.page.locator('[class*="saveButton"]').click();
  }

  async closeModal(): Promise<void> {
    await this.page.keyboard.press('Escape');
  }
}

export class PublicOccurrenceSelector {
  constructor(private readonly page: Page) {}

  calendar(): Locator {
    return this.page.locator('.hi-occurrence-datepicker');
  }

  dayButton(label: RegExp): Locator {
    return this.page.getByRole('button', { name: label });
  }

  nextMonthButton(): Locator {
    return this.page.locator('.hi-dp-nav[data-direction="next"]');
  }

  previousMonthButton(): Locator {
    return this.page.locator('.hi-dp-nav[data-direction="previous"]');
  }

  slotHeaderDay(): Locator {
    return this.page.locator('.hi-slot-header-day');
  }

  monthHeader(): Locator {
    return this.page.locator('.hi-dp-level');
  }

  productsLoadingOverlay(): Locator {
    return this.page.locator('.hi-occurrence-loading-overlay');
  }

  monthLoadingOverlay(): Locator {
    return this.page.locator('.hi-calendar-month-loading');
  }

  async waitForMonthLoaded(): Promise<void> {
    await this.monthLoadingOverlay().waitFor({ state: 'visible', timeout: 300 }).catch(() => {});
    await this.monthLoadingOverlay().waitFor({ state: 'detached' });
  }

  async navigateToMonthOf(isoDate: string): Promise<void> {
    const target = new Date(isoDate);
    const targetIndex = target.getUTCFullYear() * 12 + target.getUTCMonth();
    await this.calendar().waitFor();
    await this.waitForMonthLoaded();
    for (let attempt = 0; attempt < 24; attempt++) {
      const header = (await this.monthHeader().innerText()).trim();
      const displayed = new Date(header.replace(' ', ' 1, '));
      const displayedIndex = displayed.getFullYear() * 12 + displayed.getMonth();
      if (displayedIndex === targetIndex) return;
      if (displayedIndex < targetIndex) {
        await this.nextMonthButton().click();
      } else {
        await this.previousMonthButton().click();
      }
      await this.waitForMonthLoaded();
    }
    throw new Error(`Could not navigate the occurrence calendar to the month of ${isoDate}`);
  }

  async selectDay(label: RegExp): Promise<void> {
    await this.calendar().waitFor();
    await this.waitForMonthLoaded();
    for (let attempt = 0; attempt < 2 && (await this.dayButton(label).count()) === 0; attempt++) {
      await this.nextMonthButton().click();
      await this.waitForMonthLoaded();
    }
    await this.dayButton(label).click();
  }
}
