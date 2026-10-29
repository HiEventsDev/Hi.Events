import type { Locator, Page } from '@playwright/test';

type Access = 'Read and write' | 'Read-only';

export class ApiKeysPage {
  constructor(private readonly page: Page) {}

  async goto(): Promise<void> {
    await this.page.context().grantPermissions(['clipboard-read', 'clipboard-write']);
    await this.page.goto('/settings/api-keys');
    await this.page.getByRole('heading', { level: 1, name: 'API keys' }).waitFor();
    await this.page.waitForLoadState('networkidle');
  }

  row(name: string): Locator {
    return this.page.getByTestId('api-key-row').filter({ hasText: name });
  }

  emptyState(): Locator {
    return this.page.getByTestId('api-keys-empty');
  }

  dialog(): Locator {
    return this.page.getByRole('dialog');
  }

  async openCreate(): Promise<void> {
    await this.page.getByTestId('api-key-create-button').first().click();
    await this.dialog().getByRole('heading', { name: 'Create API key' }).waitFor();
  }

  async fillCreateForm(details: { name: string; access?: Access; expiry?: string; password: string }): Promise<void> {
    await this.page.getByTestId('api-key-name').fill(details.name);
    if (details.access === 'Read-only') {
      await this.page.getByTestId('api-key-access-read-only').click();
    }
    if (details.expiry) {
      await this.page.getByTestId('api-key-expiry').click();
      await this.page.getByRole('option', { name: details.expiry, exact: true }).click();
    }
    await this.page.getByTestId('api-key-password').fill(details.password);
  }

  async submitCreate(): Promise<void> {
    await this.page.getByTestId('api-key-create-submit').click();
  }

  async createKey(details: { name: string; access?: Access; expiry?: string; password: string }): Promise<string> {
    await this.openCreate();
    await this.fillCreateForm(details);
    await this.submitCreate();
    await this.dialog().getByRole('heading', { name: 'Copy your API key' }).waitFor();
    const key = (await this.page.getByTestId('api-key-secret').textContent())?.trim() ?? '';
    await this.page.getByTestId('api-key-copy').click();
    await this.page.getByTestId('api-key-done').click();
    await this.dialog().waitFor({ state: 'hidden' });
    return key;
  }

  async revoke(name: string): Promise<void> {
    await this.row(name).getByTestId('api-key-revoke').click();
    await this.page.getByRole('dialog').getByRole('button', { name: /^(Revoke|Remove) key$/ }).click();
  }

  async showEveryone(): Promise<void> {
    await this.page.getByTestId('api-key-scope').getByText(/^Everyone/).click();
  }
}
