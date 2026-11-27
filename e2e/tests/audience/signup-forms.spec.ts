import { randomBytes } from 'node:crypto';
import { test, expect, type FreshAccount } from '../../fixtures';
import { AudiencePage } from '../../pages/audience.page';
import { openEmbedHost, resolveWidgetFrame } from '../../pages/embedded-widget';
import type { MailpitClient } from '../../utils/mailpit';
import { uniqueEmail, uniqueShort } from '../../utils/unique';
import { expirePendingSubscription } from '../../utils/audience-db';
import { liftThrottle } from '../../utils/throttle';
import { createLiveEventWithFreeTicket } from '../../api/factory';
import { buildAudience, createOptedInOrder, marketingStatusOf, submitSignupForm, waitForContact, type SignupForm } from './audience.shared';
import { expectNoSidewaysScroll, PHONE_VIEWPORT } from './contacts.helpers';
import { contactIdOf, withMessagingApi } from './messaging.shared';

const CONFIRM_LINK = /\/audience\/confirm\//;

const arrangeLiveForm = async (freshAccount: FreshAccount, playwright: Parameters<typeof withMessagingApi>[0], name: string): Promise<SignupForm> => {
  await buildAudience(freshAccount.api, freshAccount.organizerId);
  await freshAccount.api.updateOrganizerStatus(freshAccount.organizerId, 'LIVE');
  return withMessagingApi(playwright, freshAccount, (messaging) => messaging.createSignupForm({ name, title: 'Hear about new shows' }));
};

const confirmLinks = async (mailpit: MailpitClient, email: string, count: number): Promise<string[]> => {
  await expect.poll(async () => (await mailpit.search(email)).length, { timeout: 45_000 }).toBe(count);
  const messages = await mailpit.search(email);
  return Promise.all(messages.map(async (message) => new URL((await mailpit.getLinks(message.ID)).find((link) => CONFIRM_LINK.test(link))!).pathname));
};

