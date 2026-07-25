import type { Locator, Page } from '@playwright/test';

export class OrganizerPage {
  constructor(private readonly page: Page) {}

  async gotoEventsDashboard(): Promise<void> {
    await this.page.goto('/manage/events');
    await this.page.waitForLoadState('networkidle');
  }

  async gotoSettings(organizerId: number): Promise<void> {
    await this.page.goto(`/manage/organizer/${organizerId}/settings`);
    await this.page.waitForLoadState('networkidle');
  }

  async gotoReport(organizerId: number, reportType: string): Promise<void> {
    await this.page.goto(`/manage/organizer/${organizerId}/report/${reportType}`);
    await this.page.waitForLoadState('networkidle');
  }

  async openCreateOrganizerModal(avatarInitials: string): Promise<void> {
    await this.page.getByRole('button', { name: avatarInitials, exact: true }).click();
    await this.page.getByRole('menuitem', { name: 'Create Organizer' }).click();
  }

  get organizationNameInput(): Locator {
    return this.page.getByLabel(/^Organization Name/);
  }

  get contactEmailInput(): Locator {
    return this.page.getByLabel(/^Contact Email/);
  }

  get continueSetupButton(): Locator {
    return this.page.getByRole('button', { name: /^Continue to event creation$/ });
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
