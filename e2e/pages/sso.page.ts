import { expect, type Page } from '@playwright/test';

export interface IdentityClaims {
  email?: string;
  email_verified?: boolean;
  given_name?: string;
  family_name?: string;
}

export class SsoPage {
  constructor(private readonly page: Page) {}

  providerButton() {
    return this.page.getByTestId('sso-provider-button-oidc');
  }

  async signInAtProvider(subject: string, claims: IdentityClaims): Promise<void> {
    await this.page.waitForURL(/\/default\/authorize/);
    await this.page.locator('input[name=username]').fill(subject);
    await this.page.locator('textarea[name=claims]').fill(JSON.stringify(claims));
    await this.page.locator('input[type=submit]').click();
    await this.page.waitForURL(/\/sso\/callback/);
  }

  async completeSignup(): Promise<void> {
    await expect(this.page.getByRole('heading', { name: 'One last step' })).toBeVisible();
    await this.page.getByTestId('sso-complete-signup-submit').click();
  }
}

export const providerIsConfigured = async (page: Page): Promise<boolean> => {
  const response = await page.request.get('/api/auth/sso/providers');
  if (!response.ok()) return false;
  const body = await response.json();
  return body.data.providers.some((provider: { key: string }) => provider.key === 'oidc');
};
