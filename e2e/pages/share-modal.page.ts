import type { Locator, Page } from '@playwright/test';

export class ShareModalPage {
  constructor(private readonly page: Page) {}

  async open(eventId: number): Promise<void> {
    await this.page.goto(`/manage/event/${eventId}/dashboard`);
    await this.page.getByRole('button', { name: 'Share Event' }).click();
    await this.dialog().waitFor();
  }

  dialog(): Locator {
    return this.page.getByRole('dialog', { name: 'Share Event' });
  }

  captionInput(): Locator {
    return this.page.getByTestId('share-caption-input');
  }

  linkInput(): Locator {
    return this.page.getByTestId('share-link-input');
  }

  previewCard(): Locator {
    return this.page.getByTestId('share-preview-card');
  }

  previewImage(): Locator {
    return this.page.getByTestId('share-preview-image');
  }

  addCoverPrompt(): Locator {
    return this.page.getByTestId('share-add-cover-prompt');
  }

  suggestCaptionsButton(): Locator {
    return this.page.getByTestId('share-suggest-captions-button');
  }

  suggestion(tone: 'friendly' | 'professional' | 'energetic' | 'short'): Locator {
    return this.page.getByTestId(`share-suggestion-${tone}`);
  }

  async stubSuggestions(body: Record<string, unknown>): Promise<void> {
    await this.page.route(/\/events\/\d+\/share-suggestions$/, (route) => route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ data: body }),
    }));
  }
}
