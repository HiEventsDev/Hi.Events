import type { Locator, Page } from '@playwright/test';

export class EventReportPage {
  constructor(private readonly page: Page) {}

  async goto(eventId: number, reportType: string): Promise<void> {
    await this.page.goto(`/manage/event/${eventId}/report/${reportType}`);
    await this.page.waitForLoadState('networkidle');
  }

  async chooseCustomRange(): Promise<void> {
    await this.page.getByTestId('report-period-select').click();
    await this.page.getByRole('option', { name: 'Custom Range' }).click();
  }

  get dateRangePicker(): Locator {
    return this.page.getByTestId('report-date-range-picker');
  }

  async openDateRangePicker(): Promise<Locator> {
    await this.dateRangePicker.click();
    const dropdown = this.page.getByRole('dialog');
    await dropdown.waitFor();
    return dropdown;
  }
}
