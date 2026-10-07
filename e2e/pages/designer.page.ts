import type { FrameLocator, Locator, Page } from '@playwright/test';

export class DesignerPage {
  constructor(private readonly page: Page) {}

  async gotoHomepageDesigner(eventId: number): Promise<void> {
    await this.page.goto(`/manage/event/${eventId}/homepage-designer`);
    await this.page.waitForLoadState('networkidle');
  }

  async gotoTicketDesigner(eventId: number): Promise<void> {
    await this.page.goto(`/manage/event/${eventId}/ticket-designer`);
    await this.page.waitForLoadState('networkidle');
  }

  get continueButtonInput(): Locator {
    return this.page.getByLabel('Continue Button');
  }

  get footerTextInput(): Locator {
    return this.page.getByLabel('Footer Text');
  }

  get saveButton(): Locator {
    return this.page.getByTestId('designer-save-button');
  }

  get discardButton(): Locator {
    return this.page.getByTestId('designer-discard-button');
  }

  get unsavedStatus(): Locator {
    return this.page.getByText('Unsaved changes');
  }

  get savedStatus(): Locator {
    return this.page.getByText('All changes saved');
  }

  get eventPreview(): FrameLocator {
    return this.page.frameLocator('iframe[title="Event Preview"]');
  }
}
