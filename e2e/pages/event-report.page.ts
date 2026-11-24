import { expect, type Locator, type Page } from '@playwright/test';

export class ReportPage {
  constructor(protected readonly page: Page) {}

  tab(reportType: string): Locator {
    return this.page.getByTestId(`report-tab-${reportType}`);
  }

  get periodButton(): Locator {
    return this.page.getByTestId('report-period-select');
  }

  async choosePeriod(label: string): Promise<void> {
    await this.periodButton.click();
    await this.page.getByRole('menuitem', { name: label, exact: true }).click();
  }

  async chooseCustomRange(): Promise<void> {
    await this.choosePeriod('Custom range');
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

  get rows(): Locator {
    return this.page.getByTestId('report-table').locator('tbody tr');
  }

  row(text: string): Locator {
    return this.rows.filter({ hasText: text });
  }

  get totalsRow(): Locator {
    return this.page.getByTestId('report-table').locator('tfoot tr');
  }

  summaryValue(label: string): Locator {
    return this.page.getByTestId('report-summary').locator('div').filter({ hasText: label }).locator('dd');
  }

  columnHeader(label: string): Locator {
    return this.page.getByTestId('report-table').locator('thead').getByRole('button').filter({ hasText: label });
  }

  get exportCsvButton(): Locator {
    return this.page.getByRole('button', { name: 'Export CSV' });
  }

  async showDaysWithNoSales(): Promise<void> {
    await this.page.getByRole('switch', { name: 'Show days with no sales' }).dispatchEvent('click');
  }

  async reloadUntilRowAppears(text: string): Promise<void> {
    await expect(async () => {
      await this.page.reload();
      await expect(this.row(text)).toBeVisible({ timeout: 4_000 });
    }).toPass({ timeout: 60_000 });
  }
}

export class EventReportPage extends ReportPage {
  async goto(eventId: number, reportType: string): Promise<void> {
    await this.page.goto(`/manage/event/${eventId}/report/${reportType}`);
    await this.page.waitForLoadState('networkidle');
  }

  async gotoIndex(eventId: number): Promise<void> {
    await this.page.goto(`/manage/event/${eventId}/reports`);
    await this.page.waitForLoadState('networkidle');
  }
}

export class OrganizerReportPage extends ReportPage {
  async goto(organizerId: number, reportType: string): Promise<void> {
    await this.page.goto(`/manage/organizer/${organizerId}/report/${reportType}`);
    await this.page.waitForLoadState('networkidle');
  }
}
