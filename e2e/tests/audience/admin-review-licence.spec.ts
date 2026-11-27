import { expect } from '../../fixtures';
import { createLiveEventWithFreeTicket } from '../../api/factory';
import { AudiencePage } from '../../pages/audience.page';
import { simulateLicence, withLicence } from '../../utils/licence';
import { saasOnly } from '../../utils/mode';
import { uniqueEmail, uniqueShort } from '../../utils/unique';
import { buildAudience, createOptedInOrder, waitForContact } from './audience.shared';
import { emailsAbout } from './coverage.helpers';
import { test, waitForCampaignStatus } from './messaging.shared';

test.describe('audience campaign review without a licence', () => {
  test('with a lapsed licence a reviewer is told campaigns cannot be approved, and can still reject one with a reason', async ({ freshAccount, publicApi, adminApi, mailpit, messaging, superAdminPage, playwright }) => {
    saasOnly();
    const me = await withLicence(playwright, freshAccount.token, 'LAPSED', (api) => api.getMe());
    test.skip(me.licence.status !== 'LAPSED', 'This backend ignores X-Hi-Licence-Simulation (APP_ENV is not e2e or testing).');

    const { api, organizerId } = freshAccount;
    const event = await createLiveEventWithFreeTicket(api, organizerId);
    const subscriber = uniqueEmail('awaiting');
    await createOptedInOrder(publicApi, event, { email: subscriber, firstName: 'Ava', lastName: 'Awaiting' });
    await buildAudience(api, organizerId);
    await waitForContact(api, organizerId, subscriber);
    await adminApi.grantMessagingPrivileges((await api.getAccount()).id);
    const subject = uniqueShort('Held at review');
    const campaign = await messaging.createCampaign({ name: subject, subject, body: '<p>Tickets go on sale on Friday.</p>' });
    await messaging.sendCampaign(campaign.id);
    await waitForCampaignStatus(api, organizerId, campaign.id, /^PENDING_REVIEW$/);

    await simulateLicence(superAdminPage, 'LAPSED');
    await superAdminPage.goto('/admin/audience-campaigns');
    const locked = superAdminPage.getByRole('alert').filter({ hasText: "Campaigns can't be approved without a licence" });
    const row = superAdminPage.getByRole('row').filter({ hasText: subject });
    await expect(row).toBeVisible();
    await expect(locked.first()).toContainText("This installation's licence doesn't include Audience at the moment, so approving is switched off. You can still reject a campaign.");

    await row.getByRole('button', { name: 'Review' }).click();
    const review = superAdminPage.getByRole('dialog').filter({ hasText: subject });
    await expect(review.getByRole('alert').filter({ hasText: "Campaigns can't be approved without a licence" })).toBeVisible();
    await expect(review.getByRole('button', { name: 'Approve and send' })).toBeDisabled();
    const reason = 'We cannot send this until the licence is renewed.';
    await review.getByRole('button', { name: 'Reject', exact: true }).click();
    await review.getByLabel('Reason for the organizer (optional)').fill(reason);
    await review.getByRole('button', { name: 'Reject campaign' }).click();
    await expect(review).toBeHidden();
    await expect(row).toHaveCount(0);

    await waitForCampaignStatus(api, organizerId, campaign.id, /^REJECTED$/);
    expect(await emailsAbout(mailpit, subscriber, subject)).toBe(0);
    const page = await freshAccount.newAuthedPage();
    await page.goto(`/manage/organizer/${organizerId}/audience/campaigns/${campaign.id}`);
    await expect(new AudiencePage(page, organizerId).campaignStatus()).toContainText('Not approved');
    await expect(page.getByTestId('campaign-review-note')).toContainText(reason);
  });
});
