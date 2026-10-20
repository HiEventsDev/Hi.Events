import type { Locator, Page } from '@playwright/test';

type TeamRole = 'Admin' | 'Organizer' | 'Viewer';

export class TeamPage {
  constructor(private readonly page: Page) {}

  async goto(): Promise<void> {
    await this.page.goto('/settings/team');
    await this.page.waitForLoadState('networkidle');
  }

  userRow(email: string): Locator {
    return this.page.getByTestId('team-member-row').filter({ hasText: email });
  }

  async inviteUser(details: {
    firstName: string;
    lastName: string;
    email: string;
    role: TeamRole;
    onlyAccessTo?: string[];
  }): Promise<void> {
    await this.page.getByTestId('team-invite-button').click();
    await this.page.getByRole('heading', { name: 'Invite a team member' }).waitFor();
    await this.page.getByLabel(/^First Name/).fill(details.firstName);
    await this.page.getByLabel(/^Last Name/).fill(details.lastName);
    await this.page.getByLabel(/^Email/).fill(details.email);
    if (details.role !== 'Organizer') {
      await this.selectRole(details.role);
    }
    if (details.onlyAccessTo) {
      await this.restrictAccessTo(details.onlyAccessTo);
    }
    await this.page.getByTestId('invite-team-member-submit').click();
  }

  async restrictAccessTo(targets: string[]): Promise<void> {
    const dialog = this.page.getByRole('dialog');
    await dialog.getByText('Specific organizers and events', { exact: true }).click();
    const picker = dialog.getByPlaceholder('Search organizers and events');
    for (const target of targets) {
      await picker.click();
      await picker.fill(target);
      await this.page.getByRole('option', { name: target, exact: true }).click();
    }
    await picker.press('Escape');
  }

  async openEditUserModal(email: string): Promise<void> {
    await this.userRow(email).getByTestId('team-member-actions').click();
    await this.page.getByRole('menuitem', { name: 'Edit user' }).click();
    await this.page.getByRole('heading', { name: 'Edit member' }).waitFor();
  }

  async selectRole(role: TeamRole): Promise<void> {
    await this.page.getByTestId(`team-role-${role.toLowerCase()}`).click();
  }

  async search(text: string): Promise<void> {
    await this.page.getByLabel('Search members').fill(text);
  }

  async chooseRowAction(email: string, action: string): Promise<void> {
    await this.userRow(email).getByTestId('team-member-actions').click();
    await this.page.getByRole('menuitem', { name: action, exact: true }).click();
  }

  async confirm(label = 'Confirm'): Promise<void> {
    await this.page.getByRole('dialog').getByRole('button', { name: label, exact: true }).click();
  }

  async deactivate(email: string): Promise<void> {
    await this.chooseRowAction(email, 'Deactivate');
    await this.confirm('Deactivate');
  }

  async submitEditUser(): Promise<void> {
    await this.page.getByTestId('edit-team-member-submit').click();
  }
}
