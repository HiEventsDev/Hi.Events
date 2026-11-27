import type { APIRequestContext, Browser, Page } from '@playwright/test';
import { expect, type FreshAccount } from '../../fixtures';
import type { AdminApiClient } from '../../api/api-client';
import { createCompletedOrder, createLiveEventWithFreeTicket } from '../../api/factory';
import { AudiencePage } from '../../pages/audience.page';
import type { MailpitClient } from '../../utils/mailpit';
import { nonSaasOnly, saasOnly } from '../../utils/mode';
import { uniqueEmail, uniqueShort } from '../../utils/unique';
import { releaseScheduledCampaigns } from '../../utils/artisan';
import { moveScheduledCampaignIntoThePast } from '../../utils/audience-db';
import { buildAudience, createOptedInOrder, marketingStatusOf, waitForContact } from './audience.shared';
import { expectNoSidewaysScroll, PHONE_VIEWPORT } from './contacts.helpers';
import { approveIfHeldForReview, test, tomorrow, waitForCampaignStatus, type MessagingApi } from './messaging.shared';

interface OptedInAudience {
  subscriber: string;
  silent: string;
}

const arrangeOptedInAudience = async (
  freshAccount: FreshAccount,
  publicApi: APIRequestContext,
  adminApi: AdminApiClient,
): Promise<OptedInAudience> => {
  const { api, organizerId } = freshAccount;
  const event = await createLiveEventWithFreeTicket(api, organizerId);
  const subscriber = uniqueEmail('subscriber');
  const silent = uniqueEmail('silent');
  await createOptedInOrder(publicApi, event, { email: subscriber, firstName: 'Sasha', lastName: 'Subscriber' });
  await createCompletedOrder(publicApi, event, { buyerEmail: silent, buyerFirstName: 'Quinn', buyerLastName: 'Quiet' });
  await buildAudience(api, organizerId);
  await adminApi.grantMessagingPrivileges((await api.getAccount()).id);
  return { subscriber, silent };
};

const composeAndSend = async (page: Page, organizerId: number, subject: string): Promise<{ audience: AudiencePage; campaignId: number }> => {
  const audience = new AudiencePage(page, organizerId);
  await audience.composeCampaign(subject, 'Tickets for our next night are ready for you.');
  await expect(page.getByTestId('campaign-recipient-count')).toContainText('1 of 2 opted in');
  await expect(page.getByTestId('campaign-preview')).toBeVisible();
  return { audience, campaignId: await audience.sendCampaignNow() };
};

const expectSentReport = async (page: Page, audience: AudiencePage, recipient: string): Promise<void> => {
  await expect(audience.campaignStatus()).toContainText('Sent');
  await expect(page.getByTestId('campaign-recipient-count')).toContainText('Everyone who opted in');
  await expect(page.getByTestId('campaign-recipient-count')).toContainText('1 recipient');
  await expect(page.getByTestId('campaign-from')).toContainText('Replies go to');
  await expect(page.getByText('0 skipped or failed')).toBeVisible();
  await expect(page.getByText('What was sent')).toBeVisible();
  for (const action of ['Save draft', 'Send', 'Delete', 'Send me a test', 'Stop sending']) {
    await expect(page.getByRole('button', { name: action, exact: true })).toHaveCount(0);
  }

  const filters = page.getByRole('radiogroup', { name: 'Filter recipients' });
  await expect(filters.getByRole('radio', { name: 'Everyone · 1' })).toBeChecked();
  await expect(page.getByTestId('campaign-recipient-row')).toHaveCount(1);
  await expect(page.getByTestId('campaign-recipient-row')).toContainText(recipient);
  await filters.getByText('Not sent · 0').click();
  await expect(filters.getByRole('radio', { name: 'Not sent · 0' })).toBeChecked();
  await expect(page.getByText('Nobody here yet.')).toBeVisible();
  await expect(page.getByTestId('campaign-recipient-row')).toHaveCount(0);
  await filters.getByText('Everyone · 1').click();
  await expect(page.getByTestId('campaign-recipient-row')).toHaveCount(1);
};

