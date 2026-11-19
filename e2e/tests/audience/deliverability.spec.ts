import type { APIRequestContext } from '@playwright/test';
import { expect, type FreshAccount } from '../../fixtures';
import type { AdminApiClient } from '../../api/api-client';
import { createLiveEventWithFreeTicket } from '../../api/factory';
import { AudiencePage } from '../../pages/audience.page';
import { uniqueEmail, uniqueShort } from '../../utils/unique';
import { buildAudience, createOptedInOrder, marketingStatusOf, waitForContact } from './audience.shared';
import {
  EMAIL_DELIVERY_WEBHOOK_SECRET,
  emailDeliveryReportsAreReceived,
  emailMessageIdOf,
  emailsAbout,
  postEmailDeliveryWebhook,
  recordCampaignFeedback,
} from './coverage.helpers';
import { approveIfHeldForReview, test, waitForCampaignStatus, type MessagingApi } from './messaging.shared';

const NEEDS_RECEIVER = 'This backend has no email delivery webhook receiver: set POSTMARK_WEBHOOK_SECRET in the backend environment and pass the same value to the runner as E2E_POSTMARK_WEBHOOK_SECRET.';
const HAS_RECEIVER = 'This backend receives email delivery reports (POSTMARK_WEBHOOK_SECRET is set), so the no-reports notes are not shown.';

interface Person {
  email: string;
  firstName: string;
  lastName: string;
}

interface Arranged {
  freshAccount: FreshAccount;
  publicApi: APIRequestContext;
  adminApi: AdminApiClient;
  messaging: MessagingApi;
}

const sendToSubscribers = async ({ freshAccount, publicApi, adminApi, messaging }: Arranged, people: Person[]): Promise<{ id: number; subject: string }> => {
  const { api, organizerId } = freshAccount;
  const event = await createLiveEventWithFreeTicket(api, organizerId);
  for (const person of people) {
    await createOptedInOrder(publicApi, event, person);
  }
  await buildAudience(api, organizerId);
  for (const person of people) {
    await waitForContact(api, organizerId, person.email);
  }
  await adminApi.grantMessagingPrivileges((await api.getAccount()).id);
  const subject = uniqueShort('Season opener');
  const campaign = await messaging.createCampaign({ name: subject, subject, body: '<p>Our new season starts next month.</p>' });
  await messaging.sendCampaign(campaign.id);
  await approveIfHeldForReview(adminApi, api, organizerId, campaign.id);
  await waitForCampaignStatus(api, organizerId, campaign.id, /^SENT$/);
  return { id: campaign.id, subject };
};

const expectStatusAfterFeedback = async (freshAccount: FreshAccount, email: string, status: string): Promise<void> => {
  await expect(async () => {
    recordCampaignFeedback();
    expect(await marketingStatusOf(freshAccount.api, freshAccount.organizerId, email)).toBe(status);
  }).toPass({ timeout: 45_000, intervals: [2_000, 4_000] });
};

