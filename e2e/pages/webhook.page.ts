import type { Page } from '@playwright/test';

export class WebhookPage {
  constructor(private readonly page: Page) {}

  async goto(organizerId: number): Promise<void> {
    await this.page.goto(`/manage/organizer/${organizerId}/webhooks`);
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
}
