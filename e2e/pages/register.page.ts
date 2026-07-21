import type { Page } from '@playwright/test';

export class RegisterPage {
  constructor(private readonly page: Page) {}

  async goto(): Promise<void> {
    await this.page.goto('/auth/register');
    await this.page.waitForLoadState('networkidle');
  }

  async register(details: { firstName: string; lastName?: string; email: string; password: string }): Promise<void> {
    await this.page.getByLabel(/^First Name/).fill(details.firstName);
    if (details.lastName) {
      await this.page.getByLabel(/^Last Name/).fill(details.lastName);
    }
    await this.page.getByLabel(/^Email/).fill(details.email);
    await this.page.getByLabel(/^Password/).fill(details.password);
    await this.page.getByLabel(/^Confirm Password/).fill(details.password);
    await this.page.getByRole('button', { name: 'Register' }).click();
  }
}
