import type { APIRequestContext } from '@playwright/test';
import { expect } from '../../fixtures';
import type { AdminApiClient } from '../../api/api-client';
import { createCompletedOrder, createDraftEventWithTicket, createLiveEventWithFreeTicket, createLiveEventWithPaidTicket, createReservedOrder } from '../../api/factory';
import { completePublicOrder } from '../../api/public-client';
import { AudiencePage } from '../../pages/audience.page';
import { runAutomations } from '../../utils/artisan';
import { abandonCheckoutHoursAgo, backdateAutomationSwitchOn, endEventDatesAnHourAgo, moveLastDailyRunToYesterday, recordAutomationFailure, settlePublishedEvent } from '../../utils/audience-db';
import type { MailpitClient } from '../../utils/mailpit';
import { API_BASE_URL } from '../../utils/env';
import { saasOnly } from '../../utils/mode';
import { uniqueEmail, uniqueShort } from '../../utils/unique';
import { buildAudience, createOptedInOrder, waitForContact } from './audience.shared';
import { expectNoSidewaysScroll, PHONE_VIEWPORT } from './contacts.helpers';
import { automationCard as card, automationSwitch as toggle, contactIdOf, test, withMessagingApi, type MessagingApi } from './messaging.shared';

const emailsAbout = async (mailpit: MailpitClient, to: string, subject: string): Promise<number> =>
  (await mailpit.search(to)).filter((message) => message.Subject.includes(subject)).length;

const runAutomationsAndApproveRuns = async (messaging: MessagingApi, adminApi: AdminApiClient): Promise<void> => {
  runAutomations();
  for (const campaign of await messaging.automatedCampaigns()) {
    if (campaign.status === 'PENDING_REVIEW') {
      await adminApi.approveAudienceCampaign(campaign.id);
    }
  }
};

const expectOneAutomatedEmail = async (
  { messaging, adminApi, mailpit, to, subject }: { messaging: MessagingApi; adminApi: AdminApiClient; mailpit: MailpitClient; to: string; subject: string },
): Promise<void> => {
  await expect(async () => {
    await runAutomationsAndApproveRuns(messaging, adminApi);
    expect(await emailsAbout(mailpit, to, subject)).toBe(1);
  }).toPass({ timeout: 45_000, intervals: [2_000, 4_000] });
};

const expectStillOneAfterAnotherRun = async (
  { messaging, adminApi, mailpit, to, subject }: { messaging: MessagingApi; adminApi: AdminApiClient; mailpit: MailpitClient; to: string; subject: string },
): Promise<void> => {
  await runAutomationsAndApproveRuns(messaging, adminApi);
  await expect.poll(async () => (await messaging.automatedCampaigns()).every((campaign) => campaign.status === 'SENT'), { timeout: 45_000 }).toBe(true);
  expect(await emailsAbout(mailpit, to, subject)).toBe(1);
};

const leaveCheckoutUnfinished = async (publicApi: APIRequestContext, event: Awaited<ReturnType<typeof createLiveEventWithPaidTicket>>, email: string): Promise<string> => {
  const { orderShortId, sessionId } = await createReservedOrder(publicApi, event);
  await completePublicOrder(publicApi, event.eventId, orderShortId, sessionId, {
    order: { first_name: 'Abby', last_name: 'Abandon', email },
    attendees: [{ product_id: event.productId, product_price_id: event.priceId, first_name: 'Abby', last_name: 'Abandon', email }],
  });
  return orderShortId;
};

const FIXED_OFFSET_TIMEZONES = [
  'Pacific/Pago_Pago', 'Pacific/Honolulu', 'Pacific/Gambier', 'Pacific/Pitcairn', 'America/Phoenix', 'America/Costa_Rica',
  'America/Bogota', 'America/La_Paz', 'America/Argentina/Buenos_Aires', 'America/Noronha', 'Atlantic/Cape_Verde', 'UTC',
  'Africa/Lagos', 'Africa/Johannesburg', 'Africa/Nairobi', 'Asia/Dubai', 'Asia/Karachi', 'Asia/Dhaka',
  'Asia/Bangkok', 'Asia/Singapore', 'Asia/Tokyo', 'Pacific/Port_Moresby', 'Pacific/Guadalcanal', 'Pacific/Tarawa',
];

const hourIn = (timezone: string): number =>
  Number(new Intl.DateTimeFormat('en-GB', { timeZone: timezone, hour: '2-digit', hourCycle: 'h23' }).format(new Date()));

