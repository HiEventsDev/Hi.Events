import { expect, type Page } from '@playwright/test';
import { BASE_URL } from '../utils/env';
import type { IdentityClaims } from './sso.page';

const isDevStack = (): boolean => new URL(BASE_URL).port === '8443';

export const MOCK_OIDC_URL = process.env.E2E_MOCK_OIDC_URL
  ?? (isDevStack() ? 'http://hievents-mock-oidc.orb.local:8080' : 'http://mock-oidc:8124');

const probeUrl = (): string => {
  const url = new URL(MOCK_OIDC_URL);
  if (url.hostname === 'mock-oidc') url.hostname = 'localhost';
  return `${url.origin}/default/.well-known/openid-configuration`;
};

export const mockOidcIsReachable = async (page: Page): Promise<boolean> => {
  let reachable = false;
  try {
    reachable = (await page.request.get(probeUrl(), { timeout: 3000 })).ok();
  } catch {
    reachable = false;
  }
  if (!reachable && process.env.CI) {
    throw new Error(`The mock identity provider at ${probeUrl()} is not reachable`);
  }
  return reachable;
};

export const signInAtMockProvider = async (page: Page, issuerId: string, subject: string, claims: IdentityClaims): Promise<void> => {
  await page.waitForURL(new RegExp(`/${issuerId}/authorize`));
  await page.locator('input[name=username]').fill(subject);
  await page.locator('textarea[name=claims]').fill(JSON.stringify(claims));
  await page.locator('input[type=submit]').click();
};

export class EnterpriseSsoSettings {
  constructor(private readonly page: Page) {}

  async open(): Promise<void> {
    await this.page.goto('/settings/account');
    const setup = this.page.getByTestId('sso-setup-button');
    const manage = this.page.getByTestId('sso-manage-button');
    await expect(setup.or(manage)).toBeVisible();
    await (await setup.isVisible() ? setup : manage).click();
    await expect(this.page.getByRole('dialog')).toBeVisible();
  }

  drawer() {
    return this.page.getByRole('dialog');
  }

  async connect(issuer: string, clientId: string, clientSecret: string): Promise<void> {
    await this.page.getByTestId('sso-provider-card-OIDC').click();
    await this.drawer().getByLabel('Issuer URL').fill(issuer);
    await this.drawer().getByLabel('Client ID').fill(clientId);
    await this.drawer().getByLabel('Client secret').fill(clientSecret);
    await this.page.getByTestId('sso-connection-save').click();
    await expect(this.page.getByTestId('sso-test-sign-in')).toBeVisible();
  }

  async testSignIn(issuerId: string, subject: string, claims: IdentityClaims): Promise<void> {
    const popupPromise = this.page.waitForEvent('popup');
    await this.page.getByTestId('sso-test-sign-in').click();
    const popup = await popupPromise;
    await signInAtMockProvider(popup, issuerId, subject, claims);
    await popup.waitForEvent('close');
  }

  async toggle(testId: string): Promise<void> {
    await this.page.getByTestId(testId).locator('xpath=ancestor::label[1]').click();
  }

  async addDomain(domain: string): Promise<void> {
    await this.drawer().getByLabel('Email domain').fill(domain);
    await this.drawer().getByRole('button', { name: 'Add domain' }).click();
    await expect(this.drawer().getByTestId('sso-domain-row').filter({ hasText: domain })).toContainText('Pending');
  }
}

export class SsoLoginPage {
  constructor(private readonly page: Page) {}

  async signInWithSso(email: string): Promise<void> {
    await this.page.goto('/auth/login');
    await this.page.getByTestId('sso-enterprise-button').click();
    await expect(this.page.getByRole('heading', { name: 'Sign in with SSO' })).toBeVisible();
    await this.page.getByLabel('Work email').fill(email);
    await this.page.getByTestId('sso-discovery-submit').click();
  }
}
