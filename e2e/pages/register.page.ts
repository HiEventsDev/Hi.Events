import type { Page } from '@playwright/test';
import { waitForHydration } from '../utils/hydration';

export class RegisterPage {
  constructor(private readonly page: Page) {}

  async goto(): Promise<void> {
    await this.page.goto('/auth/register');
    await this.page.getByLabel(/^First Name/).waitFor();
    await waitForHydration(this.page, 'form');
  }

  async register(details: { firstName: string; email: string; password: string }): Promise<void> {
    await this.page.getByLabel(/^First Name/).fill(details.firstName);
    await this.page.getByLabel(/^Email/).fill(details.email);
    await this.page.getByLabel(/^Password/).fill(details.password);
    await this.page.getByRole('button', { name: 'Create account' }).click();
  }
}
