import type { Locator, Page } from '@playwright/test';

export class WebhookPage {
  constructor(private readonly page: Page) {}

  async goto(organizerId: number): Promise<void> {
    await this.page.goto(`/manage/organizer/${organizerId}/webhooks`);
    await this.page.waitForLoadState('networkidle');
  }

  async gotoEvent(eventId: number): Promise<void> {
    await this.page.goto(`/manage/event/${eventId}/webhooks`);
    await this.page.waitForLoadState('networkidle');
  }

  async createWebhook(url: string, eventType = 'order.created'): Promise<void> {
    await this.page.getByTestId('webhook-add-button').click();
    await this.page.getByRole('heading', { name: 'Create Webhook' }).waitFor();

    await this.page.getByLabel(/^Webhook URL/).fill(url);

    const eventTypesSelect = this.page.getByTestId('webhook-event-types');
    await eventTypesSelect.click();
    await this.page.getByTestId(`webhook-event-types-option-${eventType}`).click();
    await eventTypesSelect.click();

    await this.page.getByTestId('webhook-submit-button').click();

    await this.page.getByRole('heading', { name: 'Webhook Signing Secret' }).waitFor();
    await this.page.getByTestId('webhook-done-button').click();
  }

  async createEventWebhook(url: string, eventTypes: string[]): Promise<void> {
    await this.page.getByRole('button', { name: 'Add Webhook' }).first().click();
    await this.page.getByRole('heading', { name: 'Create Webhook' }).waitFor();

    await this.page.getByLabel(/^Webhook URL/).fill(url);

    const eventTypesSelect = this.page.getByTestId('webhook-event-types');
    await eventTypesSelect.click();
    for (const eventType of eventTypes) {
      await this.page.getByTestId(`webhook-event-types-option-${eventType}`).click();
    }
    await eventTypesSelect.click();

    await this.page.getByRole('button', { name: 'Create Webhook' }).click();

    await this.page.getByRole('heading', { name: 'Webhook Signing Secret' }).waitFor();
    await this.page.getByRole('button', { name: 'Done' }).click();
  }

  rowByUrl(url: string): Locator {
    return this.page.getByRole('row').filter({ hasText: url });
  }

  async chooseRowAction(url: string, action: string): Promise<void> {
    await this.rowByUrl(url).getByRole('button').click();
    await this.page.getByRole('menuitem', { name: action }).click();
  }

  editUrlInput(): Locator {
    return this.page.getByLabel(/^Webhook URL/);
  }

  async selectPausedStatus(): Promise<void> {
    await this.page.getByRole('dialog').getByText('Enabled', { exact: true }).click();
    await this.page.getByRole('option', { name: 'Paused' }).click();
  }

  async submitEditForm(): Promise<void> {
    await this.page.getByRole('button', { name: 'Edit Webhook' }).click();
  }

  async confirmDeletion(): Promise<void> {
    await this.page.getByRole('button', { name: 'Confirm' }).click();
  }
}
