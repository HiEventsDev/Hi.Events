import type { Locator, Page } from '@playwright/test';

export class AttendeePage {
  constructor(private readonly page: Page) {}

  async goto(eventId: number): Promise<void> {
    await this.page.goto(`/manage/event/${eventId}/attendees`);
    await this.page.waitForLoadState('networkidle');
  }

  rowByText(text: string): Locator {
    return this.page.getByRole('row').filter({ hasText: text });
  }

  async createAttendee(details: {
    firstName: string;
    lastName: string;
    email: string;
    productTitle: string;
  }): Promise<void> {
    await this.page.getByTestId('attendee-create-button').click();
    await this.page.getByRole('heading', { name: 'Manually Add Attendee' }).waitFor();
    await this.page.getByLabel(/^First name/).fill(details.firstName);
    await this.page.getByLabel(/^Last name/).fill(details.lastName);
    await this.page.getByLabel(/^Email address/).fill(details.email);
    await this.page.getByRole('combobox', { name: /^Ticket/ }).click();
    await this.page.getByRole('option', { name: details.productTitle }).click();
    await this.page.getByRole('button', { name: 'Create Attendee' }).click();
  }

  async openRowAction(rowText: string, action: string): Promise<void> {
    await this.rowByText(rowText).getByTestId('attendee-actions-trigger').click();
    await this.page.getByRole('menuitem', { name: action }).click();
  }

  editButton(): Locator {
    return this.page.getByTestId('attendee-edit-button');
  }

  async renameFirstName(firstName: string): Promise<void> {
    await this.editButton().click();
    await this.page.getByLabel(/^First name/).fill(firstName);
    await this.page.getByRole('button', { name: 'Save Changes' }).click();
  }

  async closeDrawer(): Promise<void> {
    await this.page.keyboard.press('Escape');
  }

  async clickExport(): Promise<void> {
    await this.page.getByRole('button', { name: 'Export' }).click();
  }
}
