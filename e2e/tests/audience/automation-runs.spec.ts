import type { APIRequestContext } from '@playwright/test';
import { expect, type FreshAccount } from '../../fixtures';
import type { AdminApiClient, ApiClient } from '../../api/api-client';
import { createLiveEventWithFreeTicket, createReservedOrder, type SeededEvent } from '../../api/factory';
import { completePublicOrder } from '../../api/public-client';
import { AudiencePage } from '../../pages/audience.page';
import { runAutomations } from '../../utils/artisan';
import { backdateAutomationSwitchOn, moveLastDailyRunToYesterday } from '../../utils/audience-db';
import { saasOnly } from '../../utils/mode';
import { uniqueEmail, uniqueShort } from '../../utils/unique';
import { buildAudience, createOptedInOrder, waitForContact } from './audience.shared';
import { moveOrderIntoThePast } from './contacts.helpers';
import {
  emailsAbout,
  expectOneAutomatedEmail,
  expectStillOneAfterAnotherRun,
  startEventDatesTomorrowEveningIn,
  timezoneWhereItIsMidday,
} from './coverage.helpers';
import { automationCard as card, automationSwitch as toggle, test, withMessagingApi, type Campaign, type MessagingApi } from './messaging.shared';

const subscribeWithAnOrder = async (publicApi: APIRequestContext, event: SeededEvent, email: string, name: { first: string; last: string }): Promise<string> => {
  const { orderShortId, sessionId } = await createReservedOrder(publicApi, event);
  await completePublicOrder(publicApi, event.eventId, orderShortId, sessionId, {
    order: { first_name: name.first, last_name: name.last, email, opted_into_marketing: true } as never,
    attendees: [{ product_id: event.productId, product_price_id: event.priceId, first_name: name.first, last_name: name.last, email }],
  });
  return orderShortId;
};

const arrangeRunWaitingForReview = async (
  { freshAccount, publicApi, adminApi, messaging }: { freshAccount: FreshAccount; publicApi: APIRequestContext; adminApi: AdminApiClient; messaging: MessagingApi },
): Promise<{ name: string; subject: string; joiner: string; run: Campaign }> => {
  const { api, organizerId } = freshAccount;
  const event = await createLiveEventWithFreeTicket(api, organizerId);
  await createOptedInOrder(publicApi, event, { email: uniqueEmail('already-in'), firstName: 'Alma', lastName: 'Already' });
  await buildAudience(api, organizerId);
  await adminApi.setAccountVerification((await api.getAccount()).id, true);
  const name = uniqueShort('Greet subscribers');
  const subject = uniqueShort('Glad you joined');
  const automation = await messaging.createAutomation('SEGMENT_ENTERED', { name, subject });
  await messaging.switchOnAutomation(automation.id);
  runAutomations();
  const joiner = uniqueEmail('joiner');
  await createOptedInOrder(publicApi, event, { email: joiner, firstName: 'Jo', lastName: 'Joiner' });
  await waitForContact(api, organizerId, joiner);

  let run: Campaign | undefined;
  await expect(async () => {
    runAutomations();
    run = (await messaging.automatedCampaigns()).find((campaign) => campaign.status === 'PENDING_REVIEW');
    expect(run).toBeDefined();
  }).toPass({ timeout: 45_000, intervals: [2_000, 4_000] });
  return { name, subject, joiner, run: run! };
};

const campaignStatusOf = (api: ApiClient, organizerId: number, campaignId: number): Promise<string> =>
  api.getAudienceCampaign(organizerId, campaignId).then((campaign) => campaign.status);