const scheduleThroughTheApi = async (
  { messaging, adminApi, freshAccount, subject }: { messaging: MessagingApi; adminApi: AdminApiClient; freshAccount: FreshAccount; subject: string },
): Promise<number> => {
  const { api, organizerId } = freshAccount;
  const draft = await messaging.createCampaign({ name: subject, subject, body: '<p>Doors at seven. See you there.</p>' });
  await messaging.sendCampaign(draft.id, tomorrow());
  await approveIfHeldForReview(adminApi, api, organizerId, draft.id);
  await waitForCampaignStatus(api, organizerId, draft.id, /^SCHEDULED$/);
  return draft.id;
};

const expectDeliveredOnlyToSubscriberWhoCanResubscribe = async (
  { page, audience, browser, mailpit, freshAccount, people, subject }: {
    page: Page;
    audience: AudiencePage;
    browser: Browser;
    mailpit: MailpitClient;
    freshAccount: FreshAccount;
    people: OptedInAudience;
    subject: string;
  },
): Promise<void> => {
  const { api, organizerId } = freshAccount;
  const unsubscribeUrl = await mailpit.waitForLink(people.subscriber, /audience\/unsubscribe/, { subjectContains: subject, timeoutMs: 45_000 });
  expect((await mailpit.search(people.silent)).filter((message) => message.Subject.includes(subject))).toHaveLength(0);

  await waitForCampaignStatus(api, organizerId, audience.campaignIdFromUrl(), /^SENT$/);
  await page.reload();
  await expectSentReport(page, audience, people.subscriber);

  await audience.gotoCampaigns();
  await page.getByRole('radiogroup', { name: 'Filter campaigns' }).getByText('Sent · 1', { exact: true }).click();
  await expect(page).toHaveURL(/folder=sent/);
  await expect(audience.campaignRow(subject)).toContainText('Sent');
  await expect(audience.campaignRow(subject).getByRole('cell', { name: '1', exact: true })).toBeVisible();

  const visitor = await browser.newContext({ ignoreHTTPSErrors: true });
  try {
    const unsubscribePage = await visitor.newPage();
    await unsubscribePage.goto(unsubscribeUrl.toString());
    await unsubscribePage.getByTestId('audience-unsubscribe-button').click();
    await expect(unsubscribePage.getByText("You're unsubscribed")).toBeVisible();
    await expect.poll(() => marketingStatusOf(api, organizerId, people.subscriber)).toBe('UNSUBSCRIBED');

    await unsubscribePage.getByTestId('audience-resubscribe-button').click();
    await expect(unsubscribePage.getByText("You're subscribed again")).toBeVisible();
  } finally {
    await visitor.close();
  }

  await expect.poll(() => marketingStatusOf(api, organizerId, people.subscriber)).toBe('SUBSCRIBED');
  await audience.gotoContactsMatching(people.subscriber);
  await audience.contactRow('Sasha Subscriber').click();
  await expect(audience.marketingStatus()).toContainText('Subscribed');
  await expect(audience.drawerSection('Consent history')).toContainText('Resubscribed on the unsubscribe page');
  await expect(audience.drawerSection('Consent history')).toContainText(`Unsubscribe link in "${subject}"`);
};

