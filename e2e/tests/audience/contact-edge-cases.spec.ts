import type { APIRequest, APIRequestContext, Page } from '@playwright/test';
import { expect, type FreshAccount } from '../../fixtures';
import type { AdminApiClient } from '../../api/api-client';
import { createLiveEventWithFreeTicket } from '../../api/factory';
import { AudiencePage } from '../../pages/audience.page';
import type { MailpitClient } from '../../utils/mailpit';
import { uniqueEmail, uniqueShort } from '../../utils/unique';
import { buildAudience, createOptedInOrder, marketingStatusOf } from './audience.shared';
import { withContactsApi } from './contacts.helpers';
import { emailsAbout, oneClickUnsubscribeUrlOf } from './coverage.helpers';
import { approveIfHeldForReview, contactIdOf, test, waitForCampaignStatus, type MessagingApi } from './messaging.shared';

interface Arranged {
  freshAccount: FreshAccount;
  publicApi: APIRequestContext;
  adminApi: AdminApiClient;
  messaging: MessagingApi;
  mailpit: MailpitClient;
  playwright: { request: APIRequest };
}

const sendToSubscriberThenDeleteThem = async ({ freshAccount, publicApi, adminApi, messaging, mailpit, playwright }: Arranged): Promise<{ email: string; subject: string }> => {
  const { api, organizerId } = freshAccount;
  const event = await createLiveEventWithFreeTicket(api, organizerId);
  const email = uniqueEmail('left-late');
  await createOptedInOrder(publicApi, event, { email, firstName: 'Lena', lastName: 'Leftlate' });
  await buildAudience(api, organizerId);
  await adminApi.grantMessagingPrivileges((await api.getAccount()).id);
  const contactId = await contactIdOf(api, organizerId, email);
  const subject = uniqueShort('Before they left');
  const campaign = await messaging.createCampaign({ name: subject, subject, body: '<p>See what is on next month.</p>' });
  await messaging.sendCampaign(campaign.id);
  await approveIfHeldForReview(adminApi, api, organizerId, campaign.id);
  await waitForCampaignStatus(api, organizerId, campaign.id, /^SENT$/);
  await mailpit.waitForMessage(email, { subjectContains: subject, timeoutMs: 45_000 });
  expect(await marketingStatusOf(api, organizerId, email)).toBe('SUBSCRIBED');
  await withContactsApi(playwright, freshAccount, (contactsApi) => contactsApi.deleteContact(contactId));
  await expect.poll(async () => (await api.findAudienceContacts(organizerId, email)).length).toBe(0);
  return { email, subject };
};

const expectRestoredContactStaysUnsubscribed = async (page: Page, freshAccount: FreshAccount, mailpit: MailpitClient, email: string): Promise<void> => {
  const { api, organizerId } = freshAccount;
  const audience = new AudiencePage(page, organizerId);
  await audience.gotoContacts();
  await audience.addContact(email, 'Lena', 'Leftlate');
  await page.getByRole('dialog', { name: 'Restore this person?' }).getByRole('button', { name: 'Restore contact' }).click();
  await expect(audience.drawer()).toContainText(email);
  await expect(audience.drawerSection('Activity').getByText('Placed an order', { exact: true })).toHaveCount(1);
  await expect(audience.marketingStatus()).toContainText('Unsubscribed');
  await expect(audience.marketingStatus()).toContainText('Asked not to receive marketing emails. Campaigns skip them.');
  expect(await marketingStatusOf(api, organizerId, email)).toBe('UNSUBSCRIBED');
  await page.keyboard.press('Escape');
  await expect(audience.drawer()).toBeHidden();

  const second = uniqueShort('After they left');
  await audience.composeCampaign(second, 'They asked not to get this.');
  await expect(page.getByTestId('campaign-recipient-count')).toContainText('0 of 1 opted in');
  await page.getByTestId('campaign-review-send').click();
  const send = page.getByRole('dialog', { name: `Send "${second}"` });
  await expect(send.getByTestId('campaign-send-summary'))
    .toHaveText('Nobody in this segment has opted in to marketing emails, so there is no one to send to.');
  await expect(send.getByTestId('campaign-send-confirm')).toBeDisabled();
  expect(await emailsAbout(mailpit, email, second)).toBe(0);
};

