import type { Locator, Page } from '@playwright/test';

export class CheckInListPage {
  constructor(private readonly page: Page) {}

  async goto(eventId: number): Promise<void> {
    await this.page.goto(`/manage/event/${eventId}/check-in`);
    await this.page.waitForLoadState('networkidle');
  }

  async createList(name: string): Promise<void> {
    await this.page.getByTestId('checkin-list-create-button').click();
    await this.page.getByLabel(/^Name/).fill(name);
    await this.page.getByTestId('checkin-list-submit-button').click();

    await this.page.getByRole('heading', { name: 'Check-In List Created' }).waitFor();
    await this.page.getByRole('button', { name: 'Done' }).click();
  }

  row(name: string): Locator {
    return this.page.getByRole('row').filter({ hasText: name });
  }

  async openRowAction(name: string, action: string): Promise<void> {
    await this.row(name).getByTestId('check-in-list-actions-menu').click();
    await this.page.getByRole('menuitem', { name: action }).click();
  }

  openCheckInButton(name: string): Locator {
    return this.row(name).getByTestId('check-in-list-open-button');
  }

  editNameInput(): Locator {
    return this.page.getByRole('dialog').getByLabel(/^Name/);
  }

  async submitEdit(): Promise<void> {
    await this.page.getByRole('dialog').getByRole('button', { name: 'Edit Check-In List' }).click();
  }
}
