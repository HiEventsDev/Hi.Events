import type { Browser, Page } from '@playwright/test';
import { test, expect } from '../../fixtures';
import { ApiClient, confirmEmailWithCode, login, registerAccount } from '../../api/api-client';
import { openAuthedPage } from '../../fixtures/auth.fixture';
import { AudiencePage } from '../../pages/audience.page';
import { API_BASE_URL } from '../../utils/env';
import { saasOnly } from '../../utils/mode';
import { liftThrottle } from '../../utils/throttle';
import { uniqueEmail, uniqueName, uniqueShort } from '../../utils/unique';
import { buildAudience } from './audience.shared';

const PASSWORD = 'Password123!';
const NOT_PUBLISHED = "Signup forms only work while your organizer page is published. Until then, links and embeds say the form isn't available, and new forms are saved as paused.";
const NOT_CONFIRMED = "Signup forms only work once your account's email address is confirmed. Check your inbox for the confirmation email. Until then, links and embeds say the form isn't available, and new forms are saved as paused.";

const formRow = (page: Page, name: string) => page.getByTestId('signup-form-row').filter({ hasText: name });

const createFormThroughTheEditor = async (page: Page, name: string, title: string): Promise<void> => {
  await page.getByTestId('signup-form-create-button').click();
  await page.getByTestId('signup-form-name').fill(name);
  await page.getByTestId('signup-form-title').fill(title);
  await page.getByTestId('signup-form-save').click();
};

const chooseFormAction = async (page: Page, name: string, action: string): Promise<void> => {
  await formRow(page, name).getByRole('button', { name: 'Form actions' }).click();
  await page.getByRole('menuitem', { name: action, exact: true }).click();
};

const expectVisitorSees = async (browser: Browser, path: string, heading: string): Promise<void> => {
  const visitor = await browser.newPage();
  try {
    await visitor.goto(path);
    await expect(visitor.getByRole('heading', { name: heading })).toBeVisible();
  } finally {
    await visitor.close();
  }
};

