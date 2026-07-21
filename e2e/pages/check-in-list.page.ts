import type { Page } from '@playwright/test';

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
}
