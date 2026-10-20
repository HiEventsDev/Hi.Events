import { expect, type Locator, type Page } from '@playwright/test';

export class OrganizerPage {
  constructor(private readonly page: Page) {}

  async gotoEventsDashboard(): Promise<void> {
    await this.page.goto('/manage/events');
    await this.page.waitForLoadState('networkidle');
  }

  async searchEvents(organizerId: number, title: string): Promise<void> {
    await this.page.goto(`/manage/organizer/${organizerId}/events`);
    await this.page.waitForLoadState('networkidle');
    await this.page.getByPlaceholder('Search by event name...').fill(title);
    await this.page.waitForURL(/query=/);
    await this.page.waitForLoadState('networkidle');
  }

  eventCard(title: string): Locator {
    return this.page.locator('a').filter({ has: this.page.getByRole('heading', { name: title }) });
  }

  async openEventDuplicateModal(title: string): Promise<void> {
    const duplicateItem = this.page.getByTestId('event-duplicate-menu-item');
    await expect(async () => {
      if (!(await duplicateItem.isVisible())) {
        await this.eventCard(title).getByRole('button').click();
      }
      await duplicateItem.click({ timeout: 2_000 });
      await expect(this.page.getByRole('dialog').getByLabel(/^Name/)).toBeVisible({ timeout: 2_000 });
    }).toPass({ timeout: 20_000 });
  }

  async gotoSettings(organizerId: number): Promise<void> {
    await this.page.goto(`/manage/organizer/${organizerId}/settings`);
    await this.page.waitForLoadState('networkidle');
  }

  async gotoReport(organizerId: number, reportType: string): Promise<void> {
    await this.page.goto(`/manage/organizer/${organizerId}/report/${reportType}`);
    await this.page.waitForLoadState('networkidle');
  }

  async openCreateOrganizerModal(): Promise<void> {
    await this.page.getByTestId('account-menu-button').click();
    await this.page.getByRole('menuitem', { name: 'Create Organizer' }).click();
  }

  get organizerNameInput(): Locator {
    return this.page.getByRole('dialog').getByLabel(/^Organizer name/);
  }

  get contactEmailInput(): Locator {
    return this.page.getByRole('dialog').getByLabel(/^Contact email/);
  }

  get createOrganizerButton(): Locator {
    return this.page.getByTestId('create-organizer-submit');
  }

  get settingsNameInput(): Locator {
    return this.page.locator('#basic-settings').getByLabel(/^Organizer Name/);
  }

  async saveBasicSettings(): Promise<void> {
    await this.page.locator('#basic-settings').getByRole('button', { name: 'Save' }).click();
  }

  reportRow(text: string): Locator {
    return this.page.getByRole('row').filter({ hasText: text });
  }

  get exportCsvButton(): Locator {
    return this.page.getByRole('button', { name: 'Export CSV' });
  }
}

export class OrganizerPublicPage {
  constructor(private readonly page: Page) {}

  async goto(organizerId: number, slug: string): Promise<void> {
    await this.page.goto(`/events/${organizerId}/${slug}`);
    await this.page.waitForLoadState('networkidle');
  }

  eventLink(title: string): Locator {
    return this.page.getByRole('link').filter({ has: this.page.getByRole('heading', { name: title }) });
  }

  get contactButton(): Locator {
    return this.page.getByRole('button', { name: 'Contact', exact: true });
  }

  async sendContactMessage(details: { name: string; email: string; message: string }): Promise<void> {
    await this.page.getByLabel(/^Your Name/).fill(details.name);
    await this.page.getByLabel(/^Your Email/).fill(details.email);
    await this.page.getByLabel(/^Message/).fill(details.message);
    await this.page.getByRole('button', { name: 'Send Message' }).click();
  }
}
