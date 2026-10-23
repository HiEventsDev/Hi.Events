import type { FrameLocator, Locator, Page } from '@playwright/test';

export class DesignerPage {
  constructor(private readonly page: Page) {}

  async gotoHomepageDesigner(eventId: number): Promise<void> {
    await this.page.goto(`/manage/event/${eventId}/homepage-designer`);
    await this.page.waitForLoadState('networkidle');
  }

  async gotoOrganizerHomepageDesigner(organizerId: number): Promise<void> {
    await this.page.goto(`/manage/organizer/${organizerId}/organizer-homepage-designer`);
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

  layoutOption(layout: 'marquee' | 'flyer' | 'story' | 'invite'): Locator {
    return this.page.getByTestId(`homepage-layout-${layout}`);
  }

  sectionRow(type: string): Locator {
    return this.page.getByTestId(`homepage-section-row-${type}`);
  }

  async toggleSection(type: string): Promise<void> {
    await this.page.getByTestId(`homepage-section-toggle-${type}`).click();
  }

  async addSection(type: 'text' | 'gallery' | 'faq' | 'video' | 'sponsors'): Promise<Locator> {
    await this.page.getByTestId('homepage-add-section-button').click();
    await this.page.getByTestId(`homepage-add-section-${type}`).click();
    return this.sectionRow(type).last();
  }

  async moveSectionUp(type: string): Promise<void> {
    const grip = this.sectionRow(type).last().getByRole('button', { name: 'Drag to reorder' });
    await grip.focus();
    for (const key of ['Space', 'ArrowUp', 'Space']) {
      await this.page.keyboard.press(key);
      await this.page.waitForTimeout(250);
    }
  }
}
