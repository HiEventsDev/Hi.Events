import type { Locator, Page } from '@playwright/test';

export class EmailTemplatePage {
  constructor(private readonly page: Page) {}

  async gotoEventTemplates(eventId: number): Promise<void> {
    await this.page.goto(`/manage/event/${eventId}/settings#email-settings`);
    await this.page.waitForLoadState('networkidle');
  }

  async gotoOrganizerTemplates(organizerId: number): Promise<void> {
    await this.page.goto(`/manage/organizer/${organizerId}/settings#email-templates`);
    await this.page.waitForLoadState('networkidle');
  }

  templateCard(typeLabel: string): Locator {
    return this.page
      .locator('.mantine-Paper-root')
      .filter({ has: this.page.getByText(typeLabel, { exact: true }) });
  }

  async openCreateEditor(typeLabel: string): Promise<void> {
    await this.templateCard(typeLabel).getByTestId('email-template-create-button').click();
  }

  async openEditEditor(typeLabel: string): Promise<void> {
    await this.templateCard(typeLabel).getByRole('button').first().click();
  }

  async deleteTemplate(typeLabel: string): Promise<void> {
    await this.templateCard(typeLabel).getByRole('button').nth(1).click();
    await this.page.getByRole('button', { name: 'Delete Template' }).click();
  }

  editorDialog(): Locator {
    return this.page.getByRole('dialog');
  }

  subjectInput(): Locator {
    return this.editorDialog().getByLabel(/^Subject/);
  }

  bodyEditor(): Locator {
    return this.editorDialog().locator('.ProseMirror');
  }

  async fillEditor(subject: string, body: string): Promise<void> {
    await this.subjectInput().fill(subject);
    await this.bodyEditor().click();
    await this.bodyEditor().fill(body);
  }

  async saveTemplate(): Promise<void> {
    await this.editorDialog().getByRole('button', { name: 'Save Template' }).click();
  }

  async openPreviewTab(): Promise<void> {
    await this.editorDialog().getByRole('tab', { name: 'Preview' }).click();
  }
}
