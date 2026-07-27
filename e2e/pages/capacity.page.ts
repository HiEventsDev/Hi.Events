import type { Locator, Page } from '@playwright/test';

export class CapacityPage {
  constructor(private readonly page: Page) {}

  async goto(eventId: number): Promise<void> {
    await this.page.goto(`/manage/event/${eventId}/capacity-assignments`);
    await this.page.waitForLoadState('networkidle');
  }

  cardByName(name: string): Locator {
    return this.page.locator('[class*="capacityCard"]').filter({ hasText: name });
  }

  capacityInput(): Locator {
    return this.page.getByLabel(/^Capacity/);
  }

  async createAssignment(details: { name: string; capacity: number; productTitle: string }): Promise<void> {
    await this.page.getByTestId('capacity-create-button').click();
    await this.page.getByRole('heading', { name: 'Create Capacity Assignment' }).waitFor();
    await this.page.getByLabel(/^Name/).fill(details.name);
    await this.capacityInput().fill(String(details.capacity));
    await this.page.getByRole('combobox', { name: /^What products should this capacity apply to/ }).click();
    await this.page.getByRole('option', { name: details.productTitle }).click();
    await this.page.getByRole('heading', { name: 'Create Capacity Assignment' }).click();
    await this.page.getByRole('dialog').getByRole('button', { name: 'Create Capacity Assignment' }).click();
  }

  async openCardAction(name: string, action: string): Promise<void> {
    await this.cardByName(name).getByRole('button').click();
    await this.page.getByRole('menuitem', { name: action }).click();
  }

  async submitEdit(capacity: number): Promise<void> {
    await this.capacityInput().fill(String(capacity));
    await this.page.getByRole('dialog').getByRole('button', { name: 'Edit Capacity Assignment' }).click();
  }

  async confirmAction(): Promise<void> {
    await this.page.getByRole('button', { name: 'Confirm' }).click();
  }
}
