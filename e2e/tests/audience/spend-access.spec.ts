import { expect } from '../../fixtures';
import { createCompletedPaidOrder, createLiveEventWithFreeTicket, createLiveEventWithPaidTicket } from '../../api/factory';
import { AudiencePage } from '../../pages/audience.page';
import { invitedMember } from '../../utils/team';
import { uniqueEmail, uniqueShort } from '../../utils/unique';
import { buildAudience, createOptedInOrder, waitForContact } from './audience.shared';
import { segmentRow } from './contacts.helpers';
import { approveIfHeldForReview, test, waitForCampaignStatus, type SegmentRule } from './messaging.shared';

const SPEND_HIDDEN = "Filters by spend, which your role can't see";

test.describe('audience spend rules without financial access', () => {
  test('a member who cannot view financials can rename a spend segment and edit a campaign aimed at it, but never sees its rules, its people or its recipients, and cannot use a spend filter link', async ({ freshAccount, publicApi, adminApi, mailpit, browser, messaging }) => {
    test.slow();
    const { api, organizerId } = freshAccount;
    const paid = await createLiveEventWithPaidTicket(api, organizerId, 25);
    const free = await createLiveEventWithFreeTicket(api, organizerId);
    const spender = uniqueEmail('big-spender');
    const freeloader = uniqueEmail('free-only');
    await createCompletedPaidOrder(api, publicApi, paid, { buyerEmail: spender, buyerFirstName: 'Bianca', buyerLastName: 'Bigspender' });
    await createOptedInOrder(publicApi, free, { email: spender, firstName: 'Bianca', lastName: 'Bigspender' });
    await createOptedInOrder(publicApi, free, { email: freeloader, firstName: 'Fred', lastName: 'Freeonly' });
    await buildAudience(api, organizerId);
    await waitForContact(api, organizerId, freeloader);
    await adminApi.grantMessagingPrivileges((await api.getAccount()).id);

    const segmentName = uniqueShort('Big spenders');
    const spendRule = { field: 'total_spent', operator: 'gte', value: 20, currency: 'USD' } as SegmentRule;
    const segment = await messaging.createSegment(segmentName, [spendRule]);
    const draftSubject = uniqueShort('For big spenders');
    const draft = await messaging.createCampaign({ name: draftSubject, subject: draftSubject, body: '<p>A thank-you for our best customers.</p>', segment_id: segment.id });
    const sentSubject = uniqueShort('Thanks, big spenders');
    const sent = await messaging.createCampaign({ name: sentSubject, subject: sentSubject, body: '<p>You have been generous.</p>', segment_id: segment.id });
    await messaging.sendCampaign(sent.id);
    await approveIfHeldForReview(adminApi, api, organizerId, sent.id);
    await waitForCampaignStatus(api, organizerId, sent.id, /^SENT$/);
    await mailpit.waitForMessage(spender, { subjectContains: sentSubject, timeoutMs: 45_000 });

    const ownerPage = await freshAccount.newAuthedPage();
    const owner = new AudiencePage(ownerPage, organizerId);
    await owner.gotoSegments();
    await expect(segmentRow(ownerPage, segmentName)).toContainText('1 person');
    await expect(segmentRow(ownerPage, segmentName)).not.toContainText(SPEND_HIDDEN);

    const renamed = uniqueShort('Top customers');
    const { page } = await invitedMember(freshAccount, mailpit, publicApi, browser, { role: 'ORGANIZER', permissions: { VIEW_FINANCIALS: false } });
    try {
      const audience = new AudiencePage(page, organizerId);
      await audience.gotoSegments();
      const row = segmentRow(page, segmentName);
      await expect(row).toContainText(SPEND_HIDDEN);
      await expect(row).not.toContainText(/\d+ (person|people)/);

      const counted = await page.request.get(`/api/organizers/${organizerId}/crm/segments/count`, {
        params: { segment_id: segment.id },
        headers: { Accept: 'application/json' },
      });
      expect(counted.ok()).toBe(false);
      expect(await counted.text()).not.toContain('contacts_count');

      await row.getByRole('button', { name: 'Segment actions' }).click();
      await page.getByRole('menuitem', { name: 'Edit' }).click();
      const editor = page.getByRole('dialog').filter({ hasText: 'Some rules are hidden' });
      await expect(editor).toContainText("This segment filters by spend, which your role can't see. You can rename it, but only a member who can view financials can change its rules.");
      await expect(editor.getByPlaceholder('Choose what to filter by')).toHaveCount(0);
      await expect(editor).not.toContainText('Total spent');
      await expect(editor.getByRole('textbox', { name: 'Value' })).toHaveCount(0);
      await editor.getByRole('textbox', { name: 'Name' }).fill(renamed);
      await editor.getByRole('button', { name: 'Save', exact: false }).last().click();
      await expect(editor).toBeHidden();
      await expect(segmentRow(page, renamed)).toContainText(SPEND_HIDDEN);

      await segmentRow(page, renamed).getByRole('link', { name: renamed, exact: true }).click();
      await expect(page).toHaveURL(/\/audience\/contacts\?segment=/);
      await expect(page.getByText("This segment filters by spend, which your role can't see. Pick another one to see its contacts.")).toBeVisible();
      await expect(audience.contactRows()).toHaveCount(0);
      await expect(page.getByText('Bianca Bigspender')).toHaveCount(0);

      const sharedLink = `?filter=${encodeURIComponent(JSON.stringify({ match: 'all', rules: [spendRule] }))}`;
      await audience.gotoContacts(sharedLink);
      const rejected = page.getByRole('alert').filter({ hasText: "This filter can't be applied." });
      await expect(rejected).toContainText('You do not have permission to filter by spend.');
      await expect(audience.contactRows()).toHaveCount(0);
      await rejected.getByRole('button', { name: 'Clear filter' }).click();
      await expect(audience.contactTotal()).toHaveText('2 contacts');

      await page.goto(`/manage/organizer/${organizerId}/audience/campaigns/${draft.id}`);
      await expect(page.getByText("Some of this campaign's audience rules filter by spend, so they're hidden from you. You can edit the message, but only a member who can view financials can change who receives it.")).toBeVisible();
      await expect(page.getByRole('combobox', { name: 'Send to' })).toBeDisabled();
      await expect(page.getByTestId('campaign-recipient-count')).not.toContainText(/\d+ of \d+/);
      const editedSubject = uniqueShort('Edited by a colleague');
      await page.getByTestId('campaign-subject').fill(editedSubject);
      await page.getByRole('button', { name: 'Save draft' }).click();
      await expect(page.getByText('Draft saved')).toBeVisible();
      await page.reload();
      await expect(page.getByTestId('campaign-subject')).toHaveValue(editedSubject);
      await expect(page.getByRole('combobox', { name: 'Send to' })).toBeDisabled();

      await page.goto(`/manage/organizer/${organizerId}/audience/campaigns/${sent.id}`);
      await expect(audience.campaignStatus()).toContainText('Sent');
      await expect(page.getByText("Some of this campaign's audience rules filter by spend, so they're hidden from you.")).toBeVisible();
      await expect(page.getByText('This campaign was sent to people filtered by spend, so only members who can view financials can see who received it.')).toBeVisible();
      await expect(page.getByTestId('campaign-recipient-row')).toHaveCount(0);
      await expect(page.getByText(spender)).toHaveCount(0);
    } finally {
      await page.context().close();
    }

    await owner.gotoSegments();
    await expect(segmentRow(ownerPage, renamed)).toContainText('1 person');
    await segmentRow(ownerPage, renamed).getByRole('link', { name: renamed, exact: true }).click();
    await expect(owner.contactTotal()).toHaveText('1 contact');
    await expect(owner.contactRow('Bianca Bigspender')).toBeVisible();
    await ownerPage.goto(`/manage/organizer/${organizerId}/audience/campaigns/${sent.id}`);
    await expect(ownerPage.getByTestId('campaign-recipient-row')).toContainText(spender);
  });
});
