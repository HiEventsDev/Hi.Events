import type { Locator, Page } from '@playwright/test';

type TeamRole = 'Admin' | 'Organizer';

export class TeamPage {
  constructor(private readonly page: Page) {}

  async goto(): Promise<void> {
    await this.page.goto('/account/users');
    await this.page.waitForLoadState('networkidle');
  }

  userRow(email: string): Locator {
    return this.page.getByRole('row').filter({ hasText: email });
  }

  async inviteUser(details: { firstName: string; lastName: string; email: string; role: TeamRole }): Promise<void> {
    await this.page.getByTestId('team-invite-button').click();
    await this.page.getByRole('heading', { name: 'Invite a team member' }).waitFor();
    await this.page.getByLabel(/^First Name/).fill(details.firstName);
    await this.page.getByLabel(/^Last Name/).fill(details.lastName);
    await this.page.getByLabel(/^Email/).fill(details.email);
    if (details.role !== 'Admin') {
      await this.selectRole('Admin', details.role);
    }
    await this.page.getByRole('button', { name: 'Invite Team Member' }).click();
  }

  async openEditUserModal(email: string): Promise<void> {
    await this.userRow(email).getByRole('button').click();
    await this.page.getByRole('menuitem', { name: 'Edit user' }).click();
    await this.page.getByRole('heading', { name: 'Edit User' }).waitFor();
  }

  async selectRole(currentRole: TeamRole, newRole: TeamRole): Promise<void> {
    await this.page.getByRole('dialog').getByText(currentRole, { exact: true }).click();
    await this.page.getByRole('option', { name: newRole }).click();
  }

  async selectStatus(status: 'Active' | 'Inactive'): Promise<void> {
    await this.page.getByRole('dialog').getByLabel(/^Status/).click();
    await this.page.getByRole('option', { name: status, exact: true }).click();
  }

  async submitEditUser(): Promise<void> {
    await this.page.getByRole('button', { name: 'Edit User', exact: true }).click();
  }
}