test.describe('audience contact edge cases', () => {
  test('someone who presses unsubscribe in their mail app after their contact was deleted stays unsubscribed when the contact is restored', async ({ freshAccount, publicApi, adminApi, mailpit, messaging, request, playwright }) => {
    test.slow();
    const { api, organizerId } = freshAccount;
    const { email, subject } = await sendToSubscriberThenDeleteThem({ freshAccount, publicApi, adminApi, messaging, mailpit, playwright });
    const oneClick = await oneClickUnsubscribeUrlOf(request, mailpit, email, subject);

    const unsubscribed = await publicApi.post(oneClick, { form: { 'List-Unsubscribe': 'One-Click' } });
    expect(unsubscribed.ok()).toBe(true);
    expect((await api.findAudienceContacts(organizerId, email)).length).toBe(0);

    const page = await freshAccount.newAuthedPage();
    await expectRestoredContactStaysUnsubscribed(page, freshAccount, mailpit, email);
  });

  test('someone who opens the unsubscribe link after their contact was deleted stays unsubscribed when the contact is restored', async ({ freshAccount, publicApi, adminApi, mailpit, messaging, browser, playwright }) => {
    test.slow();
    const { api, organizerId } = freshAccount;
    const { email, subject } = await sendToSubscriberThenDeleteThem({ freshAccount, publicApi, adminApi, messaging, mailpit, playwright });
    const unsubscribeUrl = await mailpit.waitForLink(email, /audience\/unsubscribe/, { subjectContains: subject, timeoutMs: 45_000 });

    const visitor = await browser.newPage();
    try {
      await visitor.goto(unsubscribeUrl.toString());
      await expect(visitor.getByRole('heading', { name: /^Unsubscribe from / })).toBeVisible();
      await expect(visitor.getByText(/d•+@/)).toHaveCount(0);
      await visitor.getByTestId('audience-unsubscribe-button').click();
      await expect(visitor.getByRole('heading', { name: "You're unsubscribed" })).toBeVisible();
      await expect(visitor.getByText(/won't send you marketing emails any more\./)).toBeVisible();
      await expect(visitor.getByTestId('audience-resubscribe-button')).toHaveCount(0);
    } finally {
      await visitor.close();
    }
    expect((await api.findAudienceContacts(organizerId, email)).length).toBe(0);

    const page = await freshAccount.newAuthedPage();
    await expectRestoredContactStaysUnsubscribed(page, freshAccount, mailpit, email);
  });

  test('a campaign someone was sent shows in their activity, and a tag is taken off them from their profile', async ({ freshAccount, publicApi, adminApi, mailpit, messaging }) => {
    test.slow();
    const { api, organizerId } = freshAccount;
    const event = await createLiveEventWithFreeTicket(api, organizerId);
    const email = uniqueEmail('tagged-reader');
    await createOptedInOrder(publicApi, event, { email, firstName: 'Tara', lastName: 'Taggedreader' });
    await buildAudience(api, organizerId);
    await adminApi.grantMessagingPrivileges((await api.getAccount()).id);
    const contactId = await contactIdOf(api, organizerId, email);
    const kept = await messaging.createTag(uniqueShort('Members'));
    const dropped = await messaging.createTag(uniqueShort('Press'));
    await messaging.tagContact(contactId, [kept.id, dropped.id]);
    const subject = uniqueShort('Programme news');
    const campaign = await messaging.createCampaign({ name: subject, subject, body: '<p>The autumn programme is out.</p>' });
    await messaging.sendCampaign(campaign.id);
    await approveIfHeldForReview(adminApi, api, organizerId, campaign.id);
    await waitForCampaignStatus(api, organizerId, campaign.id, /^SENT$/);
    await mailpit.waitForMessage(email, { subjectContains: subject, timeoutMs: 45_000 });

    const page = await freshAccount.newAuthedPage();
    const audience = new AudiencePage(page, organizerId);
    await audience.gotoContactsMatching(email);
    await expect(audience.contactRow('Tara Taggedreader')).toContainText(dropped.name);
    await audience.contactRow('Tara Taggedreader').click();
    await expect(audience.drawerSection('Activity').getByText(`Sent the email "${subject}"`, { exact: true })).toHaveCount(1);
    await expect(audience.drawerSection('Activity').getByText('Placed an order', { exact: true })).toHaveCount(1);

    const tags = audience.drawerSection('Tags');
    await expect(tags).toContainText(kept.name);
    await expect(tags).toContainText(dropped.name);
    await audience.tagInput().click();
    await page.getByRole('option', { name: dropped.name, exact: true }).click();
    await page.keyboard.press('Escape');
    await expect(tags).not.toContainText(dropped.name);
    await expect(tags).toContainText(kept.name);
    await page.reload();
    await expect(audience.drawerSection('Tags')).toContainText(kept.name);
    await expect(audience.drawerSection('Tags')).not.toContainText(dropped.name);
    await page.keyboard.press('Escape');
    await expect(audience.drawer()).toBeHidden();
    await expect(audience.contactRow('Tara Taggedreader')).toContainText(kept.name);
    await expect(audience.contactRow('Tara Taggedreader')).not.toContainText(dropped.name);
  });

  test('a saved link to a filter that no longer works says why, and clearing the filter brings the contacts back', async ({ freshAccount, publicApi }) => {
    const { api, organizerId } = freshAccount;
    const event = await createLiveEventWithFreeTicket(api, organizerId);
    await createOptedInOrder(publicApi, event, { email: uniqueEmail('listed'), firstName: 'Lia', lastName: 'Listed' });
    await buildAudience(api, organizerId);
    const staleFilter = JSON.stringify({ match: 'all', rules: [{ field: 'events_count', operator: 'gte', value: 5_000_000_000_000 }] });

    const page = await freshAccount.newAuthedPage();
    const audience = new AudiencePage(page, organizerId);
    await audience.gotoContacts(`?filter=${encodeURIComponent(staleFilter)}`);
    const rejected = page.getByRole('alert').filter({ hasText: "This filter can't be applied." });
    await expect(rejected).toContainText('Enter a whole number.');
    await expect(audience.contactRows()).toHaveCount(0);
    await expect(page.getByText("We couldn't load these contacts.")).toHaveCount(0);
    await expect(audience.filterPanel()).toBeVisible();
    await expect(audience.filterPanel().getByRole('button', { name: 'Save as segment' })).toHaveCount(0);

    await rejected.getByRole('button', { name: 'Clear filter' }).click();
    await expect(page).not.toHaveURL(/filter=/);
    await expect(rejected).toHaveCount(0);
    await expect(audience.contactTotal()).toHaveText('1 contact');
    await expect(audience.contactRow('Lia Listed')).toBeVisible();
  });

  test('when an audience refresh fails the contacts stay on screen with a notice, and trying again clears it', async ({ freshAccount, publicApi }) => {
    const { api, organizerId } = freshAccount;
    const event = await createLiveEventWithFreeTicket(api, organizerId);
    await createOptedInOrder(publicApi, event, { email: uniqueEmail('still-here'), firstName: 'Stella', lastName: 'Stillhere' });
    await buildAudience(api, organizerId);

    const page = await freshAccount.newAuthedPage();
    const syncStatus = `**/api/organizers/${organizerId}/crm/sync`;
    await page.route(syncStatus, async (route) => {
      if (route.request().method() !== 'GET') {
        await route.fallback();
        return;
      }
      const response = await route.fetch();
      const body = (await response.json()) as { data: Record<string, unknown> };
      await route.fulfill({ response, json: { ...body, data: { ...body.data, status: 'FAILED' } } });
    });
    const audience = new AudiencePage(page, organizerId);
    await audience.gotoContacts();
    const notice = page.getByRole('complementary').filter({ hasText: "The last audience refresh didn't finish" });
    await expect(notice).toContainText('Your audience is still here and new orders keep being added. Some older details may be out of date until a refresh finishes.');
    await expect(audience.contactTotal()).toHaveText('1 contact');
    await expect(audience.contactRow('Stella Stillhere')).toBeVisible();
    await expect(audience.startButton()).toHaveCount(0);
    await expect(page.getByRole('heading', { name: 'See who your audience really is' })).toHaveCount(0);
    await audience.gotoOverview();
    await expect(notice).toBeVisible();
    await expect(audience.startButton()).toHaveCount(0);

    await page.unroute(syncStatus);
    const restarted = page.waitForResponse((response) => response.url().endsWith(`/crm/sync`) && response.request().method() === 'POST');
    await page.getByTestId('audience-refresh-retry-button').click();
    expect((await restarted).ok()).toBe(true);
    await expect(notice).toHaveCount(0, { timeout: 30_000 });
    await audience.gotoContacts();
    await expect(audience.contactRow('Stella Stillhere')).toBeVisible();
    await expect(notice).toHaveCount(0);
  });
});
