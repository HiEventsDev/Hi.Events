import type { Locator, Page } from '@playwright/test';

export class EventCreatePage {
  constructor(private readonly page: Page) {}

  get titleInput(): Locator {
    return this.page.getByTestId('create-event-title-input');
  }

  get submitButton(): Locator {
    return this.page.getByTestId('create-event-submit-button');
  }

  chip(name: 'start' | 'end' | 'repeat' | 'timezone' | 'currency' | 'category'): Locator {
    return this.page.getByTestId(`create-event-${name}-chip`);
  }

  async gotoDashboard(): Promise<void> {
    await this.page.goto('/manage/events');
    await this.page.waitForLoadState('networkidle');
  }

  async openCreateModal(): Promise<void> {
    const createNewMenu = this.page.getByTestId('create-new-menu-button');
    const blankSlateButton = this.page.getByTestId('create-event-blank-slate-button');
    const eventMenuItem = this.page.getByTestId('create-event-menu-item');

    await createNewMenu.or(blankSlateButton).first().click();
    await eventMenuItem.or(this.titleInput).first().waitFor({ state: 'visible' });
    if (await eventMenuItem.isVisible()) {
      await eventMenuItem.click();
    }
    await this.titleInput.waitFor({ state: 'visible' });
  }

  async selectOption(chip: 'repeat' | 'timezone' | 'currency' | 'category', value: string, search?: string): Promise<void> {
    await this.chip(chip).click();
    if (search) {
      await this.page.getByRole('textbox', { name: 'Search' }).fill(search);
    }
    await this.page.getByTestId(`create-event-${chip}-chip-option-${value}`).click();
  }

  async pickDate(chip: 'start' | 'end', options: { nextMonth?: boolean; day: number }): Promise<void> {
    await this.chip(chip).click();
    const popover = this.page.getByTestId(`create-event-${chip}-chip-popover`);
    await popover.waitFor({ state: 'visible' });
    if (options.nextMonth) {
      await popover.locator('[data-direction="next"]').first().click();
    }
    await popover
      .locator('table button:not([data-outside])')
      .filter({ hasText: new RegExp(`^${options.day}$`) })
      .click();
  }

  async setTime(chip: 'start' | 'end', time: { hours: string; minutes: string; amPm?: 'AM' | 'PM' }): Promise<void> {
    const popover = this.page.getByTestId(`create-event-${chip}-chip-popover`);
    await popover.getByLabel('Hours').click();
    await this.page.keyboard.type(time.hours);
    await popover.getByLabel('Minutes').click();
    await this.page.keyboard.type(time.minutes);
    if (time.amPm) {
      await popover.getByLabel('AM/PM').click();
      await this.page.keyboard.type(time.amPm);
    }
  }

  async closePopover(): Promise<void> {
    await this.page.keyboard.press('Escape');
  }

  get descriptionEditor(): Locator {
    return this.page.getByTestId('create-event-description-input');
  }

  async addDescription(paragraphs: string[]): Promise<void> {
    await this.page.getByTestId('create-event-description-button').click();
    await this.descriptionEditor.click();
    for (const [index, paragraph] of paragraphs.entries()) {
      if (index > 0) {
        await this.page.keyboard.press('Enter');
      }
      await this.page.keyboard.type(paragraph);
    }
  }

  async boldParagraph(text: string): Promise<void> {
    await this.descriptionEditor.getByText(text).click({ clickCount: 3 });
    await this.floatingControl('Bold').click();
  }

  floatingControl(name: string): Locator {
    return this.page.getByRole('button', { name, exact: true }).filter({ visible: true });
  }

  async insertUploadedImage(filePath: string): Promise<void> {
    await this.floatingControl('Insert').click();
    await this.floatingControl('Insert image').click();
    const dialog = this.page.getByRole('dialog', { name: 'Insert Image' });
    await dialog.getByRole('tab', { name: 'Upload Image' }).click();
    const chooser = this.page.waitForEvent('filechooser');
    await dialog.getByRole('button', { name: 'Upload Image' }).click();
    await (await chooser).setFiles(filePath);
    await dialog.getByRole('button', { name: 'Insert Image' }).click();
    await dialog.waitFor({ state: 'hidden' });
  }

  async submit(): Promise<number> {
    await this.submitButton.click();
    return this.waitForCreatedEventId();
  }

  async waitForCreatedEventId(): Promise<number> {
    await this.page.waitForURL(/\/manage\/event\/\d+\/dashboard/);
    const match = this.page.url().match(/\/manage\/event\/(\d+)\/dashboard/);
    return Number(match?.[1]);
  }

  async createSingleEvent(details: { title: string; category?: string }): Promise<number> {
    const { title, category = 'MUSIC' } = details;
    await this.titleInput.fill(title);
    await this.selectOption('category', category);
    return this.submit();
  }
}