test.describe('audience campaigns', () => {
  test('on Hi.Events Cloud a first campaign waits for review, then reaches only people who opted in, who can unsubscribe and resubscribe', async ({ freshAccount, publicApi, mailpit, adminApi, browser }) => {
    saasOnly();
    const people = await arrangeOptedInAudience(freshAccount, publicApi, adminApi);
    const subject = uniqueShort('First dibs');
    const page = await freshAccount.newAuthedPage();
    const { audience, campaignId } = await composeAndSend(page, freshAccount.organizerId, subject);

    await expect(audience.campaignStatus()).toContainText('Waiting for review');
    await adminApi.approveAudienceCampaign(campaignId);

    await expectDeliveredOnlyToSubscriberWhoCanResubscribe({ page, audience, browser, mailpit, freshAccount, people, subject });
  });

  test('a self-hosted campaign goes straight out to people who opted in, who can unsubscribe and resubscribe', { tag: '@smoke' }, async ({ freshAccount, publicApi, mailpit, adminApi, browser }) => {
    nonSaasOnly();
    const people = await arrangeOptedInAudience(freshAccount, publicApi, adminApi);
    const subject = uniqueShort('First dibs');
    const page = await freshAccount.newAuthedPage();
    const { audience, campaignId } = await composeAndSend(page, freshAccount.organizerId, subject);

    await expect.poll(async () => (await freshAccount.api.getAudienceCampaign(freshAccount.organizerId, campaignId)).status)
      .toMatch(/^(SENDING|SENT)$/);

    await expectDeliveredOnlyToSubscriberWhoCanResubscribe({ page, audience, browser, mailpit, freshAccount, people, subject });
  });

  test('a draft can be saved, edited, test-sent to yourself and found by search', async ({ freshAccount, publicApi, mailpit, adminApi }) => {
    await arrangeOptedInAudience(freshAccount, publicApi, adminApi);
    const page = await freshAccount.newAuthedPage();
    const audience = new AudiencePage(page, freshAccount.organizerId);
    const subject = uniqueShort('Draft idea');
    const edited = uniqueShort('Edited idea');

    await audience.startBlankCampaign();
    await page.getByTestId('campaign-review-send').click();
    await expect(page.getByRole('alert').filter({ hasText: 'Add a subject line' })).toBeVisible();
    await expect(page.getByTestId('campaign-subject')).toHaveAttribute('aria-invalid', 'true');
    await expect(page.getByTestId('campaign-subject')).toHaveAttribute('aria-describedby', 'campaign-subject-error');
    await expect(page).toHaveURL(/\/audience\/campaigns\/new/);
    await page.getByTestId('campaign-subject').fill(subject);

    await page.getByTestId('campaign-body').fill('A first rough version.');
    await audience.tab('Campaigns').click();
    const leave = page.getByRole('dialog').filter({ hasText: 'Leave without saving? Your campaign changes will be lost.' });
    await leave.getByRole('button', { name: 'Cancel', exact: true }).click();
    await expect(page.getByTestId('campaign-subject')).toHaveValue(subject);

    await page.getByRole('button', { name: 'Save draft' }).click();
    await page.waitForURL(/\/audience\/campaigns\/\d+$/);
    await expect(page.getByText('Draft saved')).toBeVisible();
    await expect(audience.campaignStatus()).toContainText('Draft');

    await page.reload();
    await expect(page.getByTestId('campaign-subject')).toHaveValue(subject);
    await page.getByTestId('campaign-subject').fill(edited);
    await page.getByRole('button', { name: 'Save draft' }).click();
    await expect(page.getByText('Draft saved')).toBeVisible();

    await page.getByRole('button', { name: 'Send me a test' }).click();
    const testEmail = await mailpit.waitForMessage(freshAccount.email, { subjectContains: edited, timeoutMs: 45_000 });
    expect(testEmail.Subject).toBe(`[Test] ${edited}`);
    await expect(audience.campaignStatus()).toContainText('Draft');

    await audience.gotoCampaigns();
    await expect(audience.campaignRow(edited)).toContainText('Draft');
    await page.getByTestId('campaign-search').fill('no campaign has this subject');
    await expect(page.getByText('No campaigns match your search.')).toBeVisible();
    await page.getByTestId('campaign-search').fill(edited);
    await expect(audience.campaignRow(edited)).toHaveCount(1);
    await expect(page).toHaveURL(/query=/);
  });

  test('sending a campaign with no message says to write one first', async ({ freshAccount, publicApi, adminApi }) => {
    await arrangeOptedInAudience(freshAccount, publicApi, adminApi);
    const page = await freshAccount.newAuthedPage();
    const audience = new AudiencePage(page, freshAccount.organizerId);
    await audience.startBlankCampaign();
    await page.getByTestId('campaign-subject').fill(uniqueShort('No message'));
    await page.getByTestId('campaign-review-send').click();
    await expect(page.getByText('Write a message before sending')).toBeVisible();
    await page.getByTestId('campaign-review-send').click();
    await expect(page.getByText('Write a message before sending')).toBeVisible();
    await expect(page.getByRole('dialog')).toHaveCount(0);
    await expect(page).toHaveURL(/\/audience\/campaigns\/new/);
  });

  test('a draft is deleted from its page and leaves the campaign list', async ({ freshAccount, publicApi, adminApi }) => {
    await arrangeOptedInAudience(freshAccount, publicApi, adminApi);
    const page = await freshAccount.newAuthedPage();
    const audience = new AudiencePage(page, freshAccount.organizerId);
    const subject = uniqueShort('Throwaway');

    await audience.composeCampaign(subject, 'Not worth sending.');
    await page.getByRole('button', { name: 'Save draft' }).click();
    await page.waitForURL(/\/audience\/campaigns\/\d+$/);

    await page.getByRole('button', { name: 'Delete', exact: true }).click();
    await expect(page.getByRole('dialog', { name: 'Delete this draft?' })).toContainText("This can't be undone.");
    await audience.confirm('Delete draft');
    await expect(page).toHaveURL(/\/audience\/campaigns$/);
    await expect(page.getByText('Campaign deleted')).toBeVisible();
    await expect(audience.campaignRow(subject)).toHaveCount(0);
  });

  test('a scheduled campaign is read-only until it is moved back to draft', async ({ freshAccount, publicApi, adminApi }) => {
    nonSaasOnly();
    await arrangeOptedInAudience(freshAccount, publicApi, adminApi);
    const page = await freshAccount.newAuthedPage();
    const audience = new AudiencePage(page, freshAccount.organizerId);
    const subject = uniqueShort('Later on');

    await audience.composeCampaign(subject, 'This one goes out next month.');
    await scheduleForNextMonth(page, subject);

    await page.waitForURL(/\/audience\/campaigns\/\d+$/);
    const campaignId = audience.campaignIdFromUrl();
    await expect(audience.campaignStatus()).toContainText('Scheduled');
    expect((await freshAccount.api.getAudienceCampaign(freshAccount.organizerId, campaignId)).status).toBe('SCHEDULED');

    await audience.gotoCampaigns();
    await page.getByRole('radiogroup', { name: 'Filter campaigns' }).getByText('Scheduled · 1', { exact: true }).click();
    await expect(page).toHaveURL(/folder=scheduled/);
    await expect(audience.campaignRow(subject)).toContainText('Goes out');

    await audience.campaignRow(subject).getByRole('link', { name: subject }).click();
    await expect(page.getByRole('button', { name: 'Send me a test' })).toHaveCount(0);
    await expect(page.getByRole('button', { name: 'Delete' })).toHaveCount(0);
    await page.getByRole('button', { name: 'Move back to draft' }).click();
    await expect(page.getByText('Moved back to draft')).toBeVisible();
    await expect(audience.campaignStatus()).toContainText('Draft');
    await expect(page.getByTestId('campaign-subject')).toBeEditable();
    expect((await freshAccount.api.getAudienceCampaign(freshAccount.organizerId, campaignId)).status).toBe('DRAFT');
  });

  test('scheduling a campaign that needs review sends it for review straight away, and it can be pulled back to a draft', async ({ freshAccount, publicApi, adminApi }) => {
    saasOnly();
    await arrangeOptedInAudience(freshAccount, publicApi, adminApi);
    const page = await freshAccount.newAuthedPage();
    const audience = new AudiencePage(page, freshAccount.organizerId);
    const subject = uniqueShort('Reviewed early');

    await audience.composeCampaign(subject, 'This one goes out next month.');
    await scheduleForNextMonth(page, subject, 'Submit for review');

    await expect(page.getByText("Sent for review. Once it's approved, it goes out at the time you picked.")).toBeVisible();
    await page.waitForURL(/\/audience\/campaigns\/\d+$/);
    await expect(audience.campaignStatus()).toContainText('Waiting for review');
    const campaign = await freshAccount.api.getAudienceCampaign(freshAccount.organizerId, audience.campaignIdFromUrl());
    expect(campaign.status).toBe('PENDING_REVIEW');
    expect(campaign.scheduled_at).not.toBeNull();

    await audience.gotoCampaigns();
    await page.getByRole('radiogroup', { name: 'Filter campaigns' }).getByText('Scheduled or in review · 1', { exact: true }).click();
    await expect(audience.campaignRow(subject)).toContainText('Waiting for review');
    await expect(audience.campaignRow(subject)).toContainText('Submitted');
    await audience.campaignRow(subject).getByRole('link', { name: subject }).click();

    await expect(page.getByTestId('campaign-subject')).toHaveCount(0);
    await page.getByRole('button', { name: 'Move back to draft' }).click();
    await expect(page.getByText('Moved back to draft')).toBeVisible();
    await expect(audience.campaignStatus()).toContainText('Draft');
    await expect(page.getByTestId('campaign-subject')).toBeEditable();
  });

  test('a reviewer rejects a campaign with a reason, and approves it once it is resubmitted', async ({ freshAccount, publicApi, mailpit, adminApi, superAdminPage }) => {
    saasOnly();
    const people = await arrangeOptedInAudience(freshAccount, publicApi, adminApi);
    const subject = uniqueShort('Needs review');
    const page = await freshAccount.newAuthedPage();
    const { audience } = await composeAndSend(page, freshAccount.organizerId, subject);
    await expect(audience.campaignStatus()).toContainText('Waiting for review');

    const reason = 'Please add the venue address before sending.';
    await reviewCampaign(superAdminPage, subject, async (modal) => {
      await expect(modal).toContainText(`Subject: ${subject}`);
      await expect(modal).toContainText('Goes to: Everyone who opted in');
      await expect(modal).toContainText('Recipients: 1');
      await expect(modal).toContainText(/Replies go to: \S+@\S+/);
      await expect(modal).toContainText('If approved: starts sending straight away');
      await expect(modal.getByTitle('Email preview')).toBeVisible();
      await modal.getByRole('button', { name: 'Reject', exact: true }).click();
      await modal.getByLabel('Reason for the organizer (optional)').fill(reason);
      await modal.getByRole('button', { name: 'Reject campaign' }).click();
    });

    await page.reload();
    await expect(audience.campaignStatus()).toContainText('Not approved');
    await expect(page.getByTestId('campaign-review-note')).toContainText(reason);
    await expect(page.getByText("This campaign wasn't approved to send. Edit it and try again")).toBeVisible();
    const rejection = await mailpit.waitForMessage(freshAccount.email, { subjectContains: 'Your campaign was not approved', timeoutMs: 45_000 });
    expect((await mailpit.getMessage(rejection.ID)).Text).toContain(reason);
    expect((await mailpit.search(people.subscriber)).filter((message) => message.Subject.includes(subject))).toHaveLength(0);

    await page.getByTestId('campaign-body').fill('Tickets are ready. We are at 1 Main Street.');
    await audience.sendCampaignNow();
    await expect(audience.campaignStatus()).toContainText('Waiting for review');

    await reviewCampaign(superAdminPage, subject, async (modal) => {
      await modal.getByRole('button', { name: 'Approve and send' }).click();
    });

    await mailpit.waitForMessage(people.subscriber, { subjectContains: subject, timeoutMs: 45_000 });
    await page.reload();
    await expect(audience.campaignStatus()).toContainText(/Sending|Sent/);
    await expect(page.getByTestId('campaign-review-note')).toHaveCount(0);
  });

  test('a campaign started from a segment reaches only the subscribers in it', async ({ freshAccount, publicApi, mailpit, adminApi, messaging }) => {
    test.slow();
    const { api, organizerId } = freshAccount;
    const first = await createLiveEventWithFreeTicket(api, organizerId);
    const second = await createLiveEventWithFreeTicket(api, organizerId);
    const regular = uniqueEmail('regular');
    const onceOnly = uniqueEmail('once-only');
    await createOptedInOrder(publicApi, first, { email: regular, firstName: 'Rita', lastName: 'Regular' });
    await createOptedInOrder(publicApi, second, { email: regular, firstName: 'Rita', lastName: 'Regular' });
    await createOptedInOrder(publicApi, first, { email: onceOnly, firstName: 'Olly', lastName: 'Once' });
    await buildAudience(api, organizerId);
    await waitForContact(api, organizerId, onceOnly);
    await adminApi.grantMessagingPrivileges((await api.getAccount()).id);
    const segmentName = uniqueShort('Came twice');
    await messaging.createSegment(segmentName, [{ field: 'events_count', operator: 'gte', value: 2 }]);
    const subject = uniqueShort('For our regulars');

    const page = await freshAccount.newAuthedPage();
    const audience = new AudiencePage(page, organizerId);
    await audience.gotoContacts();
    await audience.segmentSelect().click();
    await page.getByRole('option', { name: segmentName }).click();
    await expect(audience.contactRows()).toHaveCount(1);
    await page.getByRole('link', { name: 'Email this segment' }).click();

    await expect(page).toHaveURL(/\/audience\/campaigns\/new\?segment=/);
    await expect(page.getByRole('combobox', { name: 'Send to' })).toHaveValue(segmentName);
    await expect(page.getByTestId('campaign-recipient-count')).toHaveText('1 of 1 opted in');
    await page.getByTestId('campaign-subject').fill(subject);
    await page.getByTestId('campaign-body').fill('You have been to two of our nights. Thank you.');
    await page.getByTestId('campaign-review-send').click();
    await expect(page.getByRole('dialog', { name: `Send "${subject}"` }).getByTestId('campaign-send-summary'))
      .toHaveText('This goes to 1 person who opted in to marketing emails. Everyone else you selected is skipped.');
    await page.getByTestId('campaign-send-confirm').click();
    await expect(audience.campaignStatus()).not.toContainText('Draft');
    const campaignId = audience.campaignIdFromUrl();
    await approveIfHeldForReview(adminApi, api, organizerId, campaignId);

    await mailpit.waitForMessage(regular, { subjectContains: subject, timeoutMs: 45_000 });
    await waitForCampaignStatus(api, organizerId, campaignId, /^SENT$/);
    expect((await mailpit.search(onceOnly)).filter((message) => message.Subject.includes(subject))).toHaveLength(0);

    await page.reload();
    await expect(page.getByTestId('campaign-recipient-count')).toContainText(segmentName);
    await expect(page.getByTestId('campaign-recipient-count')).toContainText('1 recipient');
    await expect(page.getByTestId('campaign-recipient-row')).toHaveCount(1);
    await expect(page.getByTestId('campaign-recipient-row')).toContainText('Rita Regular');
  });

  test('a scheduled campaign goes out when its time comes', async ({ freshAccount, publicApi, mailpit, adminApi, messaging }) => {
    test.slow();
    const people = await arrangeOptedInAudience(freshAccount, publicApi, adminApi);
    const { api, organizerId } = freshAccount;
    const subject = uniqueShort('Right on time');
    const campaignId = await scheduleThroughTheApi({ messaging, adminApi, freshAccount, subject });

    const page = await freshAccount.newAuthedPage();
    const audience = new AudiencePage(page, organizerId);
    await page.goto(`/manage/organizer/${organizerId}/audience/campaigns/${campaignId}`);
    await expect(audience.campaignStatus()).toContainText('Scheduled');
    await expect(page.getByText("This campaign is scheduled, so it can't be edited. Move it back to draft to change it.")).toBeVisible();
    await expect(page.getByTestId('campaign-subject')).toHaveCount(0);
    expect((await mailpit.search(people.subscriber)).filter((message) => message.Subject.includes(subject))).toHaveLength(0);

    moveScheduledCampaignIntoThePast(campaignId, 1);
    await expect(async () => {
      releaseScheduledCampaigns();
      expect((await api.getAudienceCampaign(organizerId, campaignId)).status).toMatch(/^(SENDING|SENT)$/);
    }).toPass({ timeout: 45_000, intervals: [2_000, 4_000] });
    await mailpit.waitForMessage(people.subscriber, { subjectContains: subject, timeoutMs: 45_000 });
    expect((await mailpit.search(people.silent)).filter((message) => message.Subject.includes(subject))).toHaveLength(0);
    await waitForCampaignStatus(api, organizerId, campaignId, /^SENT$/);

    await page.reload();
    await expect(audience.campaignStatus()).toContainText('Sent');
    await expect(page.getByTestId('campaign-recipient-row')).toContainText(people.subscriber);
    await expect(page.getByRole('button', { name: 'Move back to draft' })).toHaveCount(0);

    await page.goto(`/manage/organizer/${organizerId}/audience/campaigns?folder=scheduled`);
    await expect(page.getByText(/^Nothing is scheduled/)).toBeVisible();
    await expect(audience.campaignRow(subject)).toHaveCount(0);
  });

  test('a scheduled campaign that misses its time by too long goes back to drafts and the organizer is told', async ({ freshAccount, publicApi, mailpit, adminApi, messaging }) => {
    test.slow();
    const people = await arrangeOptedInAudience(freshAccount, publicApi, adminApi);
    const { api, organizerId } = freshAccount;
    const subject = uniqueShort('Far too late');
    const campaignId = await scheduleThroughTheApi({ messaging, adminApi, freshAccount, subject });

    moveScheduledCampaignIntoThePast(campaignId, 7 * 60);
    await expect(async () => {
      releaseScheduledCampaigns();
      expect((await api.getAudienceCampaign(organizerId, campaignId)).status).toBe('DRAFT');
    }).toPass({ timeout: 45_000, intervals: [2_000, 4_000] });

    await mailpit.waitForMessage(freshAccount.email, { subjectContains: 'Your scheduled campaign was not sent', timeoutMs: 45_000 });
    expect((await mailpit.search(people.subscriber)).filter((message) => message.Subject.includes(subject))).toHaveLength(0);

    const page = await freshAccount.newAuthedPage();
    const audience = new AudiencePage(page, organizerId);
    await page.goto(`/manage/organizer/${organizerId}/audience/campaigns/${campaignId}`);
    await expect(audience.campaignStatus()).toContainText('Draft');
    await expect(page.getByText("This campaign couldn't be sent within 6 hours of its scheduled time, so it's back in your drafts and nobody received it.")).toBeVisible();
    await expect(page.getByTestId('campaign-subject')).toHaveValue(subject);
    await expect(page.getByTestId('campaign-subject')).toBeEditable();
  });

  test('an unsubscribe link we never sent says it is not valid', async ({ page }) => {
    await page.goto('/audience/unsubscribe/123456.not-a-real-signature');
    await expect(page.getByRole('heading', { name: "This link isn't valid" })).toBeVisible();
    await expect(page.getByText('The unsubscribe link may be incomplete. Try opening it again from the email.')).toBeVisible();
    await expect(page.getByTestId('audience-unsubscribe-button')).toHaveCount(0);
    await expect(page.getByTestId('audience-resubscribe-button')).toHaveCount(0);
  });

  test('on a phone a campaign is written, checked in the send dialog and found through the folder picker', async ({ freshAccount, publicApi, adminApi }) => {
    await arrangeOptedInAudience(freshAccount, publicApi, adminApi);
    const page = await freshAccount.newAuthedPage();
    await page.setViewportSize(PHONE_VIEWPORT);
    const audience = new AudiencePage(page, freshAccount.organizerId);
    const subject = uniqueShort('From my phone');

    await audience.composeCampaign(subject, 'Written on the bus.');
    await expect(page.getByTestId('campaign-recipient-count')).toContainText('1 of 2 opted in');
    await expectNoSidewaysScroll(page);
    await expect(page.getByRole('button', { name: 'Send me a test' })).toHaveCount(0);
    await page.getByTestId('campaign-more-actions').click();
    await expect(page.getByRole('menuitem', { name: 'Send me a test' })).toBeVisible();
    await page.keyboard.press('Escape');

    await page.getByRole('radiogroup').getByText('Preview', { exact: true }).click();
    await expect(page.getByTestId('campaign-preview').contentFrame().getByText('Written on the bus.')).toBeVisible();
    await expect(page.getByTestId('campaign-preview-inbox')).toContainText(subject);
    await expectNoSidewaysScroll(page);

    await page.getByTestId('campaign-review-send').click();
    const send = page.getByRole('dialog', { name: `Send "${subject}"` });
    await expect(send.getByTestId('campaign-send-summary')).toContainText('This goes to 1 person who opted in to marketing emails.');
    await expect(send.getByTestId('campaign-send-confirm')).toBeInViewport();
    await expectNoSidewaysScroll(page);
    await send.getByRole('button', { name: 'Cancel' }).click();
    await page.waitForURL(/\/audience\/campaigns\/\d+$/);

    await audience.gotoCampaigns();
    await expect(page.getByRole('radiogroup', { name: 'Filter campaigns' })).toHaveCount(0);
    const folder = page.getByRole('combobox', { name: 'Filter campaigns' });
    await folder.click();
    await page.getByRole('option', { name: 'Drafts · 1' }).click();
    await expect(page).toHaveURL(/folder=drafts/);
    await expect(page.getByRole('link', { name: subject })).toBeVisible();
    await expect(page.getByText('1 campaign', { exact: true })).toBeVisible();
    await folder.click();
    await page.getByRole('option', { name: 'Sent', exact: true }).click();
    await expect(page.getByText('Nothing sent yet.')).toBeVisible();
    await expectNoSidewaysScroll(page);
  });
});