test.describe('audience signup forms', () => {
  test.beforeEach(() => liftThrottle('crm-form-submit'));

  test('a visitor signs up on the hosted form and becomes a subscriber once they confirm by email', { tag: '@smoke' }, async ({ freshAccount, browser, mailpit }) => {
    const { api, organizerId } = freshAccount;
    await buildAudience(api, organizerId);
    await api.updateOrganizerStatus(organizerId, 'LIVE');
    const organizer = await api.getOrganizer(organizerId);
    const email = uniqueEmail('reader');

    const page = await freshAccount.newAuthedPage();
    const audience = new AudiencePage(page, organizerId);
    await audience.gotoForms();
    await expect(page.getByRole('heading', { name: 'Signup forms' })).toBeVisible();

    await page.getByTestId('signup-form-create-button').click();
    await page.getByTestId('signup-form-name').fill('Monthly news');
    await page.getByTestId('signup-form-title').fill('Hear about new shows');
    await page.getByTestId('signup-form-organizer-page').click();
    const editor = page.getByRole('dialog', { name: 'New signup form' });
    await expect(editor.locator('form form')).toHaveCount(0);
    await expect(editor.locator('[inert]').getByRole('heading', { name: 'Hear about new shows' })).toBeVisible();
    await expect(editor.locator('[inert]').getByTestId('signup-form-submit')).toHaveText('Subscribe');
    await page.getByTestId('signup-form-save').click();

    const share = page.getByRole('dialog', { name: 'Share "Monthly news"' });
    await expect(share).toBeVisible();
    const link = await share.getByTestId('signup-form-link').inputValue();
    expect(link).toMatch(/\/subscribe\/sf_/);
    await share.getByRole('button', { name: 'Close' }).click();
    await expect(page.getByTestId('signup-form-row').filter({ hasText: 'Monthly news' })).toContainText('On your organizer page');

    const visitor = await browser.newPage();
    try {
      await visitor.goto(new URL(link).pathname);
      await expect(visitor.getByRole('heading', { name: 'Hear about new shows' })).toBeVisible();
      await visitor.getByTestId('signup-form-email').fill(email);
      await visitor.getByTestId('signup-form-submit').click();
      await expect(visitor.getByTestId('signup-form-check-inbox')).toContainText(email);

      const confirmUrl = await mailpit.waitForLink(email, /\/audience\/confirm\//, { timeoutMs: 45_000, subjectContains: 'Confirm your subscription' });
      await visitor.goto(confirmUrl.pathname);
      await expect(visitor.getByRole('heading', { name: 'Confirm your subscription' })).toBeVisible();
      await visitor.getByTestId('subscription-confirm-button').click();
      await expect(visitor.getByRole('heading', { name: "You're subscribed" })).toBeVisible();

      await waitForContact(api, organizerId, email);
      expect(await marketingStatusOf(api, organizerId, email)).toBe('SUBSCRIBED');

      await page.reload();
      const formRow = page.getByTestId('signup-form-row').filter({ hasText: 'Monthly news' });
      await expect(formRow.getByTestId('signup-form-started')).toHaveText('1');
      await expect(formRow.getByTestId('signup-form-subscribers')).toHaveText('1');

      await expect(formRow).toContainText('Last confirmed');
      await expect(formRow).not.toContainText('None yet');

      const viaBand = uniqueEmail('band-reader');
      await visitor.goto(`/events/${organizerId}/${organizer.slug}`);
      const band = visitor.getByTestId('organizer-signup-form');
      await expect(band).toContainText('Hear about new shows');
      await band.getByTestId('signup-form-email').fill(viaBand);
      await band.getByTestId('signup-form-submit').click();
      await expect(band.getByTestId('signup-form-check-inbox')).toContainText(viaBand);
      await mailpit.waitForLink(viaBand, CONFIRM_LINK, { timeoutMs: 45_000, subjectContains: 'Confirm your subscription' });
      await page.reload();
      await expect(formRow.getByTestId('signup-form-started')).toHaveText('2');
      await expect(formRow.getByTestId('signup-form-waiting')).toHaveText('1');

      await formRow.getByRole('button', { name: 'Form actions' }).click();
      await page.getByRole('menuitem', { name: 'Edit' }).click();
      await page.getByTestId('signup-form-organizer-page').click();
      await page.getByTestId('signup-form-save').click();
      await expect(page.getByText('Form saved')).toBeVisible();
      await expect(formRow).not.toContainText('On your organizer page');
      await visitor.goto(`/events/${organizerId}/${organizer.slug}`);
      await expect(visitor.getByRole('heading', { name: organizer.name }).first()).toBeVisible();
      await expect(visitor.getByTestId('organizer-signup-form')).toHaveCount(0);
    } finally {
      await visitor.close();
    }
  });

  test('a visitor signs up through the form embedded on another website and gets the confirmation email', async ({ freshAccount, playwright, browser, mailpit, baseURL }) => {
    const name = uniqueShort('Embed news');
    const form = await arrangeLiveForm(freshAccount, playwright, name);
    const email = uniqueEmail('embedded');

    const page = await freshAccount.newAuthedPage();
    await new AudiencePage(page, freshAccount.organizerId).gotoForms();
    await page.getByTestId('signup-form-row').filter({ hasText: name }).getByTestId('signup-form-share').click();
    const share = page.getByRole('dialog', { name: `Share "${name}"` });
    const embedCode = await share.getByText('data-hievents-form', { exact: false }).textContent() ?? '';
    const placeholder = embedCode.split('\n').find((line) => line.includes('data-hievents-form')) ?? '';
    expect(placeholder).toContain(`data-hievents-form="${form.public_id}"`);

    const host = await browser.newPage();
    try {
      await openEmbedHost(host, baseURL!, placeholder);
      const widget = await resolveWidgetFrame(host, 'Signup form');
      await expect(widget.getByRole('heading', { name: 'Hear about new shows' })).toBeVisible();
      await widget.getByTestId('signup-form-email').fill(email);
      await widget.getByTestId('signup-form-submit').click();
      await expect(widget.getByTestId('signup-form-check-inbox')).toContainText(email);
    } finally {
      await host.close();
    }

    const confirmUrl = await mailpit.waitForLink(email, CONFIRM_LINK, { timeoutMs: 45_000, subjectContains: 'Confirm your subscription' });
    expect(confirmUrl.pathname).toMatch(/^\/audience\/confirm\/[A-Za-z0-9_-]+$/);
  });

  test('a confirm link that is not one we sent says it is not valid', async ({ page }) => {
    await page.goto(`/audience/confirm/${randomBytes(24).toString('base64url')}`);
    await expect(page.getByRole('heading', { name: "This link isn't valid" })).toBeVisible();
    await expect(page.getByTestId('subscription-confirm-button')).toHaveCount(0);
  });

  test('an earlier confirm link still works after signing up again', async ({ freshAccount, playwright, publicApi, browser, mailpit }) => {
    const form = await arrangeLiveForm(freshAccount, playwright, uniqueShort('Twice news'));
    const email = uniqueEmail('twice');
    await submitSignupForm(publicApi, form.public_id, email);
    const [earlier] = await confirmLinks(mailpit, email, 1);
    await submitSignupForm(publicApi, form.public_id, email);
    await confirmLinks(mailpit, email, 2);

    const visitor = await browser.newPage();
    try {
      await visitor.goto(earlier);
      await visitor.getByTestId('subscription-confirm-button').click();
      await expect(visitor.getByRole('heading', { name: "You're subscribed" })).toBeVisible();
    } finally {
      await visitor.close();
    }
    await expect.poll(() => marketingStatusOf(freshAccount.api, freshAccount.organizerId, email)).toBe('SUBSCRIBED');
  });

  test('a form that asks for a name and tags its subscribers can be edited, paused, resumed and deleted', async ({ freshAccount, browser, mailpit }) => {
    const { api, organizerId } = freshAccount;
    await buildAudience(api, organizerId);
    await api.updateOrganizerStatus(organizerId, 'LIVE');
    const name = uniqueShort('Backstage list');
    const tag = uniqueShort('Newsletter');
    const email = uniqueEmail('named');

    const page = await freshAccount.newAuthedPage();
    await new AudiencePage(page, organizerId).gotoForms();
    await page.getByTestId('signup-form-create-button').click();
    const editor = page.getByRole('dialog', { name: 'New signup form' });
    await page.getByTestId('signup-form-name').fill(name);
    await editor.getByRole('switch', { name: 'Ask for their name' }).check();
    await editor.getByRole('combobox', { name: 'Tag new subscribers' }).fill(tag);
    await editor.getByRole('combobox', { name: 'Tag new subscribers' }).press('Enter');
    await expect(editor.getByText(tag, { exact: true })).toBeVisible();
    await editor.getByRole('textbox', { name: 'Privacy policy link' }).fill('example.com/privacy');
    await page.getByTestId('signup-form-save').click();
    await expect(editor.getByText('Enter a link that starts with https://')).toBeVisible();
    await editor.getByRole('textbox', { name: 'Privacy policy link' }).fill('https://example.com/privacy');
    await page.getByTestId('signup-form-save').click();

    const share = page.getByRole('dialog', { name: `Share "${name}"` });
    const formPath = new URL(await share.getByTestId('signup-form-link').inputValue()).pathname;
    await share.getByRole('button', { name: 'Close' }).click();
    const row = page.getByTestId('signup-form-row').filter({ hasText: name });

    const visitor = await browser.newPage();
    try {
      await visitor.goto(formPath);
      await expect(visitor.getByRole('link', { name: 'Privacy policy' })).toHaveAttribute('href', 'https://example.com/privacy');
      await visitor.getByRole('textbox', { name: 'First name' }).fill('Nell');
      await visitor.getByRole('textbox', { name: 'Last name' }).fill('Named');
      await visitor.getByTestId('signup-form-email').fill(email);
      await visitor.getByTestId('signup-form-submit').click();
      await expect(visitor.getByTestId('signup-form-check-inbox')).toContainText(email);
      await page.reload();
      await expect(row.getByTestId('signup-form-waiting')).toHaveText('1');

      const confirmUrl = await mailpit.waitForLink(email, CONFIRM_LINK, { timeoutMs: 45_000, subjectContains: 'Confirm your subscription' });
      await visitor.goto(confirmUrl.pathname);
      await visitor.getByTestId('subscription-confirm-button').click();
      await expect(visitor.getByRole('heading', { name: "You're subscribed" })).toBeVisible();

      await expect.poll(async () => (await api.findAudienceContacts(organizerId, email))[0]?.full_name).toBe('Nell Named');
      const audience = new AudiencePage(page, organizerId);
      await audience.gotoContactsMatching(email);
      await expect(audience.contactRow('Nell Named')).toContainText(tag);

      await audience.gotoForms();
      await row.getByRole('button', { name: 'Form actions' }).click();
      await page.getByRole('menuitem', { name: 'Edit' }).click();
      await page.getByTestId('signup-form-title').fill('Backstage news');
      await page.getByTestId('signup-form-save').click();
      await expect(page.getByText('Form saved')).toBeVisible();
      await visitor.goto(formPath);
      await expect(visitor.getByRole('heading', { name: 'Backstage news' })).toBeVisible();

      await row.getByRole('button', { name: 'Form actions' }).click();
      await page.getByRole('menuitem', { name: 'Pause' }).click();
      await expect(page.getByText('Form paused')).toBeVisible();
      await expect(row).toContainText('Paused');
      await expect(row).toContainText('Resume this form to share it');
      await expect(row.getByTestId('signup-form-share')).toBeDisabled();
      await visitor.goto(formPath);
      await expect(visitor.getByRole('heading', { name: "This form isn't available" })).toBeVisible();
      await expect(visitor.getByTestId('signup-form-email')).toHaveCount(0);

      await row.getByRole('button', { name: 'Form actions' }).click();
      await page.getByRole('menuitem', { name: 'Resume' }).click();
      await expect(row).toContainText('Live');
      await visitor.goto(formPath);
      await expect(visitor.getByTestId('signup-form-email')).toBeVisible();

      await row.getByRole('button', { name: 'Form actions' }).click();
      await page.getByRole('menuitem', { name: 'Delete' }).click();
      await page.getByRole('dialog', { name: `Delete the form "${name}"?` }).getByRole('button', { name: 'Delete form' }).click();
      await expect(row).toHaveCount(0);
      await visitor.goto(formPath);
      await expect(visitor.getByRole('heading', { name: "This form isn't available" })).toBeVisible();
    } finally {
      await visitor.close();
    }
    expect(await marketingStatusOf(api, organizerId, email)).toBe('SUBSCRIBED');
  });

  test('a confirm link that has expired says so and offers to sign up again, and a used link says they are subscribed', async ({ freshAccount, playwright, publicApi, browser, mailpit }) => {
    const form = await arrangeLiveForm(freshAccount, playwright, uniqueShort('Slow news'));
    const { api, organizerId } = freshAccount;
    const late = uniqueEmail('too-late');
    const prompt = uniqueEmail('prompt');
    await submitSignupForm(publicApi, form.public_id, late);
    await submitSignupForm(publicApi, form.public_id, prompt);
    const [lateLink] = await confirmLinks(mailpit, late, 1);
    const [promptLink] = await confirmLinks(mailpit, prompt, 1);
    expirePendingSubscription(late);

    const visitor = await browser.newPage();
    try {
      await visitor.goto(lateLink);
      await expect(visitor.getByRole('heading', { name: 'This link has expired' })).toBeVisible();
      await expect(visitor.getByText('Confirmation links work for 7 days. Sign up again to get a new link.')).toBeVisible();
      await expect(visitor.getByTestId('subscription-confirm-button')).toHaveCount(0);
      await visitor.getByRole('link', { name: 'Sign up again' }).click();
      await expect(visitor).toHaveURL(new RegExp(`/subscribe/${form.public_id}$`));
      await expect(visitor.getByRole('heading', { name: 'Hear about new shows' })).toBeVisible();
      await expect(visitor.getByTestId('signup-form-email')).toBeVisible();

      await visitor.goto(promptLink);
      await visitor.getByTestId('subscription-confirm-button').click();
      await expect(visitor.getByRole('heading', { name: "You're subscribed" })).toBeVisible();
      await visitor.goto(promptLink);
      await expect(visitor.getByRole('heading', { name: "You're subscribed" })).toBeVisible();
      await expect(visitor.getByTestId('subscription-confirm-button')).toHaveCount(0);
    } finally {
      await visitor.close();
    }

    await expect.poll(() => marketingStatusOf(api, organizerId, prompt)).toBe('SUBSCRIBED');
    expect(await api.findAudienceContacts(organizerId, late)).toHaveLength(0);
  });

  test('the form answers the same way for a new address, a subscriber and someone who unsubscribed, who is subscribed again once they confirm', async ({ freshAccount, playwright, publicApi, browser, mailpit }) => {
    const { api, organizerId } = freshAccount;
    const event = await createLiveEventWithFreeTicket(api, organizerId);
    const subscriber = uniqueEmail('still-in');
    const leaver = uniqueEmail('came-back');
    await createOptedInOrder(publicApi, event, { email: subscriber, firstName: 'Stella', lastName: 'Stillin' });
    await createOptedInOrder(publicApi, event, { email: leaver, firstName: 'Cam', lastName: 'Cameback' });
    const name = uniqueShort('Comeback news');
    const form = await arrangeLiveForm(freshAccount, playwright, name);
    await withMessagingApi(playwright, freshAccount, async (messaging) =>
      messaging.unsubscribeContact(await contactIdOf(api, organizerId, leaver)));
    await expect.poll(() => marketingStatusOf(api, organizerId, leaver)).toBe('UNSUBSCRIBED');

    const visitor = await browser.newPage();
    try {
      const answers: string[] = [];
      for (const email of [uniqueEmail('brand-new'), subscriber, leaver]) {
        await visitor.goto(`/subscribe/${form.public_id}`);
        await visitor.getByTestId('signup-form-email').fill(email);
        await visitor.getByTestId('signup-form-submit').click();
        await expect(visitor.getByTestId('signup-form-check-inbox')).toContainText(email);
        answers.push((await visitor.getByTestId('signup-form-check-inbox').innerText()).replace(email, '<email>'));
      }
      expect(new Set(answers).size).toBe(1);
      expect(answers[0]).toContain('Check your inbox');

      const confirmUrl = await mailpit.waitForLink(leaver, CONFIRM_LINK, { timeoutMs: 45_000, subjectContains: 'Confirm your subscription' });
      expect(await marketingStatusOf(api, organizerId, leaver)).toBe('UNSUBSCRIBED');
      await visitor.goto(confirmUrl.pathname);
      await visitor.getByTestId('subscription-confirm-button').click();
      await expect(visitor.getByRole('heading', { name: "You're subscribed" })).toBeVisible();
    } finally {
      await visitor.close();
    }
    await expect.poll(() => marketingStatusOf(api, organizerId, leaver)).toBe('SUBSCRIBED');

    const page = await freshAccount.newAuthedPage();
    const audience = new AudiencePage(page, organizerId);
    await audience.gotoContactsMatching(leaver);
    await audience.contactRow('Cam Cameback').click();
    await expect(audience.marketingStatus()).toContainText('Subscribed');
    await expect(audience.drawerSection('Consent history')).toContainText(name);
  });

  test('a form with its own button label and thank-you message shows them to the visitor', async ({ freshAccount, browser, mailpit }) => {
    const { api, organizerId } = freshAccount;
    await buildAudience(api, organizerId);
    await api.updateOrganizerStatus(organizerId, 'LIVE');
    const name = uniqueShort('Insiders');
    const email = uniqueEmail('insider');

    const page = await freshAccount.newAuthedPage();
    await new AudiencePage(page, organizerId).gotoForms();
    await expect(page.getByText('No signup forms yet. Create one to collect subscribers from a link, your website or your organizer page.')).toBeVisible();
    await page.getByTestId('signup-form-create-button').click();
    const editor = page.getByRole('dialog', { name: 'New signup form' });
    await page.getByTestId('signup-form-name').fill('');
    await page.getByTestId('signup-form-save').click();
    await expect(editor.getByText('Give this form a name')).toBeVisible();
    await page.getByTestId('signup-form-name').fill(name);
    await editor.getByRole('textbox', { name: 'Button label' }).fill('Count me in');
    await expect(editor.locator('[inert]').getByTestId('signup-form-submit')).toHaveText('Count me in');
    await editor.getByRole('textbox', { name: 'Message after confirming' }).fill('Welcome aboard. Look out for our Friday email.');
    await page.getByTestId('signup-form-save').click();

    const share = page.getByRole('dialog', { name: `Share "${name}"` });
    const formPath = new URL(await share.getByTestId('signup-form-link').inputValue()).pathname;
    await expect(share.getByRole('link', { name: 'Open the page' })).toHaveAttribute('href', new RegExp(`${formPath}$`));
    await expect(share.getByRole('img', { name: 'QR code for the form link' })).toBeVisible();

    const visitor = await browser.newPage();
    try {
      await visitor.goto(formPath);
      await visitor.getByTestId('signup-form-email').fill(email);
      await expect(visitor.getByTestId('signup-form-submit')).toHaveText('Count me in');
      await visitor.getByTestId('signup-form-submit').click();
      const confirmUrl = await mailpit.waitForLink(email, CONFIRM_LINK, { timeoutMs: 45_000, subjectContains: 'Confirm your subscription' });
      await visitor.goto(confirmUrl.pathname);
      await visitor.getByTestId('subscription-confirm-button').click();
      await expect(visitor.getByRole('heading', { name: "You're subscribed" })).toBeVisible();
      await expect(visitor.getByText('Welcome aboard. Look out for our Friday email.')).toBeVisible();
    } finally {
      await visitor.close();
    }
  });

  test('on a phone the forms list fits, and a visitor signs up and confirms', async ({ freshAccount, playwright, browser, mailpit, baseURL }) => {
    const name = uniqueShort('Pocket news');
    const form = await arrangeLiveForm(freshAccount, playwright, name);
    const email = uniqueEmail('pocket');

    const visitorContext = await browser.newContext({ ignoreHTTPSErrors: true, viewport: PHONE_VIEWPORT, baseURL });
    try {
      const visitor = await visitorContext.newPage();
      await visitor.goto(`/subscribe/${form.public_id}`);
      await expect(visitor.getByRole('heading', { name: 'Hear about new shows' })).toBeVisible();
      await expectNoSidewaysScroll(visitor);
      await visitor.getByTestId('signup-form-email').fill(email);
      await visitor.getByTestId('signup-form-submit').click();
      await expect(visitor.getByTestId('signup-form-check-inbox')).toContainText(email);
      await expectNoSidewaysScroll(visitor);

      const confirmUrl = await mailpit.waitForLink(email, CONFIRM_LINK, { timeoutMs: 45_000, subjectContains: 'Confirm your subscription' });
      await visitor.goto(confirmUrl.pathname);
      await expect(visitor.getByTestId('subscription-confirm-button')).toBeInViewport();
      await expectNoSidewaysScroll(visitor);
      await visitor.getByTestId('subscription-confirm-button').click();
      await expect(visitor.getByRole('heading', { name: "You're subscribed" })).toBeVisible();
    } finally {
      await visitorContext.close();
    }

    const page = await freshAccount.newAuthedPage();
    await page.setViewportSize(PHONE_VIEWPORT);
    await new AudiencePage(page, freshAccount.organizerId).gotoForms();
    const row = page.getByTestId('signup-form-row').filter({ hasText: name });
    await expect(row.getByTestId('signup-form-subscribers')).toHaveText('1');
    await expect(row).toContainText('Live');
    await expectNoSidewaysScroll(page);
    await row.getByTestId('signup-form-share').click();
    const share = page.getByRole('dialog', { name: `Share "${name}"` });
    await expect(share.getByTestId('signup-form-link')).toHaveValue(new RegExp(`/subscribe/${form.public_id}$`));
    await expectNoSidewaysScroll(page);
  });
});
