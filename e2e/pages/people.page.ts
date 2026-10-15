import type { Locator, Page } from '@playwright/test';

export class PeoplePage {
  constructor(private readonly page: Page) {}

  async goto(organizerId: number): Promise<void> {
    await this.page.goto(`/manage/organizer/${organizerId}/people`);
    await this.page.waitForLoadState('networkidle');
  }

  row(name: string): Locator {
    return this.page.locator('[data-testid^="people-row-"]').filter({ hasText: name });
  }

  async createProfile(details: { name: string; tagline: string }): Promise<void> {
    await this.page.getByTestId('people-create-button').click();
    const drawer = this.page.getByRole('dialog').filter({ has: this.page.getByTestId('lineup-profile-save-button') });
    await drawer.getByLabel(/^Name/).fill(details.name);
    await drawer.getByLabel(/^Tagline/).fill(details.tagline);
    await this.page.getByTestId('lineup-profile-save-button').click();
  }
}
