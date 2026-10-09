import { expect, type Browser, type Page } from '@playwright/test';
import { BASE_URL } from '../utils/env';
import { nextTotpCode } from '../utils/totp';

export async function openAnonymousPage(browser: Browser): Promise<Page> {
  const context = await browser.newContext({ baseURL: BASE_URL, ignoreHTTPSErrors: true });
  return context.newPage();
}

export class TwoFactorPage {
  constructor(private readonly page: Page) {}

  async gotoSecurity(): Promise<void> {
    await this.page.goto('/manage/profile/security');
    await this.page.waitForLoadState('networkidle');
    await expect(this.page.getByText('Two-factor authentication', { exact: true }).first()).toBeVisible();
  }

  status() {
    return this.page.getByTestId('two-factor-status');
  }

  async enable(password: string): Promise<{ secret: string; recoveryCodes: string[] }> {
    await this.page.getByTestId('two-factor-enable-button').click();
    return this.completeSetupFlow(password);
  }

  async completeSetupFlow(password: string): Promise<{ secret: string; recoveryCodes: string[] }> {
    await this.page.getByTestId('two-factor-setup-password').fill(password);
    await this.page.getByTestId('two-factor-setup-password-submit').click();
    await this.page.getByTestId('two-factor-manual-entry-toggle').click();
    const secretLocator = this.page.getByTestId('two-factor-secret');
    await expect(secretLocator).toBeVisible();
    const secret = ((await secretLocator.textContent()) ?? '').replace(/\s/g, '');

    await this.page.getByTestId('two-factor-setup-next').click();
    await this.page.getByTestId('two-factor-setup-code').fill(await nextTotpCode(secret));

    const codes = this.page.getByTestId('two-factor-recovery-codes').getByRole('listitem');
    await expect(codes).toHaveCount(10);
    const recoveryCodes = (await codes.allTextContents()).map((code) => code.trim());

    await expect(this.page.getByTestId('two-factor-setup-done')).toBeDisabled();
    await this.page.getByText('I have saved my recovery codes somewhere safe').click();
    await this.page.getByTestId('two-factor-setup-done').click();

    return { secret, recoveryCodes };
  }

  async signInWithPassword(email: string, password: string): Promise<void> {
    await this.page.goto('/auth/login');
    await this.page.waitForLoadState('networkidle');
    await this.page.getByLabel(/^Email/).fill(email);
    await this.page.getByLabel(/^Password/).fill(password);
    await this.page.getByRole('button', { name: 'Log in' }).click();
  }

  async expectChallenge(): Promise<void> {
    await expect(this.page.getByRole('heading', { name: 'Two-factor authentication' })).toBeVisible();
  }

  async enterLoginCode(code: string, opts: { trustDevice?: boolean } = {}): Promise<void> {
    if (opts.trustDevice) {
      await this.page.getByText('Trust this device for 30 days').click();
    }
    await this.page.getByTestId('two-factor-login-code').fill(code);
  }

  async enterRecoveryCode(code: string): Promise<void> {
    await this.page.getByTestId('two-factor-toggle-mode').click();
    await expect(this.page.getByRole('heading', { name: 'Use a recovery code' })).toBeVisible();
    await this.page.getByTestId('two-factor-login-recovery-code').fill(code);
    await this.page.getByTestId('two-factor-login-submit').click();
  }

  async expectDashboard(): Promise<void> {
    await expect(this.page).toHaveURL(/\/manage\/organizer\/\d+/);
    await expect(this.page.getByRole('heading', { level: 1, name: /Dashboard/ })).toBeVisible();
  }
}
