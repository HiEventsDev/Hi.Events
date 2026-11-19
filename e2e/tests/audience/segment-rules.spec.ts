import type { Locator, Page } from '@playwright/test';
import { expect } from '../../fixtures';
import { createCompletedPaidOrder, createLiveEventWithFreeTicket, createLiveEventWithPaidTicket } from '../../api/factory';
import { AudiencePage } from '../../pages/audience.page';
import { uniqueEmail, uniqueShort } from '../../utils/unique';
import { askForMobileAtCheckout, buildAudience, createOptedInOrder, UK_MOBILE, waitForContact } from './audience.shared';
import { chooseRule, moveOrderIntoThePast, segmentRow } from './contacts.helpers';
import { approveIfHeldForReview, test, waitForCampaignStatus } from './messaging.shared';

const tomorrow = (): string => new Date(Date.now() + 24 * 60 * 60 * 1000).toISOString().slice(0, 19).replace('T', ' ');

const chooseCondition = async (page: Page, panel: Locator, condition: string): Promise<void> => {
  await panel.getByRole('combobox', { name: 'Condition' }).click();
  await page.getByRole('option', { name: condition, exact: true }).click();
};

const startFilterOn = async (page: Page, audience: AudiencePage, field: string): Promise<Locator> => {
  await audience.gotoContacts();
  await expect(audience.contactTotal()).toHaveText('2 contacts');
  await audience.filterButton().click();
  await chooseRule(page, audience.filterPanel(), field);
  return audience.filterPanel();
};

test.describe('audience segment rules', () => {
  test('contacts are filtered by what they spent, when they last ordered and whether they gave a mobile number', async ({ freshAccount, publicApi }) => {
    test.slow();
    const { api, organizerId } = freshAccount;
    const paid = await createLiveEventWithPaidTicket(api, organizerId, 25);
    const free = await createLiveEventWithFreeTicket(api, organizerId);
    await askForMobileAtCheckout(api, free.eventId);
    const spender = uniqueEmail('old-spender');
    const newcomer = uniqueEmail('new-mobile');
    const old = await createCompletedPaidOrder(api, publicApi, paid, { buyerEmail: spender, buyerFirstName: 'Otto', buyerLastName: 'Oldspender' });
    moveOrderIntoThePast(old.orderShortId, 100);
    await createOptedInOrder(publicApi, free, { email: newcomer, firstName: 'Nina', lastName: 'Newmobile', phone: UK_MOBILE });
    await buildAudience(api, organizerId);
    await waitForContact(api, organizerId, newcomer);

    const page = await freshAccount.newAuthedPage();
    const audience = new AudiencePage(page, organizerId);
    const onlyShows = async (name: string): Promise<void> => {
      await expect(audience.contactTotal()).toHaveText('1 contact');
      await expect(audience.contactRow(name)).toBeVisible();
    };

    let panel = await startFilterOn(page, audience, 'Total spent');
    await panel.getByRole('textbox', { name: 'Amount' }).fill('20');
    await expect(panel.getByRole('combobox', { name: 'Currency' })).toHaveValue('USD');
    await onlyShows('Otto Oldspender');
    await panel.getByRole('textbox', { name: 'Amount' }).fill('30');
    await expect(page.getByText('Nobody matches this search or filter.')).toBeVisible();

    panel = await startFilterOn(page, audience, 'Last order');
    await chooseCondition(page, panel, 'more than');
    await panel.getByRole('textbox', { name: 'Days' }).fill('30');
    await onlyShows('Otto Oldspender');
    await chooseCondition(page, panel, 'in the last');
    await onlyShows('Nina Newmobile');

    panel = await startFilterOn(page, audience, 'Has a mobile number');
    await onlyShows('Nina Newmobile');
    await chooseCondition(page, panel, 'no');
    await onlyShows('Otto Oldspender');
    await expect(page).toHaveURL(/filter=/);
  });

  test('a segment that a scheduled campaign or a switched-on automation sends to cannot be deleted until they stop using it', async ({ freshAccount, publicApi, adminApi, messaging }) => {
    test.slow();
    const { api, organizerId } = freshAccount;
    const event = await createLiveEventWithFreeTicket(api, organizerId);
    await createOptedInOrder(publicApi, event, { email: uniqueEmail('member'), firstName: 'Mel', lastName: 'Member' });
    await buildAudience(api, organizerId);
    await adminApi.grantMessagingPrivileges((await api.getAccount()).id);
    const forCampaign = uniqueShort('Campaign crowd');
    const forAutomation = uniqueShort('Automation crowd');
    const rules = [{ field: 'events_count', operator: 'gte', value: 1 }];
    const campaignSegment = await messaging.createSegment(forCampaign, rules);
    const automationSegment = await messaging.createSegment(forAutomation, rules);
    const subject = uniqueShort('Going out tomorrow');
    const scheduled = await messaging.createCampaign({ name: subject, subject, body: '<p>See you soon.</p>', segment_id: campaignSegment.id });
    await messaging.sendCampaign(scheduled.id, tomorrow());
    await approveIfHeldForReview(adminApi, api, organizerId, scheduled.id);
    await waitForCampaignStatus(api, organizerId, scheduled.id, /^SCHEDULED$/);
    const automationName = uniqueShort('Greet the crowd');
    const automation = await messaging.createAutomation('SEGMENT_ENTERED', { name: automationName, segment_id: automationSegment.id, preset: null });
    await messaging.switchOnAutomation(automation.id);

    const page = await freshAccount.newAuthedPage();
    const audience = new AudiencePage(page, organizerId);
    await audience.gotoSegments();
    const tryToDelete = async (name: string): Promise<void> => {
      await segmentRow(page, name).getByRole('button', { name: 'Segment actions' }).click();
      await page.getByRole('menuitem', { name: 'Delete' }).click();
    };

    await tryToDelete(forCampaign);
    const blocked = page.getByText(/^This segment can't be deleted while it's still in use: .+\. Cancel the campaign or change who the automation sends to first\.$/);
    await expect(blocked.filter({ hasText: `${subject} (scheduled campaign)` })).toBeVisible();
    await expect(page.getByRole('dialog').filter({ hasText: `Delete the segment "${forCampaign}"?` })).toHaveCount(0);
    await page.reload();
    await tryToDelete(forAutomation);
    await expect(blocked.filter({ hasText: `${automationName} (automation, on)` })).toBeVisible();
    await expect(page.getByRole('dialog').filter({ hasText: `Delete the segment "${forAutomation}"?` })).toHaveCount(0);
    await page.reload();
    await expect(segmentRow(page, forCampaign)).toBeVisible();
    await expect(segmentRow(page, forAutomation)).toBeVisible();

    await page.goto(`/manage/organizer/${organizerId}/audience/campaigns/${scheduled.id}`);
    await page.getByRole('button', { name: 'Move back to draft' }).click();
    await expect(page.getByText('Moved back to draft')).toBeVisible();
    await audience.gotoSegments();
    await tryToDelete(forCampaign);
    const confirm = page.getByRole('dialog').filter({ hasText: `Delete the segment "${forCampaign}"?` });
    await expect(confirm).toContainText("It's still in use:");
    await expect(confirm).toContainText(subject);
    await confirm.getByRole('button', { name: 'Delete segment' }).click();
    await expect(page.getByText('Segment deleted')).toBeVisible();
    await expect(segmentRow(page, forCampaign)).toHaveCount(0);
    await expect(segmentRow(page, forAutomation)).toBeVisible();
  });
});
