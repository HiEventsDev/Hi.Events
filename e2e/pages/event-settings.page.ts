import type { Locator, Page } from '@playwright/test';

export class EventSettingsPage {
  constructor(private readonly page: Page) {}

  async goto(eventId: number): Promise<void> {
    await this.page.goto(`/manage/event/${eventId}/settings`);
    await this.page.waitForLoadState('networkidle');
  }

  section(id: string): Locator {
    return this.page.locator(`#${id}`);
  }

  get detailsNameInput(): Locator {
    return this.section('event-details').getByLabel(/^Name/);
  }

  get detailsStartDate(): Locator {
    return this.section('event-details').getByLabel(/^Start Date/);
  }

  get detailsEndDate(): Locator {
    return this.section('event-details').getByLabel(/^End Date/);
  }

  get detailsDescriptionEditor(): Locator {
    return this.section('event-details').locator('[contenteditable="true"]');
  }

  get seoTitleInput(): Locator {
    return this.section('seo-settings').getByLabel(/^SEO Title/);
  }

  get selfServiceSwitch(): Locator {
    return this.section('misc-settings').getByLabel('Enable attendee self-service');
  }

  get offlinePaymentsCheckbox(): Locator {
    return this.section('payment-settings').getByLabel('Offline Payments', { exact: true });
  }

  get offlineInstructionsEditor(): Locator {
    return this.section('payment-settings').locator('[contenteditable="true"]');
  }

  get deleteConfirmationInput(): Locator {
    return this.section('danger-zone').getByPlaceholder('delete');
  }

  get eventTypeChangeButton(): Locator {
    return this.page.getByTestId('event-type-change-button');
  }

  async changeEventType(confirmLabel: string): Promise<void> {
    await this.eventTypeChangeButton.click();
    await this.page.getByRole('dialog').getByRole('button', { name: confirmLabel }).click();
  }

  get deleteButton(): Locator {
    return this.page.getByTestId('event-delete-button');
  }

  async saveSection(id: string): Promise<void> {
    await this.section(id).getByRole('button', { name: 'Save' }).click();
  }
}
