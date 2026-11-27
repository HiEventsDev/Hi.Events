import { expect } from '../../fixtures';
import { createCompletedOrder, createLiveEventWithFreeTicket } from '../../api/factory';
import { AudiencePage } from '../../pages/audience.page';
import { saasOnly } from '../../utils/mode';
import { uniqueEmail, uniqueShort } from '../../utils/unique';
import { buildAudience, createOptedInOrder, waitForContact } from './audience.shared';
import { emailsAbout } from './coverage.helpers';
import { test, waitForCampaignStatus } from './messaging.shared';

const NOT_ENABLED = 'To protect people from spam, campaigns are switched on once your account is verified. Connect Stripe or contact us';

test.describe('audience campaign send gate', () => {
  test('on Hi.Events Cloud a new account writes a campaign but cannot send it until the account is enabled, and its first send then waits for review', async ({ freshAccount, publicApi, mailpit, adminApi }) => {
    saasOnly();
    test.slow();
    const { api, organizerId } = freshAccount;
    await adminApi.setUntrustedMessagingTier((await api.getAccount()).id);
    const event = await createLiveEventWithFreeTicket(api, organizerId);
    const subscriber = uniqueEmail('gated-subscriber');
    await createOptedInOrder(publicApi, event, { email: subscriber, firstName: 'Gia', lastName: 'Gated' });
    await buildAudience(api, organizerId);
    await waitForContact(api, organizerId, subscriber);
    const subject = uniqueShort('Not yet');

    const page = await freshAccount.newAuthedPage();
    const audience = new AudiencePage(page, organizerId);
    await audience.gotoCampaigns();
    const banner = page.getByRole('complementary').filter({ hasText: "You can't send campaigns yet" });
    await expect(banner).toContainText(NOT_ENABLED);
    await expect(banner.getByRole('link', { name: 'Connect Stripe' })).toHaveAttribute('href', `/manage/organizer/${organizerId}/settings/payouts`);

    await audience.composeCampaign(subject, 'Doors open at seven on Friday.');
    await expect(page.getByTestId('campaign-recipient-count')).toContainText('1 of 1 opted in');
    const notice = page.getByTestId('campaign-send-gate-notice');
    await expect(notice).toContainText("You can write this campaign, but you can't send it yet.");
    await expect(notice).toContainText(NOT_ENABLED);

    await page.getByTestId('campaign-review-send').click();
    const send = page.getByRole('dialog', { name: `Send "${subject}"` });
    await expect(send.getByTestId('campaign-send-summary')).toContainText(NOT_ENABLED);
    await expect(send.getByRole('link', { name: 'Connect Stripe' })).toBeVisible();
    await expect(send.getByTestId('campaign-send-confirm')).toHaveText("Can't send yet");
    await expect(send.getByTestId('campaign-send-confirm')).toBeDisabled();
    await expect(send.getByRole('button', { name: 'Cancel', exact: true })).toHaveCount(0);
    await send.getByRole('button', { name: 'Close', exact: true }).filter({ hasText: 'Close' }).click();
    await expect(send).toBeHidden();
    await page.waitForURL(/\/audience\/campaigns\/\d+$/);
    const campaignId = audience.campaignIdFromUrl();
    await expect(audience.campaignStatus()).toContainText('Draft');
    await expect(page.getByTestId('campaign-subject')).toHaveValue(subject);
    expect((await api.getAudienceCampaign(organizerId, campaignId)).status).toBe('DRAFT');
    expect(await emailsAbout(mailpit, subscriber, subject)).toBe(0);

    await adminApi.setAccountVerification((await api.getAccount()).id, true);
    await page.reload();
    await expect(audience.campaignStatus()).toContainText('Draft');
    await expect(notice).toHaveCount(0);
    await page.getByTestId('campaign-review-send').click();
    await expect(send.getByTestId('campaign-send-summary'))
      .toHaveText('This goes to 1 person who opted in to marketing emails. Everyone else you selected is skipped.');
    await expect(send).toContainText('Campaigns from new accounts are checked before they go out.');
    await expect(send.getByTestId('campaign-send-confirm')).toHaveText('Submit for review');
    await send.getByTestId('campaign-send-confirm').click();
    await expect(page.getByText("Sent for review. We'll send it as soon as it's approved.")).toBeVisible();
    await expect(audience.campaignStatus()).toContainText('Waiting for review');
    await expect(page.getByText('Campaigns from new accounts are checked before they go out.')).toBeVisible();
    await expect(page.getByTestId('campaign-subject')).toHaveCount(0);
    expect(await emailsAbout(mailpit, subscriber, subject)).toBe(0);

    await adminApi.approveAudienceCampaign(campaignId);
    await mailpit.waitForMessage(subscriber, { subjectContains: subject, timeoutMs: 45_000 });
    await waitForCampaignStatus(api, organizerId, campaignId, /^SENT$/);
    await page.reload();
    await expect(audience.campaignStatus()).toContainText('Sent');
    await expect(page.getByTestId('campaign-recipient-row')).toContainText(subscriber);

    await audience.gotoCampaigns();
    await expect(audience.campaignRow(subject)).toContainText('Sent');
    await expect(banner).toHaveCount(0);
  });

  test('a campaign aimed at people who never opted in cannot be sent, and says there is no one to send to', async ({ freshAccount, publicApi, mailpit, adminApi }) => {
    const { api, organizerId } = freshAccount;
    const event = await createLiveEventWithFreeTicket(api, organizerId);
    const silent = uniqueEmail('never-asked');
    await createCompletedOrder(publicApi, event, { buyerEmail: silent, buyerFirstName: 'Nell', buyerLastName: 'Neverasked' });
    await buildAudience(api, organizerId);
    await waitForContact(api, organizerId, silent);
    await adminApi.grantMessagingPrivileges((await api.getAccount()).id);
    const subject = uniqueShort('To nobody');

    const page = await freshAccount.newAuthedPage();
    const audience = new AudiencePage(page, organizerId);
    await audience.composeCampaign(subject, 'Is anybody out there?');
    await expect(page.getByTestId('campaign-recipient-count')).toContainText('0 of 1 opted in');
    await expect(page.getByTestId('campaign-send-gate-notice')).toHaveCount(0);

    await page.getByTestId('campaign-review-send').click();
    const send = page.getByRole('dialog', { name: `Send "${subject}"` });
    await expect(send.getByTestId('campaign-send-summary'))
      .toHaveText('Nobody in this segment has opted in to marketing emails, so there is no one to send to.');
    await expect(send.getByTestId('campaign-send-confirm')).toBeDisabled();
    await send.getByRole('button', { name: 'Close', exact: true }).filter({ hasText: 'Close' }).click();
    await page.waitForURL(/\/audience\/campaigns\/\d+$/);
    await expect(audience.campaignStatus()).toContainText('Draft');
    expect((await api.getAudienceCampaign(organizerId, audience.campaignIdFromUrl())).status).toBe('DRAFT');
    expect(await emailsAbout(mailpit, silent, subject)).toBe(0);
  });
});