const timezoneWhereItIsMidAfternoon = (): string => FIXED_OFFSET_TIMEZONES.find((timezone) => hourIn(timezone) === 15)!;

test.describe('audience automations', () => {
  test('an automation is created from a template, previewed before it is turned on, and stays on until switched off', async ({ freshAccount, publicApi, mailpit, adminApi }) => {
    const { api, organizerId } = freshAccount;
    const event = await createLiveEventWithFreeTicket(api, organizerId);
    await createOptedInOrder(publicApi, event, { email: uniqueEmail('fan'), firstName: 'Fay', lastName: 'Fan' });
    await createCompletedOrder(publicApi, event, { buyerEmail: uniqueEmail('quiet'), buyerFirstName: 'Quin', buyerLastName: 'Quiet' });
    await buildAudience(api, organizerId);
    await adminApi.grantMessagingPrivileges((await api.getAccount()).id);

    const page = await freshAccount.newAuthedPage();
    const audience = new AudiencePage(page, organizerId);
    await audience.gotoCampaigns();
    await audience.automationsTab().click();
    await expect(page).toHaveURL(/\/audience\/automations$/);
    await expect(page.getByText('Set up your first automation')).toBeVisible();

    await page.getByTestId('automation-template-EVENT_PUBLISHED').click();
    await page.getByTestId('automation-save').click();
    await expect(page.getByText("Automation created. Turn it on when you're ready.")).toBeVisible();
    const announce = card(page, 'Announce new events');
    await expect(announce).toContainText('When you publish an event · Everyone who opted in');
    await expect(announce).toContainText('Nothing sent yet.');
    await expect(toggle(page, 'Announce new events')).not.toBeChecked();

    await toggle(page, 'Announce new events').press('Space');
    const confirm = page.getByRole('dialog', { name: 'Turn on "Announce new events"?' });
    await expect(confirm.getByTestId('automation-confirm-count')).toHaveText('1 person who opted in would get the next announcement, as of now.');
    await expect(confirm.getByTestId('automation-confirm-first-send')).toHaveText("It goes out about 30 minutes after you next publish an event. Events that are already on sale won't be announced.");
    await expect(confirm.getByTitle('Email preview')).toBeVisible();
    await confirm.getByRole('button', { name: 'Cancel' }).click();
    await expect(confirm).toBeHidden();
    await expect(toggle(page, 'Announce new events')).not.toBeChecked();

    await toggle(page, 'Announce new events').press('Space');
    await confirm.getByTestId('automation-confirm-test').click();
    const testEmail = await mailpit.waitForMessage(freshAccount.email, { timeoutMs: 45_000, subjectContains: '[Test] ' });
    await expect(confirm).toContainText(testEmail.Subject.replace('[Test] ', ''));
    await confirm.getByTestId('automation-confirm-turn-on').click();
    await expect(confirm).toBeHidden();
    await expect(page.getByText('Automation turned on')).toBeVisible();
    await expect(toggle(page, 'Announce new events')).toBeChecked();
    await expect(announce).toContainText('Nothing sent yet.');

    await page.reload();
    await expect(toggle(page, 'Announce new events')).toBeChecked();

    await card(page, 'Announce new events').getByTestId('automation-edit').click();
    await page.getByTestId('automation-name').fill('Announce shows');
    await page.getByTestId('automation-save').click();
    const review = page.getByRole('dialog', { name: 'Save changes to "Announce new events"?' });
    await expect(review.getByTestId('automation-confirm-count')).toHaveText('1 person who opted in would get the next announcement, as of now.');
    await review.getByRole('button', { name: 'Back to editing' }).click();
    await expect(page.getByTestId('automation-name')).toHaveValue('Announce shows');
    await page.getByTestId('automation-save').click();
    await review.getByTestId('automation-confirm-turn-on').click();
    await expect(page.getByText('Automation saved')).toBeVisible();
    await expect(toggle(page, 'Announce shows')).toBeChecked();
    await expect(card(page, 'Announce shows')).toContainText('Announce new events');

    await toggle(page, 'Announce shows').press('Space');
    await expect(page.getByText('Automation turned off')).toBeVisible();
    await page.reload();
    await expect(toggle(page, 'Announce shows')).not.toBeChecked();
  });

  test('an abandoned checkout automation explains who it reaches and can be edited and deleted', async ({ freshAccount }) => {
    const { api, organizerId } = freshAccount;
    await buildAudience(api, organizerId);
    const page = await freshAccount.newAuthedPage();
    const audience = new AudiencePage(page, organizerId);
    await audience.gotoAutomations();

    await page.getByTestId('automation-template-ABANDONED_CHECKOUT').click();
    await page.getByTestId('automation-name').fill('Bring back browsers');
    await page.getByTestId('automation-save').click();
    const recovery = card(page, 'Bring back browsers');
    await expect(recovery).toContainText('1 hour after a checkout is abandoned · Everyone who opted in');

    await recovery.getByTestId('automation-edit').click();
    await page.getByTestId('automation-wait').click();
    await page.getByRole('option', { name: '3 hours after they leave' }).click();
    await page.getByTestId('automation-save').click();
    await expect(recovery).toContainText('3 hours after a checkout is abandoned');

    await toggle(page, 'Bring back browsers').press('Space');
    const confirm = page.getByRole('dialog', { name: 'Turn on "Bring back browsers"?' });
    await expect(confirm.getByTestId('automation-confirm-count')).toHaveText('Reaches subscribers who abandon a checkout from now on. Nobody gets it straight away.');
    await expect(confirm.getByTestId('automation-confirm-first-send')).toHaveText('It goes out about 3 hours after each abandoned checkout.');
    await confirm.getByRole('button', { name: 'Cancel' }).click();

    await page.getByTestId('automation-new-button').click();
    await page.getByTestId('automation-template-BEFORE_EVENT').click();
    await page.getByTestId('automation-days-before').fill('2');
    await page.getByTestId('automation-save').click();
    await expect(card(page, 'Remind people before an event')).toContainText('2 days before each date · ticket holders');

    await recovery.getByTestId('automation-menu').click();
    await page.getByTestId('automation-delete').click();
    await audience.confirm('Delete automation');
    await expect(page.getByText('Automation deleted')).toBeVisible();
    await expect(card(page, 'Bring back browsers')).toHaveCount(0);
    await expect(card(page, 'Remind people before an event')).toHaveCount(1);
  });

  test('each kind of automation says who it reaches and when it first sends before it is turned on', async ({ freshAccount, publicApi, messaging }) => {
    const { api, organizerId } = freshAccount;
    const event = await createLiveEventWithFreeTicket(api, organizerId);
    await createOptedInOrder(publicApi, event, { email: uniqueEmail('holder'), firstName: 'Holly', lastName: 'Holder' });
    await buildAudience(api, organizerId);
    const segmentName = uniqueShort('Front row');
    await messaging.createSegment(segmentName, [{ field: 'events_count', operator: 'gte', value: 1 }]);

    const page = await freshAccount.newAuthedPage();
    await new AudiencePage(page, organizerId).gotoAutomations();
    const confirm = page.getByRole('dialog', { name: /^Turn on "/ });
    const reach = confirm.getByTestId('automation-confirm-count');
    const firstSend = confirm.getByTestId('automation-confirm-first-send');
    const closeConfirmation = async () => {
      await confirm.getByRole('button', { name: 'Cancel' }).click();
      await expect(confirm).toBeHidden();
    };

    await page.getByTestId('automation-template-WELCOME').click();
    await page.getByTestId('automation-save').click();
    await expect(card(page, 'Welcome first-timers')).toContainText('Once a day · First-timers');
    await toggle(page, 'Welcome first-timers').press('Space');
    await expect(reach).toHaveText('Reaches people who book with you for the first time from now on. Nobody gets it straight away.');
    await expect(firstSend).toHaveText(/^First run: .+\. After that it runs once a day\.$/);
    await closeConfirmation();

    await page.getByTestId('automation-new-button').click();
    await page.getByTestId('automation-template-SEGMENT_ENTERED').click();
    const editor = page.getByRole('dialog', { name: 'Email people who join a segment' });
    await editor.getByRole('combobox', { name: 'When someone joins' }).click();
    await page.getByRole('option', { name: segmentName }).click();
    await expect(editor).toContainText("People already in the segment when you turn this on won't get it. Each person gets it once.");
    await page.getByTestId('automation-save').click();
    await expect(card(page, 'Email people who join a segment')).toContainText(`When someone joins the segment · ${segmentName}`);
    await toggle(page, 'Email people who join a segment').press('Space');
    await expect(reach).toHaveText("Reaches people who join the segment from now on. People already in it won't get it.");
    await expect(firstSend).toHaveText(/^Starts: .+\. From then on, people who join get it within 15 minutes\.$/);
    await closeConfirmation();

    await page.getByTestId('automation-new-button').click();
    await page.getByTestId('automation-template-AFTER_EVENT').click();
    await page.getByRole('dialog', { name: 'Say thanks after an event' }).getByRole('textbox', { name: 'Days after the event ends' }).fill('2');
    await page.getByTestId('automation-save').click();
    await expect(card(page, 'Say thanks after an event')).toContainText('2 days after each date ends · ticket holders');
    await toggle(page, 'Say thanks after an event').press('Space');
    await expect(reach).toHaveText('1 ticket holder who opted in would get the next one.');
    await expect(firstSend).toContainText(`after ${event.title} on`);
    await closeConfirmation();

    await page.getByTestId('automation-new-button').click();
    await page.getByTestId('automation-template-WIN_BACK').click();
    await page.getByTestId('automation-save').click();
    await expect(card(page, 'Win back lapsed regulars')).toContainText('Once a day · Lapsed regulars');
    await toggle(page, 'Win back lapsed regulars').press('Space');
    await expect(reach).toHaveText('0 people who opted in would get it on the next run, as of now. Nobody gets it more than twice a year.');
    await expect(firstSend).toHaveText(/^First run: .+\. After that it runs once a day\.$/);
    await closeConfirmation();
    await expect(toggle(page, 'Win back lapsed regulars')).not.toBeChecked();
  });

  test('someone who joins a segment gets its email once, and the run shows on the automation and under Automated campaigns', async ({ freshAccount, publicApi, mailpit, adminApi, messaging }) => {
    test.slow();
    const { api, organizerId } = freshAccount;
    const event = await createLiveEventWithFreeTicket(api, organizerId);
    const already = uniqueEmail('already-vip');
    const joiner = uniqueEmail('new-vip');
    await createOptedInOrder(publicApi, event, { email: already, firstName: 'Alma', lastName: 'Already' });
    await createOptedInOrder(publicApi, event, { email: joiner, firstName: 'Jo', lastName: 'Joiner' });
    await buildAudience(api, organizerId);
    await adminApi.grantMessagingPrivileges((await api.getAccount()).id);
    const tag = await messaging.createTag(uniqueShort('VIP'));
    const segment = await messaging.createSegment(uniqueShort('VIPs'), [{ field: 'tag', operator: 'in', value: [tag.id] }]);
    await messaging.tagContact(await contactIdOf(api, organizerId, already), [tag.id]);
    const name = uniqueShort('Greet VIPs');
    const subject = uniqueShort('Welcome to the VIP list');
    const automation = await messaging.createAutomation('SEGMENT_ENTERED', { name, subject, segment_id: segment.id, preset: null });
    await messaging.switchOnAutomation(automation.id);
    runAutomations();

    await messaging.tagContact(await contactIdOf(api, organizerId, joiner), [tag.id]);
    await expectOneAutomatedEmail({ messaging, adminApi, mailpit, to: joiner, subject });
    await expectStillOneAfterAnotherRun({ messaging, adminApi, mailpit, to: joiner, subject });
    expect(await emailsAbout(mailpit, already, subject)).toBe(0);

    const page = await freshAccount.newAuthedPage();
    const audience = new AudiencePage(page, organizerId);
    await audience.gotoAutomations();
    await expect(card(page, name)).toContainText(/1 email sent, most recently/);
    await expect(card(page, name)).not.toContainText('Nothing sent yet.');

    await audience.gotoCampaigns();
    await page.getByRole('radiogroup', { name: 'Filter campaigns' }).getByText(/^Automated/).click();
    await expect(page).toHaveURL(/folder=automated/);
    const run = audience.campaignRow(subject);
    await expect(run).toHaveCount(1);
    await expect(run).toContainText('Sent');
    await run.getByRole('link', { name: subject }).click();
    await expect(page.getByTestId('campaign-recipient-row')).toHaveCount(1);
    await expect(page.getByTestId('campaign-recipient-row')).toContainText(joiner);
    await expect(page.getByRole('button', { name: 'Delete' })).toHaveCount(0);
  });

  test('publishing an event announces it to subscribers once, and never an event that was already on sale', async ({ freshAccount, publicApi, mailpit, adminApi, messaging }) => {
    test.slow();
    const { api, organizerId } = freshAccount;
    const onSale = await createLiveEventWithFreeTicket(api, organizerId);
    const subscriber = uniqueEmail('announce-me');
    const silent = uniqueEmail('announce-not');
    await createOptedInOrder(publicApi, onSale, { email: subscriber, firstName: 'Ann', lastName: 'Ounce' });
    await createCompletedOrder(publicApi, onSale, { buyerEmail: silent, buyerFirstName: 'Si', buyerLastName: 'Lent' });
    await buildAudience(api, organizerId);
    await waitForContact(api, organizerId, silent);
    await adminApi.grantMessagingPrivileges((await api.getAccount()).id);
    const automation = await messaging.createAutomation('EVENT_PUBLISHED');
    await messaging.switchOnAutomation(automation.id);

    const fresh = await createDraftEventWithTicket(api, organizerId);
    await api.publishEvent(fresh.eventId);
    settlePublishedEvent(fresh.eventId);
    settlePublishedEvent(onSale.eventId);
    const subject = `Just announced: ${fresh.title}`;

    await expectOneAutomatedEmail({ messaging, adminApi, mailpit, to: subscriber, subject });
    await expectStillOneAfterAnotherRun({ messaging, adminApi, mailpit, to: subscriber, subject });
    expect(await emailsAbout(mailpit, silent, subject)).toBe(0);
    expect(await emailsAbout(mailpit, subscriber, `Just announced: ${onSale.title}`)).toBe(0);

    const page = await freshAccount.newAuthedPage();
    await new AudiencePage(page, organizerId).gotoAutomations();
    await expect(card(page, 'Announce new events')).toContainText(/1 email sent, most recently/);
  });

  test('a thank-you goes to ticket holders who opted in once the event date has ended', async ({ freshAccount, publicApi, mailpit, adminApi, messaging }) => {
    test.slow();
    const { api, organizerId } = freshAccount;
    const event = await createLiveEventWithFreeTicket(api, organizerId);
    const other = await createLiveEventWithFreeTicket(api, organizerId);
    const holder = uniqueEmail('thanked');
    const elsewhere = uniqueEmail('elsewhere');
    await createOptedInOrder(publicApi, event, { email: holder, firstName: 'Thea', lastName: 'Thanked' });
    await createOptedInOrder(publicApi, other, { email: elsewhere, firstName: 'Elsa', lastName: 'Elsewhere' });
    await buildAudience(api, organizerId);
    await waitForContact(api, organizerId, elsewhere);
    await adminApi.grantMessagingPrivileges((await api.getAccount()).id);
    const automation = await messaging.createAutomation('AFTER_EVENT', { delay_days: 0 });
    await messaging.switchOnAutomation(automation.id);
    backdateAutomationSwitchOn(organizerId, 5);
    endEventDatesAnHourAgo(event.eventId);
    const subject = `Thanks for coming to ${event.title}`;

    await expectOneAutomatedEmail({ messaging, adminApi, mailpit, to: holder, subject });
    await expectStillOneAfterAnotherRun({ messaging, adminApi, mailpit, to: holder, subject });
    expect(await emailsAbout(mailpit, elsewhere, subject)).toBe(0);

    const page = await freshAccount.newAuthedPage();
    await new AudiencePage(page, organizerId).gotoAutomations();
    await expect(card(page, 'Say thanks after an event')).toContainText('When each date ends · ticket holders');
    await expect(card(page, 'Say thanks after an event')).toContainText(/1 email sent, most recently/);
  });

  test('a subscriber who leaves a checkout unfinished is emailed about that event once', async ({ freshAccount, publicApi, mailpit, adminApi, messaging, playwright }) => {
    test.slow();
    const { api, organizerId } = freshAccount;
    const earlier = await createLiveEventWithFreeTicket(api, organizerId);
    const paid = await createLiveEventWithPaidTicket(api, organizerId);
    const subscriber = uniqueEmail('abandoner');
    const stranger = uniqueEmail('stranger');
    await createOptedInOrder(publicApi, earlier, { email: subscriber, firstName: 'Abby', lastName: 'Abandon' });
    await buildAudience(api, organizerId);
    await waitForContact(api, organizerId, subscriber);
    await adminApi.grantMessagingPrivileges((await api.getAccount()).id);
    const automation = await messaging.createAutomation('ABANDONED_CHECKOUT');
    await messaging.switchOnAutomation(automation.id);
    backdateAutomationSwitchOn(organizerId, 5);
    abandonCheckoutHoursAgo(await leaveCheckoutUnfinished(publicApi, paid, subscriber), 2);
    const anotherBrowser = await playwright.request.newContext({ baseURL: API_BASE_URL, ignoreHTTPSErrors: true, extraHTTPHeaders: { Accept: 'application/json' } });
    abandonCheckoutHoursAgo(await leaveCheckoutUnfinished(anotherBrowser, paid, stranger), 2);
    await anotherBrowser.dispose();
    const subject = `Still thinking about ${paid.title}?`;

    await expectOneAutomatedEmail({ messaging, adminApi, mailpit, to: subscriber, subject });
    await expectStillOneAfterAnotherRun({ messaging, adminApi, mailpit, to: subscriber, subject });
    expect(await emailsAbout(mailpit, stranger, subject)).toBe(0);

    const page = await freshAccount.newAuthedPage();
    await new AudiencePage(page, organizerId).gotoAutomations();
    await expect(card(page, 'Recover abandoned checkouts')).toContainText(/1 email sent, most recently/);
  });

  test('a first-time buyer who opted in gets the welcome email on the next daily run, and only once', async ({ freshAccount, publicApi, mailpit, adminApi, playwright }) => {
    test.slow();
    const { api } = freshAccount;
    const organizer = await api.createOrganizer(uniqueShort('Welcome Org'), { timezone: timezoneWhereItIsMidAfternoon() });
    const event = await createLiveEventWithFreeTicket(api, organizer.id);
    await buildAudience(api, organizer.id);
    await adminApi.grantMessagingPrivileges((await api.getAccount()).id);

    await withMessagingApi(playwright, { ...freshAccount, organizerId: organizer.id }, async (messaging) => {
      const subject = uniqueShort('Thanks for joining us');
      const automation = await messaging.createAutomation('WELCOME', { subject });
      await messaging.switchOnAutomation(automation.id);
      const newcomer = uniqueEmail('newcomer');
      const silent = uniqueEmail('quiet-newcomer');
      await createOptedInOrder(publicApi, event, { email: newcomer, firstName: 'Nia', lastName: 'Newcomer' });
      await createCompletedOrder(publicApi, event, { buyerEmail: silent, buyerFirstName: 'Quin', buyerLastName: 'Quiet' });
      await waitForContact(api, organizer.id, newcomer);
      await waitForContact(api, organizer.id, silent);
      moveLastDailyRunToYesterday(organizer.id);

      await expectOneAutomatedEmail({ messaging, adminApi, mailpit, to: newcomer, subject });
      await expectStillOneAfterAnotherRun({ messaging, adminApi, mailpit, to: newcomer, subject });
      expect(await emailsAbout(mailpit, silent, subject)).toBe(0);
    });

    const page = await freshAccount.newAuthedPage();
    await new AudiencePage(page, organizer.id).gotoAutomations();
    await expect(card(page, 'Welcome first-timers')).toContainText(/1 email sent, most recently/);
  });

  test('on a phone an automation is picked, checked in its confirmation and turned on', async ({ freshAccount, publicApi, adminApi }) => {
    const { api, organizerId } = freshAccount;
    const event = await createLiveEventWithFreeTicket(api, organizerId);
    await createOptedInOrder(publicApi, event, { email: uniqueEmail('pocket-fan'), firstName: 'Pat', lastName: 'Pocket' });
    await buildAudience(api, organizerId);
    await adminApi.grantMessagingPrivileges((await api.getAccount()).id);

    const page = await freshAccount.newAuthedPage();
    await page.setViewportSize(PHONE_VIEWPORT);
    await new AudiencePage(page, organizerId).gotoAutomations();
    await expect(page.getByText('Set up your first automation')).toBeVisible();
    await expectNoSidewaysScroll(page);
    await page.getByTestId('automation-template-EVENT_PUBLISHED').click();
    await expect(page.getByTestId('automation-name')).toHaveValue('Announce new events');
    await expectNoSidewaysScroll(page);
    await page.getByTestId('automation-save').click();
    await expect(card(page, 'Announce new events')).toContainText('When you publish an event · Everyone who opted in');
    await expectNoSidewaysScroll(page);

    await toggle(page, 'Announce new events').press('Space');
    const confirm = page.getByRole('dialog', { name: 'Turn on "Announce new events"?' });
    await expect(confirm.getByTestId('automation-confirm-count')).toHaveText('1 person who opted in would get the next announcement, as of now.');
    await expect(confirm.getByTitle('Email preview')).toBeVisible();
    await expectNoSidewaysScroll(page);
    await confirm.getByTestId('automation-confirm-turn-on').scrollIntoViewIfNeeded();
    await expect(confirm.getByTestId('automation-confirm-turn-on')).toBeInViewport();
    await confirm.getByTestId('automation-confirm-turn-on').click();
    await expect(page.getByText('Automation turned on')).toBeVisible();
    await expect(toggle(page, 'Announce new events')).toBeChecked();
    await expect(toggle(page, 'Announce new events')).toBeInViewport();
    await expectNoSidewaysScroll(page);
  });

  test('on Hi.Events Cloud an account that is not approved for campaigns sees why its automation did not send', async ({ freshAccount, publicApi, mailpit, messaging }) => {
    test.slow();
    saasOnly();
    const { api, organizerId } = freshAccount;
    const event = await createLiveEventWithFreeTicket(api, organizerId);
    const already = uniqueEmail('first-regular');
    const joiner = uniqueEmail('second-regular');
    await createOptedInOrder(publicApi, event, { email: already, firstName: 'Reg', lastName: 'Ular' });
    await buildAudience(api, organizerId);
    const subject = uniqueShort('You made the list');
    const name = uniqueShort('Greet regulars');
    const automation = await messaging.createAutomation('SEGMENT_ENTERED', { name, subject });
    await messaging.switchOnAutomation(automation.id);
    runAutomations();
    await createOptedInOrder(publicApi, event, { email: joiner, firstName: 'Jo', lastName: 'Joiner' });
    await waitForContact(api, organizerId, joiner);

    await expect(async () => {
      runAutomations();
      expect((await messaging.automationNamed(name)).last_failure_reason).not.toBeNull();
    }).toPass({ timeout: 45_000, intervals: [2_000, 4_000] });
    expect(await emailsAbout(mailpit, joiner, subject)).toBe(0);

    const page = await freshAccount.newAuthedPage();
    await new AudiencePage(page, organizerId).gotoAutomations();
    const failed = card(page, name);
    await expect(failed).toContainText("The last one couldn't be sent");
    await expect(failed).toContainText('To protect people from spam, campaigns are switched on once your account is verified.');
    await expect(failed.getByRole('link', { name: 'Connect Stripe' })).toHaveAttribute('href', `/manage/organizer/${organizerId}/settings/payouts`);
    await expect(failed).toContainText('Nothing sent yet.');
  });

  test('an automation that is waiting or could not send says so on its card until it is switched off', async ({ freshAccount, messaging }) => {
    const { api, organizerId } = freshAccount;
    await buildAudience(api, organizerId);
    const name = uniqueShort('Announce shows');
    const automation = await messaging.createAutomation('EVENT_PUBLISHED', { name });
    await messaging.switchOnAutomation(automation.id);

    const page = await freshAccount.newAuthedPage();
    await new AudiencePage(page, organizerId).gotoAutomations();
    const waiting = card(page, name);
    await expect(waiting).toContainText('Nothing sent yet.');
    await expect(waiting).not.toContainText('Waiting to send');

    await expect(async () => {
      recordAutomationFailure(organizerId, 'EVENT_AWAITING_REVIEW');
      await page.reload();
      await expect(waiting).toContainText('Waiting to send', { timeout: 5_000 });
      await expect(waiting).toContainText('An event is still being checked before it can be announced. The announcement goes out once the check passes.', { timeout: 1_000 });
      await expect(waiting).not.toContainText("The last one couldn't be sent", { timeout: 1_000 });
    }).toPass({ timeout: 45_000 });

    recordAutomationFailure(organizerId, 'LINKS_NOT_ALLOWED');
    await page.reload();
    await expect(waiting).toContainText("The last one couldn't be sent");
    await expect(waiting).toContainText("Your account can't send links yet, and this automation's email contains one. Edit the email to remove the links.");

    await toggle(page, name).press('Space');
    await expect(page.getByText('Automation turned off')).toBeVisible();
    await expect(waiting).not.toContainText("The last one couldn't be sent");
    await page.reload();
    await expect(toggle(page, name)).not.toBeChecked();
    await expect(waiting).not.toContainText("The last one couldn't be sent");
  });
});
