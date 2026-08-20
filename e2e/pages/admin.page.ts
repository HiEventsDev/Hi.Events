import type { Locator, Page } from '@playwright/test';

export class AdminPage {
  constructor(private readonly page: Page) {}

  async gotoDashboard(): Promise<void> {
    await this.page.goto('/admin');
    await this.page.waitForLoadState('networkidle');
  }

  async gotoSection(section: 'users' | 'events' | 'orders'): Promise<void> {
    await this.page.goto(`/admin/${section}`);
    await this.page.waitForLoadState('networkidle');
  }

  async openAccountsFromSidebar(): Promise<void> {
    await this.page.getByRole('link', { name: 'Accounts', exact: true }).click();
  }

  viewDetailsButtons(): Locator {
    return this.page.getByRole('button', { name: 'View Details' });
  }

  async search(term: string): Promise<void> {
    await this.page.getByPlaceholder(/^Search by/).fill(term);
  }

  async gotoAccount(accountId: number): Promise<void> {
    await this.page.goto(`/admin/accounts/${accountId}`);
    await this.page.waitForLoadState('networkidle');
    await this.page.getByText('Account Information').waitFor();
  }

  verificationSwitch(): Locator {
    return this.page.getByTestId('account-verification-switch');
  }
}
