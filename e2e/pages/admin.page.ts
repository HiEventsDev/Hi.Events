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

  accountFeatureFlag(key: string): Locator {
    return this.page.getByTestId(`account-feature-flag-${key}`);
  }

  async setAccountFeatureFlag(key: string, value: 'On' | 'Off'): Promise<void> {
    await this.accountFeatureFlag(key).getByText(value, { exact: true }).click();
  }

  async gotoFeatureFlags(): Promise<void> {
    await this.page.goto('/admin/feature-flags');
    await this.page.waitForLoadState('networkidle');
  }

  async expandFeatureFlagOverrides(key: string): Promise<void> {
    await this.page.getByTestId(`feature-flag-overrides-toggle-${key}`).click();
  }

  featureFlagOverrideRow(accountId: number): Locator {
    return this.page.getByTestId(`feature-flag-override-${accountId}`);
  }

  async selectDashboardPeriod(label: 'Last 24 hours' | 'Last 7 days' | 'Last 30 days'): Promise<void> {
    await this.page.getByTestId('admin-dashboard-period').getByText(label, { exact: true }).click();
  }

  accountImpersonateButtons(): Locator {
    return this.page.getByTestId('admin-account-impersonate-button');
  }

  async scheduleAccountDeletion(accountName: string, reason: string): Promise<void> {
    await this.page.getByTestId('admin-delete-account-button').click();
    await this.page.getByLabel('Reason').fill(reason);
    await this.page.getByLabel('Type the account name to confirm').fill(accountName);
    await this.page.getByTestId('admin-delete-account-confirm-button').click();
  }

  async gotoLifecycleEmails(): Promise<void> {
    await this.page.goto('/admin/lifecycle-emails');
    await this.page.waitForLoadState('networkidle');
  }

  async selectLifecycleEmail(nudgeType: string): Promise<void> {
    await this.page.getByTestId(`lifecycle-email-item-${nudgeType}`).click();
  }

  lifecycleEmailSubject(): Locator {
    return this.page.getByRole('textbox', { name: 'Subject' });
  }

  lifecycleEmailPreviewFrame() {
    return this.page.frameLocator('iframe[title="Email preview"]');
  }

  async saveLifecycleEmail(): Promise<void> {
    await this.page.getByTestId('lifecycle-email-save-button').click();
  }
}