async function reviewCampaign(adminPage: Page, subject: string, decide: (modal: ReturnType<Page['getByRole']>) => Promise<void>): Promise<void> {
  await adminPage.goto('/admin/audience-campaigns');
  const row = adminPage.getByRole('row').filter({ hasText: subject });
  await row.getByRole('button', { name: 'Review' }).click();
  const modal = adminPage.getByRole('dialog').filter({ hasText: subject });
  await decide(modal);
  await expect(modal).toBeHidden();
  await expect(row).toHaveCount(0);
}

async function scheduleForNextMonth(page: Page, subject: string, confirmLabel = 'Schedule'): Promise<void> {
  await page.getByRole('button', { name: 'More send options' }).click();
  await page.getByRole('menuitem', { name: 'Schedule for later' }).click();
  const modal = page.getByRole('dialog', { name: `Schedule "${subject}"` });
  await expect(modal.getByTestId('campaign-send-summary')).toContainText('This goes to 1 person who opted in');
  await modal.getByRole('button', { name: /^Send at/ }).click();
  const picker = page.getByRole('dialog').filter({ has: page.getByRole('table') });
  const shownMonth = picker.getByRole('button', { name: /^\p{L}+ \d{4}$/u });
  const monthBefore = await shownMonth.innerText();
  await picker.getByRole('button', { name: 'Next month' }).click();
  await expect(shownMonth).not.toHaveText(monthBefore);
  await picker.getByRole('button', { name: `15 ${await shownMonth.innerText()}`, exact: true }).click();
  await picker.getByRole('button', { name: 'Confirm' }).click();
  await expect(modal.getByRole('button', { name: /^Send at/ })).not.toHaveText('Pick a date and time');
  await expect(modal.getByTestId('campaign-send-confirm')).toHaveText(confirmLabel);
  await modal.getByTestId('campaign-send-confirm').click();
}
