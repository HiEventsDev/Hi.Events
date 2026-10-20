import type { Download, Locator, Page } from '@playwright/test';
import { pickDate } from '../utils/datePicker';

export class LineupPage {
  constructor(private readonly page: Page) {}

  async goto(eventId: number): Promise<void> {
    await this.page.goto(`/manage/event/${eventId}/lineup`);
    await this.page.waitForLoadState('networkidle');
  }

  drawer(): Locator {
    return this.page.getByRole('dialog').filter({ has: this.page.getByTestId('lineup-profile-save-button') });
  }

  person(name: string): Locator {
    return this.page.getByTestId('lineup-people-list').getByRole('listitem').filter({ hasText: name });
  }

  async addNewPerson(name: string): Promise<void> {
    await this.page.getByTestId('lineup-quick-add-input').fill(name);
    await this.page.getByTestId('lineup-quick-add-create').click();
  }

  async addSavedPerson(name: string): Promise<void> {
    await this.page.getByTestId('lineup-quick-add-input').fill(name);
    await this.page.getByRole('option', { name: new RegExp(name) }).click();
  }

  async fillEventDetails(details: { billing: 'Headliner' | 'Featured' | 'Standard'; role: string }): Promise<void> {
    const drawer = this.drawer();
    await drawer.getByText(details.billing, { exact: true }).click();
    await drawer.getByLabel(/^Role/).fill(details.role);
  }

  async fillProfile(details: { tagline: string; link: string }): Promise<void> {
    const drawer = this.drawer();
    await drawer.getByLabel(/^Tagline/).fill(details.tagline);
    await drawer.getByLabel('Link 1', { exact: true }).fill(details.link);
  }

  async uploadPhoto(filePath: string): Promise<void> {
    await this.drawer().locator('input[type="file"]').setInputFiles(filePath);
  }

  async save(): Promise<void> {
    await this.page.getByTestId('lineup-profile-save-button').click();
  }

  async openView(view: 'Preview' | 'List' | 'Timetable'): Promise<void> {
    await this.page.getByTestId('lineup-view-switch').getByText(view, { exact: true }).click();
  }

  async stepTimetable(direction: 'Next' | 'Previous'): Promise<void> {
    await this.page.getByRole('button', { name: `${direction} day`, exact: true }).click();
  }

  async addStage(name: string): Promise<void> {
    await this.page.getByTestId('lineup-add-space-button').click();
    const dialog = this.page.getByRole('dialog');
    await dialog.getByLabel(/^Name/).fill(name);
    await this.page.getByTestId('lineup-space-save-button').click();
  }

  async addTimeSlot(details: { person: string; date: string; starts: string; ends: string }): Promise<void> {
    await this.page.getByTestId('lineup-add-slot-button').click();
    const dialog = this.page.getByRole('dialog');
    await this.page.getByTestId('lineup-slot-people').click();
    await this.page.getByRole('option', { name: details.person }).click();
    await pickDate(this.page, dialog.getByRole('button', { name: /^Date/ }), details.date);
    await dialog.getByLabel('Starts').fill(details.starts);
    await dialog.getByLabel('Ends').fill(details.ends);
    await this.page.getByTestId('lineup-slot-save-button').click();
  }

  importDialog(): Locator {
    return this.page.getByRole('dialog', { name: 'Import lineup & schedule' });
  }

  async openImport(): Promise<void> {
    await this.page.getByTestId('lineup-import-button').click();
    await this.importDialog().getByTestId('lineup-import-dropzone').waitFor();
  }

  async pasteRows(rows: string[][]): Promise<void> {
    await this.page.evaluate((text) => {
      const data = new DataTransfer();
      data.setData('text/plain', text);
      document.dispatchEvent(new ClipboardEvent('paste', { clipboardData: data, bubbles: true }));
    }, rows.map(cells => cells.join('\t')).join('\n'));
  }

  summaryValue(card: 'people' | 'slots' | 'stages' | 'errors'): Locator {
    return this.importDialog().getByTestId(`lineup-import-${card}`).locator('strong');
  }

  importRow(text: string): Locator {
    return this.importDialog().getByTestId('lineup-import-rows').getByRole('listitem').filter({ hasText: text });
  }

  async importFile(path: string): Promise<void> {
    await this.page.getByTestId('lineup-import-button').click();
    await this.importDialog().locator('input[type="file"]').setInputFiles(path);
    await this.importDialog().getByTestId('lineup-import-summary').waitFor();
  }

  async exportSpreadsheet(): Promise<Download> {
    await this.page.getByTestId('lineup-export-button').click();
    const [download] = await Promise.all([
      this.page.waitForEvent('download'),
      this.page.getByTestId('lineup-export-xlsx').click(),
    ]);
    return download;
  }
}