test.describe('audience email deliverability', () => {
  test('an install whose mail service sends no delivery reports says so on the campaign list and report, and shows no delivery figures', async ({ freshAccount, publicApi, adminApi, messaging, mailpit }) => {
    test.slow();
    test.skip(await emailDeliveryReportsAreReceived(publicApi), HAS_RECEIVER);
    const reader = { email: uniqueEmail('reader'), firstName: 'Rae', lastName: 'Reader' };
    const campaign = await sendToSubscribers({ freshAccount, publicApi, adminApi, messaging }, [reader]);
    await mailpit.waitForMessage(reader.email, { subjectContains: campaign.subject, timeoutMs: 45_000 });

    const page = await freshAccount.newAuthedPage();
    const audience = new AudiencePage(page, freshAccount.organizerId);
    await page.goto(`/manage/organizer/${freshAccount.organizerId}/audience/campaigns/${campaign.id}`);
    await expect(audience.campaignStatus()).toContainText('Sent');
    await expect(page.getByTestId('campaign-no-delivery-reports')).toHaveText(
      "This installation's mail service doesn't send delivery reports, so delivered, opened, clicked and bounced figures aren't available, and addresses that bounce or report spam aren't removed for you.",
    );
    const delivered = page.locator('div').filter({ hasText: /^Delivered–Not available$/ }).last();
    await expect(delivered).toBeVisible();
    await expect(page.getByText(/\d+ bounced/)).toHaveCount(0);
    await expect(page.getByText('0 skipped or failed')).toBeVisible();

    await audience.gotoCampaigns();
    await expect(audience.campaignRow(campaign.subject)).toContainText('Sent');
    await expect(page.getByTestId('campaigns-no-delivery-reports'))
      .toHaveText("Opens and clicks aren't shown because this installation's mail service doesn't send delivery reports.");
    await expect(page.getByRole('columnheader', { name: 'Opened' })).toHaveCount(0);
    await expect(page.getByRole('columnheader', { name: 'Clicked' })).toHaveCount(0);

    const report = await postEmailDeliveryWebhook(publicApi, 'any-secret', {
      RecordType: 'Bounce',
      MessageID: emailMessageIdOf(campaign.id, reader.email),
      Type: 'HardBounce',
      Inactive: true,
      BouncedAt: new Date().toISOString(),
    });
    expect(report.status()).toBe(404);
    recordCampaignFeedback();
    expect(await marketingStatusOf(freshAccount.api, freshAccount.organizerId, reader.email)).toBe('SUBSCRIBED');
  });

  test('an address that hard-bounces is marked Bounced and left out of the next campaign until a team member reactivates it', async ({ freshAccount, publicApi, adminApi, messaging, mailpit }) => {
    test.slow();
    test.skip(!(await emailDeliveryReportsAreReceived(publicApi)) || EMAIL_DELIVERY_WEBHOOK_SECRET === '', NEEDS_RECEIVER);
    const { organizerId } = freshAccount;
    const bouncer = { email: uniqueEmail('bouncer'), firstName: 'Bo', lastName: 'Bounced' };
    const steady = { email: uniqueEmail('steady'), firstName: 'Stella', lastName: 'Steady' };
    const first = await sendToSubscribers({ freshAccount, publicApi, adminApi, messaging }, [bouncer, steady]);
    await mailpit.waitForMessage(bouncer.email, { subjectContains: first.subject, timeoutMs: 45_000 });
    const bounce = {
      RecordType: 'Bounce' as const,
      MessageID: emailMessageIdOf(first.id, bouncer.email),
      Type: 'HardBounce' as const,
      Inactive: true as const,
      BouncedAt: new Date().toISOString(),
    };

    expect((await postEmailDeliveryWebhook(publicApi, 'not-the-secret', bounce)).status()).toBe(401);
    recordCampaignFeedback();
    expect(await marketingStatusOf(freshAccount.api, organizerId, bouncer.email)).toBe('SUBSCRIBED');

    expect((await postEmailDeliveryWebhook(publicApi, EMAIL_DELIVERY_WEBHOOK_SECRET, bounce)).status()).toBe(200);
    await expectStatusAfterFeedback(freshAccount, bouncer.email, 'BOUNCED');
    expect(await marketingStatusOf(freshAccount.api, organizerId, steady.email)).toBe('SUBSCRIBED');

    const page = await freshAccount.newAuthedPage();
    const audience = new AudiencePage(page, organizerId);
    await audience.gotoSuppressed();
    await expect(audience.contactRows()).toHaveCount(1);
    await expect(audience.contactRow('Bo Bounced')).toContainText('Bounced');
    await audience.contactRow('Bo Bounced').click();
    await expect(audience.marketingStatus()).toContainText('Their address rejected an email. Campaigns skip them.');
    await expect(audience.marketingStatus()).toContainText(`From "${first.subject}"`);
    await expect(audience.drawerSection('Consent history')).toContainText(`Reported on "${first.subject}"`);
    const reactivate = page.getByTestId('contact-reactivate-bounce');
    await expect(reactivate).toBeEnabled();
    await page.keyboard.press('Escape');
    await expect(audience.drawer()).toBeHidden();

    const second = uniqueShort('After the bounce');
    await audience.composeCampaign(second, 'Only working addresses get this one.');
    await expect(page.getByTestId('campaign-recipient-count')).toContainText('1 of 2 opted in');
    const secondId = await audience.sendCampaignNow();
    await approveIfHeldForReview(adminApi, freshAccount.api, organizerId, secondId);
    await mailpit.waitForMessage(steady.email, { subjectContains: second, timeoutMs: 45_000 });
    await waitForCampaignStatus(freshAccount.api, organizerId, secondId, /^SENT$/);
    expect(await emailsAbout(mailpit, bouncer.email, second)).toBe(0);

    await audience.gotoContactsMatching(bouncer.email);
    await audience.contactRow('Bo Bounced').click();
    await reactivate.click();
    const confirm = page.getByRole('dialog').filter({ hasText: `Reactivate ${bouncer.email}?` });
    await expect(confirm).toContainText("Only do this if the address works again. This doesn't subscribe them: they'll only get campaigns if they had opted in before the bounce.");
    await expect(confirm).toContainText(/Before the bounce: opted in on .+\./);
    await confirm.getByRole('button', { name: 'Reactivate', exact: true }).click();
    await expect(page.getByText('Address reactivated')).toBeVisible();
    await expect(audience.marketingStatus()).toContainText('Subscribed');
    await expect(reactivate).toHaveCount(0);
    await expect(audience.drawerSection('Consent history')).toContainText('Bounce cleared');
    await page.keyboard.press('Escape');
    await expect(audience.drawer()).toBeHidden();
    await audience.startBlankCampaign();
    await expect(page.getByTestId('campaign-recipient-count')).toContainText('2 of 2 opted in');

    await page.goto(`/manage/organizer/${organizerId}/audience/campaigns/${first.id}`);
    await expect(page.getByText('1 bounced')).toBeVisible();
    await expect(page.getByTestId('campaign-no-delivery-reports')).toHaveCount(0);
  });

  test('someone who reports a campaign as spam is never emailed again, even if they tick the marketing box on a later order', async ({ freshAccount, publicApi, adminApi, messaging, mailpit }) => {
    test.slow();
    test.skip(!(await emailDeliveryReportsAreReceived(publicApi)) || EMAIL_DELIVERY_WEBHOOK_SECRET === '', NEEDS_RECEIVER);
    const { api, organizerId } = freshAccount;
    const reporter = { email: uniqueEmail('reporter'), firstName: 'Rex', lastName: 'Reporter' };
    const campaign = await sendToSubscribers({ freshAccount, publicApi, adminApi, messaging }, [reporter]);
    await mailpit.waitForMessage(reporter.email, { subjectContains: campaign.subject, timeoutMs: 45_000 });

    const complaint = await postEmailDeliveryWebhook(publicApi, EMAIL_DELIVERY_WEBHOOK_SECRET, {
      RecordType: 'SpamComplaint',
      MessageID: emailMessageIdOf(campaign.id, reporter.email),
      BouncedAt: new Date().toISOString(),
    });
    expect(complaint.status()).toBe(200);
    await expectStatusAfterFeedback(freshAccount, reporter.email, 'COMPLAINED');

    const later = await createLiveEventWithFreeTicket(api, organizerId);
    await createOptedInOrder(publicApi, later, reporter);
    await expect.poll(async () => (await api.findAudienceContacts(organizerId, reporter.email))[0]?.events_count, { timeout: 30_000 }).toBe(2);
    expect(await marketingStatusOf(api, organizerId, reporter.email)).toBe('COMPLAINED');

    const page = await freshAccount.newAuthedPage();
    const audience = new AudiencePage(page, organizerId);
    await audience.gotoSuppressed();
    await expect(audience.contactRow('Rex Reporter')).toContainText('Marked as spam');
    await audience.contactRow('Rex Reporter').click();
    await expect(audience.marketingStatus()).toContainText('Reported one of your emails as spam. Campaigns skip them permanently.');
    await expect(audience.marketingStatus()).toContainText(`From "${campaign.subject}"`);
    await expect(page.getByTestId('contact-reactivate-bounce')).toHaveCount(0);
    await expect(audience.drawerSection('Consent history')).toContainText('Marked as spam');
    await page.keyboard.press('Escape');
    await expect(audience.drawer()).toBeHidden();

    const second = uniqueShort('After the complaint');
    await audience.composeCampaign(second, 'Nobody should get this.');
    await expect(page.getByTestId('campaign-recipient-count')).toContainText('0 of 1 opted in');
    await page.getByTestId('campaign-review-send').click();
    const send = page.getByRole('dialog', { name: `Send "${second}"` });
    await expect(send.getByTestId('campaign-send-summary'))
      .toHaveText('Nobody in this segment has opted in to marketing emails, so there is no one to send to.');
    await expect(send.getByTestId('campaign-send-confirm')).toBeDisabled();
    expect(await emailsAbout(mailpit, reporter.email, second)).toBe(0);

    await page.goto(`/manage/organizer/${organizerId}/audience/campaigns/${campaign.id}`);
    await expect(page.getByText('1 marked as spam')).toBeVisible();
  });
});
