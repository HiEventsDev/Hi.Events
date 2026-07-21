import type { Page } from '@playwright/test';

export class MessagePage {
  constructor(private readonly page: Page) {}

  async goto(eventId: number): Promise<void> {
    await this.page.goto(`/manage/event/${eventId}/messages`);
    await this.page.waitForLoadState('networkidle');
  }

  async sendToAllAttendees(subject: string, body: string): Promise<void> {
    await this.page.getByTestId('message-compose-button').click();
    await this.page.getByRole('heading', { name: 'Send a message' }).waitFor();

    await this.page.getByRole('combobox', { name: 'Recipients' }).click();
    await this.page.getByRole('option', { name: 'All attendees of this event' }).click();

    await this.page.getByLabel(/^Subject/).fill(subject);

    const editor = this.page.locator('.ProseMirror').first();
    await editor.click();
    await editor.fill(body);

    await this.page.getByRole('checkbox', { name: /I confirm this is a transactional message/ }).check();
    await this.page.getByTestId('message-send-button').click();
  }
}
