import type { Locator, Page } from '@playwright/test';

export class AccountEventsPage {
  constructor(private readonly page: Page) {}

  async goto(): Promise<void> {
    await this.page.goto('/manage/events');
  }

  row(title: string): Locator {
    return this.page.getByRole('row').filter({ hasText: title });
  }

  async filterByOrganizer(organizerName: string): Promise<void> {
    await this.page.getByRole('button', { name: 'Filters' }).click();
    const filters = this.page.getByRole('dialog');
    await filters.getByRole('combobox', { name: 'Organizer' }).click();
    await this.page.getByRole('option', { name: organizerName }).click();
    await this.page.keyboard.press('Escape');
    await filters.getByRole('button', { name: 'Apply' }).click();
  }

  async showStatus(status: 'Upcoming' | 'Ended' | 'Archived'): Promise<void> {
    await this.page.getByRole('radiogroup', { name: 'Filter by status' }).getByText(status, { exact: true }).click();
  }

  async openEvent(title: string): Promise<void> {
    await this.row(title).getByRole('link', { name: title }).click();
  }
}
