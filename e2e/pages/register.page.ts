import type { Page } from '@playwright/test';
import { waitForHydration } from '../utils/hydration';

export class RegisterPage {
  constructor(private readonly page: Page) {}

  async goto(): Promise<void> {
    await this.page.goto('/auth/register');
    await this.page.getByLabel(/^First Name/).waitFor();
    await waitForHydration(this.page, 'form');
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
