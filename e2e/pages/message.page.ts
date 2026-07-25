import type { Locator, Page } from '@playwright/test';

export class MessagePage {
  constructor(private readonly page: Page) {}

  async goto(eventId: number): Promise<void> {
    await this.page.goto(`/manage/event/${eventId}/messages`);
    await this.page.waitForLoadState('networkidle');
  }

  async sendToAllAttendees(subject: string, body: string): Promise<void> {
    await this.openComposer();
    await this.fillComposerForAllAttendees(subject, body);
    await this.confirmAndSubmit();
  }

  async scheduleToAllAttendees(subject: string, body: string, scheduledAt: string): Promise<void> {
    await this.openComposer();
    await this.fillComposerForAllAttendees(subject, body);
    await this.page.getByRole('button', { name: 'Schedule for later' }).click();
    await this.page.getByRole('button', { name: 'Custom date and time' }).click();
    await this.page.getByLabel(/^Scheduled time/).fill(scheduledAt);
    await this.confirmAndSubmit();
  }

  listItem(subject: string): Locator {
    return this.page.getByRole('option').filter({ hasText: subject });
  }

  async openSentMessageRecipients(): Promise<void> {
    await this.page.getByRole('button', { name: 'All attendees', exact: true }).click();
    await this.page.getByRole('heading', { name: 'Recipients' }).waitFor();
  }

  async cancelScheduledMessage(): Promise<void> {
    this.page.once('dialog', (dialog) => dialog.accept());
    await this.page.getByRole('button', { name: 'Cancel', exact: true }).click();
  }

  private async openComposer(): Promise<void> {
    await this.page.getByTestId('message-compose-button').click();
    await this.page.getByRole('heading', { name: 'Send a message' }).waitFor();
  }

  private async fillComposerForAllAttendees(subject: string, body: string): Promise<void> {
    await this.page.getByRole('combobox', { name: 'Recipients' }).click();
    await this.page.getByRole('option', { name: 'All attendees of this event' }).click();

    await this.page.getByLabel(/^Subject/).fill(subject);

    const editor = this.page.locator('.ProseMirror').first();
    await editor.click();
    await editor.fill(body);
  }

  private async confirmAndSubmit(): Promise<void> {
    await this.page.getByRole('checkbox', { name: /I confirm this is a transactional message/ }).check();
    await this.page.getByTestId('message-send-button').click();
  }
}
