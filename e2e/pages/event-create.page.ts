import type { Page } from '@playwright/test';

export class EventCreatePage {
  constructor(private readonly page: Page) {}

  async gotoDashboard(): Promise<void> {
    await this.page.goto('/manage/events');
    await this.page.waitForLoadState('networkidle');
  }

  async openCreateModal(): Promise<void> {
    const createNewMenu = this.page.getByRole('button', { name: 'Create new' });
    const blankSlateButton = this.page.getByRole('button', { name: /^Create event$/i });
    const eventMenuItem = this.page.getByRole('menuitem', { name: 'Event' });
    const eventNameField = this.page.getByLabel(/^Event Name/);

    await createNewMenu.or(blankSlateButton).first().click();
    await eventMenuItem.or(eventNameField).first().waitFor({ state: 'visible' });
    if (await eventMenuItem.isVisible()) {
      await eventMenuItem.click();
    }
    await eventNameField.waitFor({ state: 'visible' });
  }

  async createSingleEvent(details: { title: string; category?: string }): Promise<void> {
    const { title, category = 'Music' } = details;
    await this.page.getByLabel(/^Event Name/).fill(title);

    await this.page.getByRole('combobox', { name: 'Event Category' }).click();
    await this.page.getByRole('option', { name: new RegExp(category) }).click();

    await this.page.getByRole('button', { name: 'Continue Setup' }).click();
    await this.page.waitForURL(/\/manage\/event\/\d+\/dashboard/);
  }
}