test.describe('audience automation runs', () => {
  test('the day before a date, one reminder goes to ticket holders and another to subscribers who have not booked, each once', async ({ freshAccount, publicApi, mailpit, adminApi, messaging }) => {
    test.slow();
    const { api, organizerId } = freshAccount;
    const event = await createLiveEventWithFreeTicket(api, organizerId);
    const other = await createLiveEventWithFreeTicket(api, organizerId);
    const holder = uniqueEmail('holder');
    const notBooked = uniqueEmail('not-booked');
    const silentHolder = uniqueEmail('silent-holder');
    await subscribeWithAnOrder(publicApi, event, holder, { first: 'Holly', last: 'Holder' });
    await subscribeWithAnOrder(publicApi, other, notBooked, { first: 'Nat', last: 'Notbooked' });
    const { orderShortId, sessionId } = await createReservedOrder(publicApi, event);
    await completePublicOrder(publicApi, event.eventId, orderShortId, sessionId, {
      order: { first_name: 'Si', last_name: 'Lent', email: silentHolder },
      attendees: [{ product_id: event.productId, product_price_id: event.priceId, first_name: 'Si', last_name: 'Lent', email: silentHolder }],
    });
    await buildAudience(api, organizerId);
    await waitForContact(api, organizerId, silentHolder);
    await adminApi.grantMessagingPrivileges((await api.getAccount()).id);

    const reminder = { name: uniqueShort('Remind holders'), subject: uniqueShort('See you tomorrow') };
    const nudge = { name: uniqueShort('Nudge the rest'), subject: uniqueShort('Still time to book') };
    const forHolders = await messaging.createAutomation('BEFORE_EVENT', { ...reminder, days_before: 1 });
    const forTheRest = await messaging.createAutomation('BEFORE_EVENT', { ...nudge, days_before: 1, occurrence_audience: 'not_purchased' });
    await messaging.switchOnAutomation(forHolders.id);
    await messaging.switchOnAutomation(forTheRest.id);
    backdateAutomationSwitchOn(organizerId, 5);
    startEventDatesTomorrowEveningIn(event.eventId, timezoneWhereItIsMidday());

    await expectOneAutomatedEmail({ messaging, adminApi, mailpit, to: holder, subject: reminder.subject });
    await expectOneAutomatedEmail({ messaging, adminApi, mailpit, to: notBooked, subject: nudge.subject });
    await expectStillOneAfterAnotherRun({ messaging, adminApi, mailpit, to: holder, subject: reminder.subject });
    expect(await emailsAbout(mailpit, notBooked, nudge.subject)).toBe(1);
    expect(await emailsAbout(mailpit, holder, nudge.subject)).toBe(0);
    expect(await emailsAbout(mailpit, notBooked, reminder.subject)).toBe(0);
    expect(await emailsAbout(mailpit, silentHolder, reminder.subject)).toBe(0);
    expect(await emailsAbout(mailpit, silentHolder, nudge.subject)).toBe(0);

    const page = await freshAccount.newAuthedPage();
    const audience = new AudiencePage(page, organizerId);
    await audience.gotoAutomations();
    await expect(card(page, reminder.name)).toContainText('1 day before each date · ticket holders');
    await expect(card(page, reminder.name)).toContainText(/1 email sent, most recently/);
    await expect(card(page, nudge.name)).toContainText('1 day before each date · subscribers without a ticket');
    await expect(card(page, nudge.name)).toContainText(/1 email sent, most recently/);

    await audience.gotoCampaigns();
    await page.getByRole('radiogroup', { name: 'Filter campaigns' }).getByText(/^Automated/).click();
    await audience.campaignRow(reminder.subject).getByRole('link', { name: reminder.subject }).click();
    await expect(page.getByTestId('campaign-recipient-row')).toHaveCount(1);
    await expect(page.getByTestId('campaign-recipient-row')).toContainText(holder);
  });

  test('a regular who has not booked for six months gets the win-back email once, and a regular who booked recently does not', async ({ freshAccount, publicApi, mailpit, adminApi, playwright }) => {
    test.slow();
    const { api } = freshAccount;
    const organizer = await api.createOrganizer(uniqueShort('Win back Org'), { timezone: timezoneWhereItIsMidday() });
    const first = await createLiveEventWithFreeTicket(api, organizer.id);
    const second = await createLiveEventWithFreeTicket(api, organizer.id);
    const lapsed = uniqueEmail('lapsed');
    const recent = uniqueEmail('recent');
    const lapsedOnce = uniqueEmail('lapsed-once');
    for (const event of [first, second]) {
      moveOrderIntoThePast(await subscribeWithAnOrder(publicApi, event, lapsed, { first: 'Lara', last: 'Lapsed' }), 200);
      await subscribeWithAnOrder(publicApi, event, recent, { first: 'Rory', last: 'Recent' });
    }
    moveOrderIntoThePast(await subscribeWithAnOrder(publicApi, first, lapsedOnce, { first: 'Olly', last: 'Once' }), 200);
    await buildAudience(api, organizer.id);
    await waitForContact(api, organizer.id, lapsedOnce);
    await adminApi.grantMessagingPrivileges((await api.getAccount()).id);

    await withMessagingApi(playwright, { ...freshAccount, organizerId: organizer.id }, async (messaging) => {
      const subject = uniqueShort('We miss you');
      const automation = await messaging.createAutomation('WIN_BACK', { subject });
      await messaging.switchOnAutomation(automation.id);

      await expectOneAutomatedEmail({ messaging, adminApi, mailpit, to: lapsed, subject });
      await expectStillOneAfterAnotherRun({ messaging, adminApi, mailpit, to: lapsed, subject });
      moveLastDailyRunToYesterday(organizer.id);
      await expectStillOneAfterAnotherRun({ messaging, adminApi, mailpit, to: lapsed, subject });
      expect(await emailsAbout(mailpit, recent, subject)).toBe(0);
      expect(await emailsAbout(mailpit, lapsedOnce, subject)).toBe(0);
    });

    const page = await freshAccount.newAuthedPage();
    await new AudiencePage(page, organizer.id).gotoAutomations();
    await expect(card(page, 'Win back lapsed regulars')).toContainText('Once a day · Lapsed regulars');
    await expect(card(page, 'Win back lapsed regulars')).toContainText(/1 email sent, most recently/);
  });

  test('on Hi.Events Cloud an automated email waiting for review can be cancelled, and the people it was for are not emailed later', async ({ freshAccount, publicApi, mailpit, adminApi, messaging }) => {
    saasOnly();
    test.slow();
    const { api, organizerId } = freshAccount;
    const { name, subject, joiner, run } = await arrangeRunWaitingForReview({ freshAccount, publicApi, adminApi, messaging });

    const page = await freshAccount.newAuthedPage();
    const audience = new AudiencePage(page, organizerId);
    await page.goto(`/manage/organizer/${organizerId}/audience/campaigns/${run.id}`);
    await expect(audience.campaignStatus()).toContainText('Waiting for review');
    await expect(page.getByTestId('campaign-recipient-count')).toContainText('1 recipient');
    await expect(page.getByRole('button', { name: 'Move back to draft' })).toHaveCount(0);

    await page.getByRole('button', { name: 'Cancel this email' }).click();
    const confirm = page.getByRole('dialog').filter({ hasText: 'Cancel this automated email?' });
    await expect(confirm).toContainText("The people it was going to won't get this email, and this can't be undone. The automation stays on and keeps sending later emails.");
    await confirm.getByRole('button', { name: 'Keep it', exact: true }).click();
    await expect(confirm).toBeHidden();
    await expect(audience.campaignStatus()).toContainText('Waiting for review');
    expect(await campaignStatusOf(api, organizerId, run.id)).toBe('PENDING_REVIEW');

    await page.getByRole('button', { name: 'Cancel this email' }).click();
    await confirm.getByRole('button', { name: 'Cancel this email', exact: true }).click();
    await expect(page.getByText('Email cancelled')).toBeVisible();
    await expect(audience.campaignStatus()).toContainText('Cancelled');
    await expect(page.getByText('This campaign was cancelled before it went out, so nobody received it.')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Cancel this email' })).toHaveCount(0);
    await expect(page.getByTestId('campaign-subject')).toHaveCount(0);
    await expect(adminApi.approveAudienceCampaign(run.id)).rejects.toThrow('is not waiting for review');

    runAutomations();
    runAutomations();
    const runs = await messaging.automatedCampaigns();
    expect(runs.filter((campaign) => campaign.status === 'PENDING_REVIEW')).toHaveLength(0);
    expect(await emailsAbout(mailpit, joiner, subject)).toBe(0);

    await audience.gotoAutomations();
    await expect(toggle(page, name)).toBeChecked();
    await expect(card(page, name)).toContainText('Nothing sent yet.');
  });

  test('on Hi.Events Cloud an automated email the reviewer rejects reaches nobody, and its page explains why and points back to the automation', async ({ freshAccount, publicApi, mailpit, adminApi, messaging, superAdminPage }) => {
    saasOnly();
    test.slow();
    const { organizerId } = freshAccount;
    const { name, subject, joiner, run } = await arrangeRunWaitingForReview({ freshAccount, publicApi, adminApi, messaging });
    const reason = 'The greeting needs your venue name.';

    await superAdminPage.goto('/admin/audience-campaigns');
    const row = superAdminPage.getByRole('row').filter({ hasText: subject });
    await row.getByRole('button', { name: 'Review' }).click();
    const review = superAdminPage.getByRole('dialog').filter({ hasText: subject });
    await review.getByRole('button', { name: 'Reject', exact: true }).click();
    await review.getByLabel('Reason for the organizer (optional)').fill(reason);
    await review.getByRole('button', { name: 'Reject campaign' }).click();
    await expect(review).toBeHidden();
    await expect(row).toHaveCount(0);

    const page = await freshAccount.newAuthedPage();
    const audience = new AudiencePage(page, organizerId);
    await audience.gotoCampaigns();
    await page.getByRole('radiogroup', { name: 'Filter campaigns' }).getByText(/^Automated/).click();
    await audience.campaignRow(subject).getByRole('link', { name: subject }).click();
    await expect(page).toHaveURL(new RegExp(`/audience/campaigns/${run.id}$`));
    await expect(audience.campaignStatus()).toContainText('Cancelled');
    await expect(page.getByText("This automated email wasn't approved to send, so nobody received it. Check the automation's email")).toBeVisible();
    await expect(page.getByTestId('campaign-review-note')).toContainText(reason);
    await expect(page.getByTestId('campaign-subject')).toHaveCount(0);
    await expect(page.getByRole('button', { name: 'Cancel this email' })).toHaveCount(0);
    expect(await emailsAbout(mailpit, joiner, subject)).toBe(0);

    await page.getByRole('link', { name: 'Go to automations' }).click();
    await expect(page).toHaveURL(/\/audience\/automations$/);
    await expect(toggle(page, name)).toBeChecked();
    runAutomations();
    expect(await emailsAbout(mailpit, joiner, subject)).toBe(0);
  });

  test('on Hi.Events Cloud switching an automation off cancels its email that is waiting for review', async ({ freshAccount, publicApi, mailpit, adminApi, messaging }) => {
    saasOnly();
    test.slow();
    const { api, organizerId } = freshAccount;
    const { name, subject, joiner, run } = await arrangeRunWaitingForReview({ freshAccount, publicApi, adminApi, messaging });

    const page = await freshAccount.newAuthedPage();
    const audience = new AudiencePage(page, organizerId);
    await audience.gotoAutomations();
    await expect(toggle(page, name)).toBeChecked();
    await toggle(page, name).press('Space');
    await expect(page.getByText('Automation turned off')).toBeVisible();
    await expect(toggle(page, name)).not.toBeChecked();
    await expect.poll(() => campaignStatusOf(api, organizerId, run.id)).toBe('CANCELLED');
    await expect(adminApi.approveAudienceCampaign(run.id)).rejects.toThrow('is not waiting for review');

    await page.goto(`/manage/organizer/${organizerId}/audience/campaigns/${run.id}`);
    await expect(audience.campaignStatus()).toContainText('Cancelled');
    await expect(page.getByText('This automated email was stopped because its automation was switched off or deleted. Nobody else will get it.')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Cancel this email' })).toHaveCount(0);

    await audience.gotoCampaigns();
    await page.getByRole('radiogroup', { name: 'Filter campaigns' }).getByText(/^Automated/).click();
    await expect(page.getByText('Nothing has been sent by your automations yet.')).toBeVisible();
    await expect(audience.campaignRow(subject)).toHaveCount(0);
    runAutomations();
    expect(await emailsAbout(mailpit, joiner, subject)).toBe(0);
  });
});