test.describe('audience signup form visibility', () => {
  test.beforeEach(() => liftThrottle('crm-form-submit'));

  test('a signup form is saved paused while the organizer page is unpublished, goes public once it is published, and hides again if it is unpublished', async ({ freshAccount, browser }) => {
    const { api, organizerId } = freshAccount;
    await buildAudience(api, organizerId);
    const name = uniqueShort('Newsletter');
    const title = 'Hear about new shows';

    const page = await freshAccount.newAuthedPage();
    const audience = new AudiencePage(page, organizerId);
    await audience.gotoForms();
    await expect(page.getByText('Publish your organizer page to take signups')).toBeVisible();
    await expect(page.getByText(NOT_PUBLISHED)).toBeVisible();

    await createFormThroughTheEditor(page, name, title);
    await expect(page.getByText('Form saved as paused')).toBeVisible();
    await expect(page.getByRole('dialog', { name: `Share "${name}"` })).toHaveCount(0);
    const row = formRow(page, name);
    await expect(row).toContainText('Paused');
    await expect(row.getByTestId('signup-form-share')).toBeDisabled();
    await row.getByRole('button', { name: 'Form actions' }).click();
    await expect(page.getByRole('menuitem', { name: 'Resume', exact: true })).toBeDisabled();
    await page.keyboard.press('Escape');

    await api.updateOrganizerStatus(organizerId, 'LIVE');
    await page.reload();
    await expect(row).toContainText('Paused');
    await expect(page.getByText('Publish your organizer page to take signups')).toHaveCount(0);
    await chooseFormAction(page, name, 'Resume');
    await expect(page.getByText('Form is live again')).toBeVisible();
    await expect(row).toContainText('Live');
    await row.getByTestId('signup-form-share').click();
    const share = page.getByRole('dialog', { name: `Share "${name}"` });
    const formPath = new URL(await share.getByTestId('signup-form-link').inputValue()).pathname;
    await share.getByRole('button', { name: 'Close' }).click();
    await expectVisitorSees(browser, formPath, title);

    await api.updateOrganizerStatus(organizerId, 'DRAFT');
    await page.reload();
    await expect(page.getByText(NOT_PUBLISHED)).toBeVisible();
    await expect(row).toContainText('Hidden');
    await expect(row).toContainText('Hidden until your organizer page is published');
    await expect(row).not.toContainText('Live');
    await expect(row.getByTestId('signup-form-share')).toBeDisabled();
    await expectVisitorSees(browser, formPath, "This form isn't available");

    await api.updateOrganizerStatus(organizerId, 'LIVE');
    await page.reload();
    await expect(row).toContainText('Live');
    await expect(row).not.toContainText('Hidden');
    const email = uniqueEmail('late-reader');
    const visitor = await browser.newPage();
    try {
      await visitor.goto(formPath);
      await expect(visitor.getByRole('heading', { name: title })).toBeVisible();
      await visitor.getByTestId('signup-form-email').fill(email);
      await visitor.getByTestId('signup-form-submit').click();
      await expect(visitor.getByTestId('signup-form-check-inbox')).toContainText(email);
    } finally {
      await visitor.close();
    }
    await page.reload();
    await expect(row.getByTestId('signup-form-started')).toHaveText('1');
  });

  test('on Hi.Events Cloud signup forms cannot go live until the account confirms its email address and publishes its organizer page', async ({ playwright, browser, mailpit }) => {
    saasOnly();
    const anon = await playwright.request.newContext({ baseURL: API_BASE_URL, ignoreHTTPSErrors: true, extraHTTPHeaders: { Accept: 'application/json' } });
    const email = uniqueEmail('unconfirmed');
    await registerAccount(anon, {
      first_name: 'E2E',
      last_name: 'Unconfirmed',
      email,
      password: PASSWORD,
      password_confirmation: PASSWORD,
      timezone: 'UTC',
      currency_code: 'USD',
      locale: 'en',
    });
    const { token, user } = await login(anon, email, PASSWORD);
    const authed = await playwright.request.newContext({
      baseURL: API_BASE_URL,
      ignoreHTTPSErrors: true,
      extraHTTPHeaders: { Accept: 'application/json', Authorization: `Bearer ${token}` },
    });
    const page = await openAuthedPage(browser, token);
    try {
      const api = new ApiClient(authed);
      const organizer = await api.createOrganizer(uniqueName('E2E Unconfirmed Org'));
      await buildAudience(api, organizer.id);
      const name = uniqueShort('Early form');

      const audience = new AudiencePage(page, organizer.id);
      await audience.gotoForms();
      await expect(page.getByText('Confirm your email address to take signups')).toBeVisible();
      await expect(page.getByText(NOT_CONFIRMED)).toBeVisible();
      await createFormThroughTheEditor(page, name, 'Be the first to know');
      await expect(page.getByText('Form saved as paused')).toBeVisible();
      await expect(formRow(page, name)).toContainText('Paused');
      await formRow(page, name).getByRole('button', { name: 'Form actions' }).click();
      await expect(page.getByRole('menuitem', { name: 'Resume', exact: true })).toBeDisabled();
      await page.keyboard.press('Escape');

      await confirmEmailWithCode(anon, token, user.id, await mailpit.waitForVerificationCode(email));
      await page.reload();
      await expect(page.getByText(NOT_PUBLISHED)).toBeVisible();
      await expect(page.getByText('Confirm your email address to take signups')).toHaveCount(0);
      await api.updateOrganizerStatus(organizer.id, 'LIVE');
      await page.reload();
      await expect(formRow(page, name)).toContainText('Paused');
      await expect(page.getByText(NOT_PUBLISHED)).toHaveCount(0);
      await chooseFormAction(page, name, 'Resume');
      await expect(page.getByText('Form is live again')).toBeVisible();
      await expect(formRow(page, name)).toContainText('Live');
      await formRow(page, name).getByTestId('signup-form-share').click();
      const formPath = new URL(await page.getByRole('dialog', { name: `Share "${name}"` }).getByTestId('signup-form-link').inputValue()).pathname;
      await expectVisitorSees(browser, formPath, 'Be the first to know');
    } finally {
      await page.context().close();
      await authed.dispose();
      await anon.dispose();
    }
  });
});
