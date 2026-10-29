import { expect, type Locator, type Page } from '@playwright/test';

export class CheckInListPage {
  constructor(private readonly page: Page) {}

  async goto(eventId: number): Promise<void> {
    await this.page.goto(`/manage/event/${eventId}/check-in`);
    await this.page.waitForLoadState('networkidle');
  }

  async createList(name: string, options: { badgePrinting?: boolean; kiosk?: boolean } = {}): Promise<void> {
    await this.page.getByTestId('checkin-list-create-button').click();
    await this.page.getByLabel(/^Name/).fill(name);
    if (options.badgePrinting) {
      await this.openRowAndToggle(this.page.getByRole('dialog'), 'badges', 'check-in-list-badge-printing-switch');
    }
    if (options.kiosk) {
      await this.toggleKiosk(this.page.getByRole('dialog'));
    }
    await this.page.getByTestId('checkin-list-submit-button').click();

    await this.page.getByRole('heading', { name: 'Check-In List Created' }).waitFor();
    await this.page.getByRole('button', { name: 'Done' }).click();
  }

  async toggleKiosk(dialog: Locator): Promise<void> {
    await this.openRowAndToggle(dialog, 'kiosk', 'check-in-list-kiosk-switch');
  }

  private async openRowAndToggle(dialog: Locator, row: string, switchTestId: string): Promise<void> {
    await dialog.getByTestId(`check-in-list-ledger-${row}`).click();
    const toggle = dialog.getByTestId(switchTestId);
    const wasChecked = await toggle.isChecked();
    await toggle.locator('xpath=ancestor::div[contains(@class, "mantine-Switch-root")]').locator('.mantine-Switch-track').click();
    await expect(toggle).toBeChecked({ checked: !wasChecked });
  }

  row(name: string): Locator {
    return this.page.getByRole('row').filter({ hasText: name });
  }

  async openRowAction(name: string, action: string): Promise<void> {
    await this.row(name).getByTestId('check-in-list-actions-menu').click();
    await this.page.getByRole('menuitem', { name: action }).click();
  }

  openCheckInButton(name: string): Locator {
    return this.row(name).getByTestId('check-in-list-open-button');
  }

  editNameInput(): Locator {
    return this.page.getByRole('dialog').getByLabel(/^Name/);
  }

  async submitEdit(): Promise<void> {
    await this.page.getByRole('dialog').getByRole('button', { name: 'Save Changes' }).click();
  }
}
