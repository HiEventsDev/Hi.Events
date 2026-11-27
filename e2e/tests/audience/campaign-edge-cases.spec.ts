import { expect } from '../../fixtures';
import { createLiveEventWithFreeTicket } from '../../api/factory';
import { AudiencePage } from '../../pages/audience.page';
import { saasOnly } from '../../utils/mode';
import { uniqueEmail, uniqueShort } from '../../utils/unique';
import { buildAudience, createOptedInOrder, waitForContact } from './audience.shared';
import { contactsCsv, withContactsApi } from './contacts.helpers';
import { emailsAbout } from './coverage.helpers';
import { approveIfHeldForReview, contactIdOf, test, waitForCampaignStatus } from './messaging.shared';

test.describe('audience campaign edge cases', () => {
  test('on Hi.Events Cloud someone who is unsubscribed while a campaign waits for review is skipped when it goes out, and the report says why', async ({ freshAccount, publicApi, adminApi, mailpit, messaging }) => {
    saasOnly();
    test.slow();
    const { api, organizerId } = freshAccount;
    const event = await createLiveEventWithFreeTicket(api, organizerId);
    const stayer = uniqueEmail('stayer');
    const leaver = uniqueEmail('leaver');
    await createOptedInOrder(publicApi, event, { email: stayer, firstName: 'Stacy', lastName: 'Stayer' });
    await createOptedInOrder(publicApi, event, { email: leaver, firstName: 'Lee', lastName: 'Leaver' });
    await buildAudience(api, organizerId);
    await waitForContact(api, organizerId, leaver);
    await adminApi.grantMessagingPrivileges((await api.getAccount()).id);
    const subject = uniqueShort('Reviewed slowly');
    const campaign = await messaging.createCampaign({ name: subject, subject, body: '<p>Thanks for waiting.</p>' });
    await messaging.sendCampaign(campaign.id);
    await waitForCampaignStatus(api, organizerId, campaign.id, /^PENDING_REVIEW$/);

    await messaging.unsubscribeContact(await contactIdOf(api, organizerId, leaver));
    await adminApi.approveAudienceCampaign(campaign.id);
    await mailpit.waitForMessage(stayer, { subjectContains: subject, timeoutMs: 45_000 });
    await waitForCampaignStatus(api, organizerId, campaign.id, /^SENT$/);
    expect(await emailsAbout(mailpit, leaver, subject)).toBe(0);

    const page = await freshAccount.newAuthedPage();
    const audience = new AudiencePage(page, organizerId);
    await page.goto(`/manage/organizer/${organizerId}/audience/campaigns/${campaign.id}`);
    await expect(audience.campaignStatus()).toContainText('Sent');
    await expect(page.getByText('1 skipped or failed')).toBeVisible();
    const skipped = page.getByTestId('campaign-recipient-row').filter({ hasText: 'Lee Leaver' });
    await expect(skipped).toContainText('Skipped');
    await expect(skipped).toContainText('Unsubscribed before it reached them');
    await expect(page.getByTestId('campaign-recipient-row').filter({ hasText: 'Stacy Stayer' })).not.toContainText('Skipped');
    await page.getByRole('radiogroup', { name: 'Filter recipients' }).getByText('Not sent · 1', { exact: true }).click();
    await expect(page.getByTestId('campaign-recipient-row')).toHaveCount(1);
    await expect(page.getByTestId('campaign-recipient-row')).toContainText(leaver);
  });

  test('on Hi.Events Cloud a new account cannot send a campaign with a link in it until the link is removed', async ({ freshAccount, publicApi, adminApi, mailpit }) => {
    saasOnly();
    const { api, organizerId } = freshAccount;
    const event = await createLiveEventWithFreeTicket(api, organizerId);
    const subscriber = uniqueEmail('link-reader');
    await createOptedInOrder(publicApi, event, { email: subscriber, firstName: 'Lin', lastName: 'Linkreader' });
    await buildAudience(api, organizerId);
    await waitForContact(api, organizerId, subscriber);
    const { id: accountId } = await api.getAccount();
    await adminApi.setAccountVerification(accountId, true);
    await adminApi.setUntrustedMessagingTier(accountId);
    const subject = uniqueShort('With a link');

    const page = await freshAccount.newAuthedPage();
    const audience = new AudiencePage(page, organizerId);
    await audience.composeCampaign(subject, 'Book at https://example.org/tickets before Friday.');
    await page.getByTestId('campaign-review-send').click();
    const send = page.getByRole('dialog', { name: `Send "${subject}"` });
    await expect(send.getByTestId('campaign-send-summary')).toContainText("Your account can't send links yet. Remove the links");
    await expect(send.getByTestId('campaign-send-confirm')).toHaveText("Can't send yet");
    await expect(send.getByTestId('campaign-send-confirm')).toBeDisabled();
    await send.getByRole('button', { name: 'Close', exact: true }).filter({ hasText: 'Close' }).click();
    await page.waitForURL(/\/audience\/campaigns\/\d+$/);
    await expect(audience.campaignStatus()).toContainText('Draft');
    expect(await emailsAbout(mailpit, subscriber, subject)).toBe(0);

    await page.getByTestId('campaign-body').fill('Book at the box office before Friday.');
    await page.getByTestId('campaign-review-send').click();
    await expect(send.getByTestId('campaign-send-summary'))
      .toHaveText('This goes to 1 person who opted in to marketing emails. Everyone else you selected is skipped.');
    await expect(send.getByTestId('campaign-send-confirm')).toHaveText('Submit for review');
    await expect(send.getByTestId('campaign-send-confirm')).toBeEnabled();
  });

  test('on Hi.Events Cloud a trusted account is reviewed again when a campaign reaches people who only opted in through a recent import', async ({ freshAccount, publicApi, adminApi, mailpit, messaging, playwright }) => {
    saasOnly();
    test.slow();
    const { api, organizerId } = freshAccount;
    const event = await createLiveEventWithFreeTicket(api, organizerId);
    const buyer = uniqueEmail('buyer');
    await createOptedInOrder(publicApi, event, { email: buyer, firstName: 'Bea', lastName: 'Buyer' });
    await buildAudience(api, organizerId);
    await waitForContact(api, organizerId, buyer);
    await adminApi.grantMessagingPrivileges((await api.getAccount()).id);
    const first = uniqueShort('First of many');
    const firstCampaign = await messaging.createCampaign({ name: first, subject: first, body: '<p>Hello from us.</p>' });
    await messaging.sendCampaign(firstCampaign.id);
    await approveIfHeldForReview(adminApi, api, organizerId, firstCampaign.id);
    await waitForCampaignStatus(api, organizerId, firstCampaign.id, /^SENT$/);

    const imported = uniqueEmail('imported');
    await withContactsApi(playwright, freshAccount, (contactsApi) =>
      contactsApi.importContacts(contactsCsv([{ email: imported, firstName: 'Ida', lastName: 'Imported' }]), { consentAttested: true }));
    await waitForContact(api, organizerId, imported);

    const page = await freshAccount.newAuthedPage();
    const audience = new AudiencePage(page, organizerId);
    const second = uniqueShort('To the new list');
    await audience.composeCampaign(second, 'Welcome to everyone who just joined.');
    await expect(page.getByTestId('campaign-recipient-count')).toContainText('2 of 2 opted in');
    await page.getByTestId('campaign-review-send').click();
    const send = page.getByRole('dialog', { name: `Send "${second}"` });
    await expect(send).toContainText('Some recipients only opted in through a recent import, so we check this campaign before it goes out.');
    await expect(send.getByTestId('campaign-send-confirm')).toHaveText('Submit for review');
    await send.getByTestId('campaign-send-confirm').click();
    await expect(audience.campaignStatus()).toContainText('Waiting for review');
    await expect(page.getByText('Some recipients only opted in through a recent import, so we check this campaign before it goes out.')).toBeVisible();
    expect(await emailsAbout(mailpit, imported, second)).toBe(0);
    const secondId = audience.campaignIdFromUrl();
    await adminApi.approveAudienceCampaign(secondId);
    await mailpit.waitForMessage(imported, { subjectContains: second, timeoutMs: 45_000 });
    await waitForCampaignStatus(api, organizerId, secondId, /^SENT$/);

    const third = uniqueShort('No review this time');
    await audience.composeCampaign(third, 'This one goes straight out.');
    await page.getByTestId('campaign-review-send').click();
    const sendThird = page.getByRole('dialog', { name: `Send "${third}"` });
    await expect(sendThird.getByTestId('campaign-send-confirm')).toHaveText('Send to 2 people');
    await expect(sendThird).not.toContainText('we check this campaign before it goes out');
    await sendThird.getByTestId('campaign-send-confirm').click();
    await expect(page.getByText('Campaign is on its way')).toBeVisible();
    await mailpit.waitForMessage(imported, { subjectContains: third, timeoutMs: 45_000 });
    await mailpit.waitForMessage(buyer, { subjectContains: third, timeoutMs: 45_000 });
  });

  test('a link to a campaign that was deleted says it no longer exists', async ({ freshAccount, messaging }) => {
    const { api, organizerId } = freshAccount;
    await buildAudience(api, organizerId);
    const subject = uniqueShort('Gone soon');
    const draft = await messaging.createCampaign({ name: subject, subject, body: '<p>Never mind.</p>' });

    const page = await freshAccount.newAuthedPage();
    const audience = new AudiencePage(page, organizerId);
    const link = `/manage/organizer/${organizerId}/audience/campaigns/${draft.id}`;
    await page.goto(link);
    await expect(page.getByTestId('campaign-subject')).toHaveValue(subject);
    await page.getByRole('button', { name: 'Delete', exact: true }).click();
    await audience.confirm('Delete draft');
    await expect(page.getByText('Campaign deleted')).toBeVisible();

    await page.goto(link);
    await expect(page.getByText("This campaign doesn't exist any more.")).toBeVisible();
    await expect(page.getByTestId('campaign-subject')).toHaveCount(0);
    await expect(page.getByRole('button', { name: 'Save draft' })).toHaveCount(0);
  });
});
