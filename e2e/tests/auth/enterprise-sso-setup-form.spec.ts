import { test, expect } from '../../fixtures';
import type { FreshAccount } from '../../fixtures';
import { EnterpriseSsoSettings, MOCK_OIDC_URL, mockOidcIsReachable } from '../../pages/enterprise-sso.page';
import { enableFeatureFlag, markSsoConnectionTested, markSsoDomainVerified } from '../../utils/db';

const uniqueSuffix = (): string => `${Date.now().toString(36)}${Math.random().toString(36).slice(2, 6)}`;

const prepareAccount = async (account: FreshAccount): Promise<number> => {
  const { id } = await account.api.getAccount();
  enableFeatureFlag(id, 'enterprise_sso');
  return id;
};

const createConnection = async (account: FreshAccount, accountId: number, issuerId: string): Promise<void> => {
  await account.api.createSsoConnection(accountId, {
    type: 'OIDC',
    issuer: `${MOCK_OIDC_URL}/${issuerId}`,
    client_id: 'hievents-e2e',
    client_secret: 'e2e-secret',
  });
};

test.describe('enterprise single sign-on setup form', () => {
  test('providers are offered as a radio choice and an empty save lists every missing field at once', async ({ freshAccount }) => {
    await prepareAccount(freshAccount);
    const page = await freshAccount.newAuthedPage();
    const settings = new EnterpriseSsoSettings(page);
    await settings.open();

    const okta = settings.drawer().getByRole('radio', { name: /Okta/ });
    await okta.click();
    await expect(okta).toBeChecked();
    await expect(settings.drawer().getByRole('radio', { name: /Other OIDC provider/ })).not.toBeChecked();

    await page.getByTestId('sso-connection-save').click();
    await expect(settings.drawer().getByText('Enter the issuer URL, starting with https://.')).toBeVisible();
    await expect(settings.drawer().getByText('Enter the client ID.')).toBeVisible();
    await expect(settings.drawer().getByText('Enter the client secret.')).toBeVisible();
  });

  test('pointing a saved connection at another issuer asks for the client secret again', async ({ freshAccount, page: probe }) => {
    test.skip(!(await mockOidcIsReachable(probe)), `No mock identity provider at ${MOCK_OIDC_URL}`);
    const accountId = await prepareAccount(freshAccount);
    const otherIssuerId = `moved-${uniqueSuffix()}`;
    await createConnection(freshAccount, accountId, `saved-${uniqueSuffix()}`);

    const page = await freshAccount.newAuthedPage();
    const settings = new EnterpriseSsoSettings(page);
    await settings.open();
    await settings.drawer().getByRole('button', { name: 'Edit details' }).click();
    await expect(settings.drawer().getByText('Saved and hidden. Leave blank to keep the current secret.')).toBeVisible();

    const issuer = settings.drawer().getByLabel('Issuer URL');
    await issuer.fill(`${MOCK_OIDC_URL}/${otherIssuerId}`);
    await issuer.press('Tab');
    await expect(settings.drawer().getByText('Found your sign-in settings')).toBeVisible();
    await expect(settings.drawer().getByText('You changed the provider details, so enter the client secret again.')).toBeVisible();
    await page.getByTestId('sso-connection-save').click();
    await expect(settings.drawer().getByText('Enter the client secret again when you change the identity provider details.')).toBeVisible();

    await settings.drawer().getByLabel('Client secret').fill('moved-secret');
    await page.getByTestId('sso-connection-save').click();
    await expect(page.getByText('Connection updated')).toBeVisible();
    await expect(settings.drawer()).toContainText(`${MOCK_OIDC_URL}/${otherIssuerId}`);
  });

  test('each member in the enforcement preview has an exempt switch named after them', async ({ freshAccount, page: probe }) => {
    test.skip(!(await mockOidcIsReachable(probe)), `No mock identity provider at ${MOCK_OIDC_URL}`);
    const accountId = await prepareAccount(freshAccount);
    const issuerId = `named-${uniqueSuffix()}`;
    const domain = `${issuerId}.test`;
    await createConnection(freshAccount, accountId, issuerId);
    markSsoConnectionTested(accountId);
    await freshAccount.api.setSsoConnectionStatus(accountId, 'ACTIVE');
    await freshAccount.api.addSsoDomain(accountId, domain);
    markSsoDomainVerified(accountId, domain);
    await freshAccount.api.inviteUser({ first_name: 'Grace', last_name: 'Hopper', email: `grace@${domain}`, role: 'ORGANIZER' });
    await freshAccount.api.inviteUser({ first_name: 'Alan', last_name: 'Turing', email: `alan@${domain}`, role: 'ORGANIZER' });

    const page = await freshAccount.newAuthedPage();
    const settings = new EnterpriseSsoSettings(page);
    await settings.open();
    await settings.toggle('sso-enforce-switch');

    const preview = page.getByTestId('sso-enforcement-preview');
    const grace = preview.getByRole('switch', { name: 'Exempt Grace Hopper' });
    await expect(grace).toBeVisible();
    await expect(preview.getByRole('switch', { name: 'Exempt Alan Turing' })).toBeVisible();

    await grace.locator('xpath=ancestor::label[1]').click();
    await expect(grace).toBeChecked();
    await expect(preview).toContainText('1 member will need to sign in with your identity provider.');
  });
});
