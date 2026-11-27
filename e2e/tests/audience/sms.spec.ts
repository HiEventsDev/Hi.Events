import type { APIRequest, APIRequestContext, Page } from '@playwright/test';
import { expect, type FreshAccount } from '../../fixtures';
import type { AdminApiClient } from '../../api/api-client';
import { createLiveEventWithFreeTicket } from '../../api/factory';
import { AudiencePage } from '../../pages/audience.page';
import { textRecipients, type TextRecipient } from '../../utils/audience-db';
import { saasOnly } from '../../utils/mode';
import { deliverWaitingText, FAKE_TWILIO_NUMBERS, sentTexts } from '../../utils/twilio';
import { uniqueEmail, uniqueShort } from '../../utils/unique';
import { askForMobileAtCheckout, buildAudience, createOptedInOrder, fakeTwilioAccount, UK_MOBILE, US_MOBILE, waitForContact, withAudienceApi } from './audience.shared';
import {
  approveIfHeldForReview,
  AU_MOBILE,
  IE_MOBILE,
  replyToTexts,
  reportTextDelivery,
  test,
  waitForCampaignStatus,
  type MessagingApi,
  type SmsCountry,
} from './messaging.shared';

const INVALID_SID_MESSAGE = 'A Twilio Account SID starts with AC and is 34 characters long.';
const SAMPLE_OPT_OUT_PATH = '/u/preview.000000000000';
const COUNTRY_NAMES: Record<SmsCountry, string> = { US: 'United States', GB: 'United Kingdom', IE: 'Ireland', AU: 'Australia' };

interface Texter {
  country: SmsCountry;
  email: string;
  firstName: string;
  lastName: string;
  phone: string;
}

const TEXTERS: Record<'US' | 'GB' | 'AU', Omit<Texter, 'email'>> = {
  US: { country: 'US', firstName: 'Uma', lastName: 'Stateside', phone: US_MOBILE },
  GB: { country: 'GB', firstName: 'Gia', lastName: 'Britain', phone: UK_MOBILE },
  AU: { country: 'AU', firstName: 'Aki', lastName: 'Sydney', phone: AU_MOBILE },
};

const switchOnTextMessages = async (
  freshAccount: FreshAccount,
  adminApi: AdminApiClient,
  playwright: { request: APIRequest },
): Promise<void> => {
  const accountId = (await freshAccount.api.getAccount()).id;
  await adminApi.setAccountFeatureFlag(accountId, 'crm_sms', true);
  await adminApi.grantMessagingPrivileges(accountId);
  const isFaked = await withAudienceApi(playwright, freshAccount, (audienceApi) => audienceApi.smsProviderIsFaked());
  test.skip(!isFaked, 'This backend talks to real Twilio (SMS_DRIVER is not fake), so text messages cannot be exercised.');
};

const arrangeTexters = async (
  { freshAccount, publicApi, messaging, countries }: { freshAccount: FreshAccount; publicApi: APIRequestContext; messaging: MessagingApi; countries: ('US' | 'GB' | 'AU')[] },
): Promise<Texter[]> => {
  const { api, organizerId } = freshAccount;
  const event = await createLiveEventWithFreeTicket(api, organizerId);
  await askForMobileAtCheckout(api, event.eventId);
  await messaging.connectSms(countries);
  const texters = countries.map((country) => ({ ...TEXTERS[country], email: uniqueEmail(`texter-${country.toLowerCase()}`) }));
  for (const texter of texters) {
    await createOptedInOrder(publicApi, event, { ...texter, agreesToTexts: true });
  }
  await buildAudience(api, organizerId);
  for (const texter of texters) {
    await waitForContact(api, organizerId, texter.email);
  }
  return texters;
};

const addTexter = async (
  { freshAccount, publicApi, texter }: { freshAccount: FreshAccount; publicApi: APIRequestContext; texter: Omit<Texter, 'email'> },
): Promise<Texter> => {
  const { api, organizerId } = freshAccount;
  const event = await createLiveEventWithFreeTicket(api, organizerId);
  await askForMobileAtCheckout(api, event.eventId);
  const added = { ...texter, email: uniqueEmail(`texter-${texter.lastName.toLowerCase()}`) };
  await createOptedInOrder(publicApi, event, { ...added, agreesToTexts: true });
  await waitForContact(api, organizerId, added.email);
  return added;
};

const sendTextThroughTheApi = async (
  { freshAccount, adminApi, messaging, body, endsAs = 'SENT' }: { freshAccount: FreshAccount; adminApi: AdminApiClient; messaging: MessagingApi; body: string; endsAs?: 'SENT' | 'PAUSED' },
): Promise<{ id: number; name: string }> => {
  const { api, organizerId } = freshAccount;
  const name = uniqueShort('Doors text');
  const draft = await messaging.createCampaign({ name, body, channel: 'SMS' });
  await messaging.sendCampaign(draft.id);
  await approveIfHeldForReview(adminApi, api, organizerId, draft.id);
  await waitForCampaignStatus(api, organizerId, draft.id, new RegExp(`^(SENDING|${endsAs})$`));
  await expect.poll(async () => (await api.getAudienceCampaign(organizerId, draft.id)).sent_at, { timeout: 45_000 }).not.toBeNull();
  return { id: draft.id, name };
};

const handEveryTextToTwilio = async (freshAccount: FreshAccount, campaignId: number, endsAs: 'SENT' | 'PAUSED'): Promise<void> => {
  for (const recipient of textRecipients(campaignId)) {
    deliverWaitingText(recipient.id, recipient.country);
  }
  await waitForCampaignStatus(freshAccount.api, freshAccount.organizerId, campaignId, new RegExp(`^${endsAs}$`));
};

const deliverEveryText = async (campaignId: number): Promise<TextRecipient[]> => {
  for (const waiting of textRecipients(campaignId).filter((recipient) => recipient.messageId === null)) {
    deliverWaitingText(waiting.id, waiting.country);
  }
  await expect.poll(() => textRecipients(campaignId).every((recipient) => recipient.messageId !== null), { timeout: 45_000 }).toBe(true);
  return textRecipients(campaignId);
};

const optOutLinkIn = (body: string): URL => {
  const link = /\nOpt out: (\S+)$/.exec(body);
  expect(link, `No opt-out link at the end of: ${body}`).not.toBeNull();
  return new URL(link![1]);
};

const openTextCampaignPicker = async (page: Page, audience: AudiencePage): Promise<void> => {
  await audience.gotoCampaigns();
  await audience.campaignCreateButton().click();
  await page.getByTestId('campaign-picker-channel').getByText('Text message', { exact: true }).click();
};

const openContact = async (audience: AudiencePage, texter: Texter): Promise<void> => {
  await audience.gotoContactsMatching(texter.email);
  await audience.contactRow(`${texter.firstName} ${texter.lastName}`).click();
};

test.describe('audience text messages', () => {
  test('Twilio is connected from the organizer settings, tested with a real number, given another country and disconnected', async ({ freshAccount, adminApi, playwright }) => {
    await switchOnTextMessages(freshAccount, adminApi, playwright);
    const { api, organizerId } = freshAccount;
    await buildAudience(api, organizerId);

    const page = await freshAccount.newAuthedPage();
    const audience = new AudiencePage(page, organizerId);
    await openTextCampaignPicker(page, audience);
    await expect(page.getByTestId('campaign-picker-start-sms')).toBeDisabled();
    await expect(page.getByText('Connect your Twilio account before sending text messages.')).toBeVisible();
    await page.getByRole('link', { name: 'Set up text messages' }).click();
    await expect(page).toHaveURL(/\/settings\/text-messages$/);

    const accountSid = page.getByLabel(/^Twilio Account SID/);
    const findServices = page.getByRole('button', { name: 'Find Messaging Services' });
    await accountSid.fill('AC-not-a-real-sid');
    await page.getByLabel(/^Auth Token/).fill('e2e-token');
    await findServices.click();
    await expect(page.getByText(INVALID_SID_MESSAGE)).toBeVisible();

    await accountSid.fill(fakeTwilioAccount());
    await findServices.click();
    await expect(page.getByRole('combobox', { name: /^Messaging Service/ })).toHaveValue(/Test sender/);

    const connect = page.getByRole('button', { name: 'Connect Twilio' });
    await connect.click();
    await expect(page.getByText('Choose at least one country to send to.')).toBeVisible();
    await expect(page.getByText('Sender names work without registration. Texts end with an opt-out link.')).toBeVisible();
    await page.getByRole('checkbox', { name: /^United Kingdom/ }).check();
    await connect.click();
    await expect(page.getByText('Text messages are set up')).toBeVisible();
    await expect(connect).toHaveCount(0);
    const summary = page.locator('#text-messages');
    await expect(summary).toContainText(/Messaging ServiceMG[0-9a-f]{32}/);
    await expect(summary).toContainText('United Kingdom');
    await expect(summary).not.toContainText('Ireland');

    const testNumber = page.getByRole('textbox', { name: 'Send a test text' });
    const sendTest = page.getByRole('button', { name: 'Send test' });
    await testNumber.fill(IE_MOBILE);
    await sendTest.click();
    await expect(page.getByText("Text messages aren't switched on for this number's country.")).toBeVisible();

    await summary.getByRole('button', { name: 'Edit', exact: true }).click();
    await expect(page.getByText('Leave blank to keep the saved token.')).toBeVisible();
    await expect(page.getByLabel(/^Auth Token/)).toHaveValue('');
    await page.getByRole('checkbox', { name: /^Ireland/ }).check();
    await page.getByRole('button', { name: 'Save changes' }).click();
    await expect(page.getByText('Text messages are set up')).toBeVisible();
    await expect(summary).toContainText('Ireland');
    await testNumber.fill(IE_MOBILE);
    await sendTest.click();
    await expect(page.getByText('Test text sent')).toBeVisible();
    await expect(page.getByText("Text messages aren't switched on for this number's country.")).toHaveCount(0);

    await openTextCampaignPicker(page, audience);
    await expect(page.getByTestId('campaign-picker-start-sms')).toBeEnabled();
    await page.getByRole('button', { name: 'Cancel' }).click();

    await page.goto(`/manage/organizer/${organizerId}/settings/text-messages`);
    await page.getByRole('button', { name: 'Disconnect' }).click();
    const disconnect = page.getByRole('dialog', { name: 'Disconnect Twilio?' });
    await expect(disconnect).toContainText("Text message campaigns can't be sent until you connect again. Your contacts and their text message consent are kept.");
    await expect(disconnect).toContainText("Replies sent while disconnected, including STOP, aren't recorded here");
    await audience.confirm('Disconnect');
    await expect(page.getByText('Twilio disconnected')).toBeVisible();
    await expect(connect).toBeVisible();

    await openTextCampaignPicker(page, audience);
    await expect(page.getByTestId('campaign-picker-start-sms')).toBeDisabled();
  });

  test('a text is written with a placeholder, previewed as it will arrive, tested on a phone and sent only to people who agreed to texts', { tag: '@smoke' }, async ({ freshAccount, publicApi, adminApi, playwright, messaging }) => {
    test.slow();
    await switchOnTextMessages(freshAccount, adminApi, playwright);
    const { api, organizerId } = freshAccount;
    const [texter] = await arrangeTexters({ freshAccount, publicApi, messaging, countries: ['GB'] });
    const event = await createLiveEventWithFreeTicket(api, organizerId);
    await askForMobileAtCheckout(api, event.eventId);
    const emailOnly = uniqueEmail('email-only');
    await createOptedInOrder(publicApi, event, { email: emailOnly, firstName: 'Emil', lastName: 'Emailer', phone: '+447400123457' });
    await waitForContact(api, organizerId, emailOnly);
    const organizer = await api.getOrganizer(organizerId);

    const page = await freshAccount.newAuthedPage();
    const audience = new AudiencePage(page, organizerId);
    await openTextCampaignPicker(page, audience);
    await page.getByTestId('campaign-picker-start-sms').click();
    await expect(page).toHaveURL(/\/audience\/campaigns\/new\?channel=SMS/);
    await expect(page.getByTestId('campaign-subject')).toHaveCount(0);
    await expect(page.getByTestId('campaign-recipient-count')).toHaveText('1 of 2 opted in to texts');
    await expect(page.getByTestId('campaign-from')).toContainText('Every text starts with your organizer name.');

    const message = page.getByTestId('campaign-sms-body');
    const counter = page.getByTestId('campaign-sms-count');
    const bubble = page.getByTestId('campaign-sms-preview');
    await message.fill('Doors open at 7 tonight. '.repeat(12));
    await expect(counter).toHaveText(/^\d+ characters · [3-9] parts$/);
    await message.fill('Doors open at 7 tonight, ');
    await page.getByRole('button', { name: 'Insert', exact: true }).click();
    await expect(page.getByRole('menuitem', { name: /event_url/ })).toHaveCount(0);
    await page.getByRole('menuitem', { name: /first_name/ }).click();
    await expect(message).toHaveValue(/^Doors open at 7 tonight, \{\{ first_name/);
    await expect(bubble).toHaveText(new RegExp(`^${organizer.name}: Doors open at 7 tonight, E2E\\s+Opt out: \\S+/u/preview\\.000000000000$`));
    await expect(page.getByText('Shown with your own details. Each person gets their own name and links, and the opt-out line in their language.')).toBeVisible();
    await expect(page.getByText('Placeholders are counted with your own details, so a longer name or link can add a part for some people.')).toBeVisible();
    await expect(counter).toHaveText(/^\d+ characters · [12] parts?$/);

    await page.getByTestId('campaign-review-send').click();
    await expect(page.getByRole('alert').filter({ hasText: 'Give this text message a name so you can find it later' })).toBeVisible();
    await expect(page.getByTestId('campaign-name')).toHaveAttribute('aria-invalid', 'true');
    await expect(page.getByTestId('campaign-name')).toHaveAttribute('aria-describedby', 'campaign-name-error');
    await expect(page.getByRole('dialog')).toHaveCount(0);
    const label = uniqueShort('Doors text');
    await page.getByTestId('campaign-name').fill(label);

    await page.getByRole('button', { name: 'Send me a test text' }).click();
    const testModal = page.getByRole('dialog', { name: 'Send a test text' });
    await expect(testModal).toContainText("Its opt-out link doesn't work; real texts get a personal one.");
    await testModal.getByRole('textbox', { name: 'Mobile number' }).fill(UK_MOBILE);
    await testModal.getByRole('button', { name: 'Send test' }).click();
    await expect(page.getByText(/^Test sent to \+44/)).toBeVisible();
    await expect(testModal).toBeHidden();

    await page.getByTestId('campaign-review-send').click();
    const send = page.getByRole('dialog', { name: `Send "${label}"` });
    await expect(send.getByTestId('campaign-send-summary'))
      .toHaveText('This text goes to 1 person who opted in to texts, in the countries you send to. Everyone else you selected is skipped.');
    const facts = send.getByTestId('campaign-send-sms-facts');
    await expect(facts).toContainText(/About [12] message parts?, billed by Twilio/);
    await expect(facts).toContainText('Texts only go out between 9am and 8pm');
    await expect(facts).toContainText(/United Kingdom: 1 text (goes out now|waits and goes out from .+)/);
    await send.getByTestId('campaign-send-confirm').click();
    await expect(send).toBeHidden();
    await expect(audience.campaignStatus()).not.toContainText('Draft');
    const campaignId = audience.campaignIdFromUrl();
    await approveIfHeldForReview(adminApi, api, organizerId, campaignId);
    await waitForCampaignStatus(api, organizerId, campaignId, /^(SENDING|SENT)$/);

    await page.reload();
    await expect(page.getByTestId('campaign-recipient-count')).toContainText('Everyone who opted in to texts');
    await expect(page.getByTestId('campaign-recipient-count')).toContainText('1 recipient');
    const recipient = page.getByTestId('campaign-recipient-row');
    await expect(recipient).toHaveCount(1);
    await expect(recipient).toContainText(`${texter.firstName} ${texter.lastName}`);
    await expect(recipient).toContainText(/\+44•+456/);
    await expect(recipient).not.toContainText(texter.email);
    await expect(recipient).toContainText(/Sent|Queued/);
    await expect(page.getByTestId('campaign-sms-body')).toHaveCount(0);
    await expect(page.getByTestId('campaign-sms-preview')).toContainText('Doors open at 7 tonight,');

    const [delivered] = await deliverEveryText(campaignId);
    const texts = sentTexts(organizerId, UK_MOBILE);
    expect(texts).toHaveLength(2);
    const [testText, campaignText] = texts;
    expect(testText.body.startsWith(`${organizer.name}: Doors open at 7 tonight, `)).toBe(true);
    expect(optOutLinkIn(testText.body).pathname).toBe(SAMPLE_OPT_OUT_PATH);
    const personalLink = optOutLinkIn(campaignText.body);
    expect(campaignText.messageId).toBe(delivered.messageId);
    expect(campaignText.body).toBe(`${organizer.name}: Doors open at 7 tonight, ${texter.firstName}\nOpt out: ${personalLink.href}`);
    expect(personalLink.pathname).toMatch(/^\/u\/[^/]+$/);
    expect(personalLink.pathname).not.toBe(SAMPLE_OPT_OUT_PATH);
    expect(sentTexts(organizerId, '+447400123457')).toEqual([]);
  });

  test('texts wait outside sending hours country by country, and stopping the campaign skips the ones still waiting', async ({ freshAccount, publicApi, adminApi, playwright, messaging }) => {
    test.slow();
    await switchOnTextMessages(freshAccount, adminApi, playwright);
    const { api, organizerId } = freshAccount;
    const texters = await arrangeTexters({ freshAccount, publicApi, messaging, countries: ['US', 'GB', 'AU'] });
    const body = 'Tickets for the summer run are out now.';
    const campaign = await sendTextThroughTheApi({ freshAccount, adminApi, messaging, body });
    const estimate = await messaging.smsEstimate({ id: campaign.id, body });
    const waiting = texters.filter((texter) => !estimate.find((country) => country.country === texter.country)?.is_sending_now);
    const goingOut = texters.filter((texter) => !waiting.includes(texter));
    expect(waiting.length).toBeGreaterThan(0);
    await expect.poll(() => textRecipients(campaign.id).filter((recipient) => recipient.messageId !== null).length, { timeout: 45_000 }).toBe(goingOut.length);

    const page = await freshAccount.newAuthedPage();
    const audience = new AudiencePage(page, organizerId);
    await page.goto(`/manage/organizer/${organizerId}/audience/campaigns/${campaign.id}`);
    await expect(audience.campaignStatus()).toContainText('Sending');
    const notice = page.getByTestId('campaign-sms-waiting');
    await expect(notice).toContainText("Some texts are waiting for sending hours. In countries with several time zones, texts wait until it is between 9am and 8pm in all of them.");
    for (const texter of waiting) {
      await expect(notice).toContainText(new RegExp(`${COUNTRY_NAMES[texter.country]}: 1 text waiting\\. Sending starts again .+`));
      await expect(page.getByTestId('campaign-recipient-row').filter({ hasText: texter.lastName })).toContainText('Queued');
    }
    for (const texter of goingOut) {
      await expect(notice).not.toContainText(COUNTRY_NAMES[texter.country]);
      await expect(page.getByTestId('campaign-recipient-row').filter({ hasText: texter.lastName })).toContainText('Sent');
    }
    await expect(page.getByText(`${waiting.length} still to go`)).toBeVisible();

    await page.getByRole('button', { name: 'Stop sending' }).click();
    const confirm = page.getByRole('dialog', { name: 'Stop sending this campaign?' });
    await expect(confirm).toContainText(`${goingOut.length} of 3 texts have already gone out. The rest won't be sent, and this can't be undone.`);
    await audience.confirm('Stop sending');
    await expect(page.getByText('Campaign stopped')).toBeVisible();
    await expect(audience.campaignStatus()).toContainText('Stopped');
    await expect(page.getByText("This campaign was stopped before it finished. Anyone who hadn't received it yet was skipped.")).toBeVisible();
    await expect(notice).toHaveCount(0);
    await expect(page.getByRole('button', { name: 'Stop sending' })).toHaveCount(0);
    for (const texter of waiting) {
      await expect(page.getByTestId('campaign-recipient-row').filter({ hasText: texter.lastName })).toContainText('Skipped');
    }
    await page.getByRole('radiogroup', { name: 'Filter recipients' }).getByText(`Not sent · ${waiting.length}`).click();
    await expect(page.getByTestId('campaign-recipient-row')).toHaveCount(waiting.length);
    expect((await api.getAudienceCampaign(organizerId, campaign.id)).status).toBe('CANCELLED');
  });

  test('a delivery report from Twilio marks the recipient as delivered, one that is not signed by Twilio is ignored, and every text arrives with the organizer name and the opt-out line of its country', async ({ freshAccount, publicApi, adminApi, playwright, messaging }) => {
    test.slow();
    await switchOnTextMessages(freshAccount, adminApi, playwright);
    const { api, organizerId } = freshAccount;
    const texters = await arrangeTexters({ freshAccount, publicApi, messaging, countries: ['US', 'GB', 'AU'] });
    const body = 'Your table is ready.';
    const campaign = await sendTextThroughTheApi({ freshAccount, adminApi, messaging, body });
    const [delivered] = await deliverEveryText(campaign.id);
    await waitForCampaignStatus(api, organizerId, campaign.id, /^SENT$/);
    const texter = texters.find((candidate) => candidate.country === delivered.country)!;

    const page = await freshAccount.newAuthedPage();
    const audience = new AudiencePage(page, organizerId);
    await page.goto(`/manage/organizer/${organizerId}/audience/campaigns/${campaign.id}`);
    const row = page.getByTestId('campaign-recipient-row').filter({ hasText: texter.lastName });
    await expect(audience.campaignStatus()).toContainText('Sent');
    await expect(row).toContainText('Sent');
    await expect(page.getByText('No reports yet')).toBeVisible();

    const forged = await reportTextDelivery(publicApi, organizerId, { messageId: delivered.messageId!, status: 'delivered', authToken: 'not-the-token' });
    expect(forged.status()).toBe(401);
    await page.reload();
    await expect(row).toContainText('Sent');
    await expect(row).not.toContainText('Delivered');

    expect((await reportTextDelivery(publicApi, organizerId, { messageId: delivered.messageId!, status: 'delivered' })).status()).toBe(200);
    await page.reload();
    await expect(row).toContainText('Delivered');
    await expect(page.getByText('No reports yet')).toHaveCount(0);
    await expect(page.getByText('0 not delivered')).toBeVisible();

    const organizer = await api.getOrganizer(organizerId);

    expect(sentTexts(organizerId, US_MOBILE).map((text) => text.body)).toEqual([`${organizer.name}: ${body}\nReply STOP to opt out.`]);
    for (const phone of [UK_MOBILE, AU_MOBILE]) {
      const texts = sentTexts(organizerId, phone);
      expect(texts).toHaveLength(1);
      const personalLink = optOutLinkIn(texts[0].body);
      expect(texts[0].body).toBe(`${organizer.name}: ${body}\nOpt out: ${personalLink.href}`);
      expect(personalLink.pathname).not.toBe(SAMPLE_OPT_OUT_PATH);
    }
  });

  test('replying STOP stops texts to that number and START brings them back', async ({ freshAccount, publicApi, adminApi, playwright, messaging }) => {
    await switchOnTextMessages(freshAccount, adminApi, playwright);
    const { organizerId } = freshAccount;
    const [texter] = await arrangeTexters({ freshAccount, publicApi, messaging, countries: ['US'] });

    const page = await freshAccount.newAuthedPage();
    const audience = new AudiencePage(page, organizerId);
    const texts = audience.drawerSection('Text message status');
    const history = audience.drawerSection('Consent history');
    const textDraftCount = async (): Promise<string> => {
      await page.goto(`/manage/organizer/${organizerId}/audience/campaigns/new?channel=SMS`);
      const count = page.getByTestId('campaign-recipient-count');
      await expect(count).toContainText('opted in to texts');
      return count.innerText();
    };
    await openContact(audience, texter);
    await expect(texts).toContainText('Opted in to marketing text messages on this number.');
    await expect(history).toContainText('Ticked the text message box at checkout');

    expect((await replyToTexts(publicApi, organizerId, { from: texter.phone, body: 'STOP', authToken: 'not-the-token' })).status()).toBe(401);
    await page.reload();
    await expect(texts).toContainText('Opted in to marketing text messages on this number.');

    expect((await replyToTexts(publicApi, organizerId, { from: texter.phone, body: 'STOP' })).status()).toBe(200);
    await page.reload();
    await expect(texts).toContainText('Asked not to receive marketing text messages on this number.');
    await expect(history).toContainText('Replied STOP to a text message');
    await expect(audience.marketingStatus()).toContainText('Subscribed');
    await expect.poll(textDraftCount).toBe('0 of 1 opted in to texts');

    expect((await replyToTexts(publicApi, organizerId, { from: texter.phone, body: 'START' })).status()).toBe(200);
    await openContact(audience, texter);
    await expect(texts).toContainText('Opted in to marketing text messages on this number.');
    await expect(history).toContainText('Replied START to a text message');
    await expect.poll(textDraftCount).toBe('1 of 1 opted in to texts');
  });

  test('a number two contacts share is texted once, the opt-out link in that text stops texts to the number, and the sample link from a test text is not valid', async ({ freshAccount, publicApi, adminApi, playwright, messaging, browser }) => {
    test.slow();
    await switchOnTextMessages(freshAccount, adminApi, playwright);
    const { api, organizerId } = freshAccount;
    const [texter] = await arrangeTexters({ freshAccount, publicApi, messaging, countries: ['GB'] });
    await addTexter({ freshAccount, publicApi, texter: { country: 'GB', firstName: 'Hal', lastName: 'Housemate', phone: texter.phone } });
    const campaign = await sendTextThroughTheApi({ freshAccount, adminApi, messaging, body: 'Last few tickets for Friday.' });
    expect(await deliverEveryText(campaign.id)).toHaveLength(1);
    const texts = sentTexts(organizerId, texter.phone);
    expect(texts).toHaveLength(1);
    const optOutLink = optOutLinkIn(texts[0].body);
    const organizer = await api.getOrganizer(organizerId);

    const visitor = await browser.newPage();
    try {
      await visitor.goto(SAMPLE_OPT_OUT_PATH);
      await expect(visitor.getByRole('heading', { name: "This link isn't valid" })).toBeVisible();
      await expect(visitor.getByText('The link may be incomplete. Try opening it again from the text message.')).toBeVisible();
      await expect(visitor.getByTestId('audience-unsubscribe-button')).toHaveCount(0);

      await visitor.goto(optOutLink.pathname);
      await expect(visitor.getByRole('heading', { name: `Stop texts from ${organizer.name}` })).toBeVisible();
      await expect(visitor.getByText(/Stop marketing text messages from .+ to \+44•+456\./)).toBeVisible();
      await expect(visitor.getByTestId('audience-unsubscribe-button')).toHaveText('Stop texts');
      await visitor.getByTestId('audience-unsubscribe-button').click();
      await expect(visitor.getByRole('heading', { name: "You've opted out of texts" })).toBeVisible();
      await expect(visitor.getByTestId('audience-resubscribe-button')).toHaveCount(0);
      await visitor.reload();
      await expect(visitor.getByRole('heading', { name: "You've opted out of texts" })).toBeVisible();
    } finally {
      await visitor.close();
    }

    const page = await freshAccount.newAuthedPage();
    const audience = new AudiencePage(page, organizerId);
    await openContact(audience, texter);
    await expect(audience.drawerSection('Text message status')).toContainText('Asked not to receive marketing text messages on this number.');
    await expect(audience.drawerSection('Consent history')).toContainText('Opt-out link in a text message');
    await expect(audience.marketingStatus()).toContainText('Subscribed');
  });

  test('when Twilio stops accepting the account mid-send the campaign is paused, the texts not yet sent are skipped and the sender is emailed', async ({ freshAccount, publicApi, adminApi, playwright, messaging, mailpit }) => {
    test.slow();
    await switchOnTextMessages(freshAccount, adminApi, playwright);
    const { organizerId } = freshAccount;
    const [texter] = await arrangeTexters({ freshAccount, publicApi, messaging, countries: ['US'] });
    const refused = await addTexter({ freshAccount, publicApi, texter: { country: 'US', firstName: 'Rex', lastName: 'Refused', phone: FAKE_TWILIO_NUMBERS.getsTheAccountRefused } });
    const campaign = await sendTextThroughTheApi({ freshAccount, adminApi, messaging, body: 'Gates open at noon on Saturday.', endsAs: 'PAUSED' });
    await handEveryTextToTwilio(freshAccount, campaign.id, 'PAUSED');
    const sentBeforeTheRefusal = textRecipients(campaign.id).filter((recipient) => recipient.messageId !== null).length;

    expect(sentTexts(organizerId, refused.phone)).toEqual([]);
    expect(sentTexts(organizerId, texter.phone)).toHaveLength(sentBeforeTheRefusal);

    const page = await freshAccount.newAuthedPage();
    const audience = new AudiencePage(page, organizerId);
    await page.goto(`/manage/organizer/${organizerId}/audience/campaigns/${campaign.id}`);
    await expect(audience.campaignStatus()).toContainText('Paused');
    await expect(page.getByText("We paused this campaign because Twilio stopped accepting your saved account details or Messaging Service. It won't start again, and nobody else will get it. Check your Twilio connection before sending another.")).toBeVisible();
    await expect(page.getByTestId('campaign-sms-waiting')).toHaveCount(0);
    await expect(page.getByRole('button', { name: 'Stop sending' })).toHaveCount(0);
    const rows = page.getByTestId('campaign-recipient-row');
    await expect(rows).toHaveCount(2);
    await expect(rows.filter({ hasText: refused.lastName })).toContainText('Skipped');
    await expect(rows.filter({ hasText: refused.lastName })).toContainText('The campaign was stopped first');
    await expect(rows.filter({ hasText: 'Sent' })).toHaveCount(sentBeforeTheRefusal);
    await page.getByRole('radiogroup', { name: 'Filter recipients' }).getByText(`Not sent · ${2 - sentBeforeTheRefusal}`).click();
    await expect(rows).toHaveCount(2 - sentBeforeTheRefusal);
    await expect(rows.filter({ hasText: 'The campaign was stopped first' })).toHaveCount(2 - sentBeforeTheRefusal);

    const notification = await mailpit.waitForMessage(freshAccount.email, { subjectContains: 'We stopped sending your campaign', timeoutMs: 45_000 });
    const email = (await mailpit.getMessage(notification.ID)).Text;
    expect(email).toContain(`We stopped sending "${campaign.name}", so the people who hadn't received it yet won't get it.`);
    expect(email).toContain('Twilio no longer accepts the account details or Messaging Service saved for this organizer. Check your Twilio connection before sending again.');
  });

  test('a number Twilio lists as opted out is opted out here for everyone who shares it, and a number Twilio rejects only fails that text', async ({ freshAccount, publicApi, adminApi, playwright, messaging }) => {
    test.slow();
    await switchOnTextMessages(freshAccount, adminApi, playwright);
    const { organizerId } = freshAccount;
    const [texter] = await arrangeTexters({ freshAccount, publicApi, messaging, countries: ['US'] });
    const optedOut = await addTexter({ freshAccount, publicApi, texter: { country: 'US', firstName: 'Opal', lastName: 'Optedout', phone: FAKE_TWILIO_NUMBERS.optedOutAtTwilio } });
    const housemate = await addTexter({ freshAccount, publicApi, texter: { country: 'US', firstName: 'Hal', lastName: 'Housemate', phone: FAKE_TWILIO_NUMBERS.optedOutAtTwilio } });
    const rejected = await addTexter({ freshAccount, publicApi, texter: { country: 'US', firstName: 'Lana', lastName: 'Landline', phone: FAKE_TWILIO_NUMBERS.rejected } });
    const campaign = await sendTextThroughTheApi({ freshAccount, adminApi, messaging, body: 'Gates open at noon on Saturday.' });
    await handEveryTextToTwilio(freshAccount, campaign.id, 'SENT');

    expect(sentTexts(organizerId, texter.phone)).toHaveLength(1);
    expect(sentTexts(organizerId, optedOut.phone)).toEqual([]);
    expect(sentTexts(organizerId, rejected.phone)).toEqual([]);

    const page = await freshAccount.newAuthedPage();
    const audience = new AudiencePage(page, organizerId);
    await page.goto(`/manage/organizer/${organizerId}/audience/campaigns/${campaign.id}`);
    await expect(audience.campaignStatus()).toContainText('Sent');
    const rows = page.getByTestId('campaign-recipient-row');
    await expect(rows).toHaveCount(3);
    await expect(rows.filter({ hasText: texter.lastName })).toContainText('Sent');
    await expect(rows.filter({ hasText: 'Twilio lists this number as opted out' })).toHaveCount(1);
    await expect(rows.filter({ hasText: 'Twilio lists this number as opted out' })).toContainText(/\+12•+192/);
    await expect(rows.filter({ hasText: rejected.lastName })).toContainText("Twilio refused this number. It may not be a mobile, or your sender can't reach it.");

    const texts = audience.drawerSection('Text message status');
    for (const sharer of [optedOut, housemate]) {
      await openContact(audience, sharer);
      await expect(texts).toContainText('Asked not to receive marketing text messages on this number.');
      await expect(audience.drawerSection('Consent history')).toContainText('Twilio reported that this number had opted out');
    }
    await openContact(audience, rejected);
    await expect(texts).toContainText('Opted in to marketing text messages on this number.');
  });

  test('on Hi.Events Cloud a first text waits for review, and the reviewer reads the text as it will arrive', async ({ freshAccount, publicApi, adminApi, playwright, messaging, superAdminPage }) => {
    saasOnly();
    test.slow();
    await switchOnTextMessages(freshAccount, adminApi, playwright);
    const { api, organizerId } = freshAccount;
    await arrangeTexters({ freshAccount, publicApi, messaging, countries: ['US'] });
    const organizer = await api.getOrganizer(organizerId);
    const name = uniqueShort('Review this text');
    const draft = await messaging.createCampaign({ name, body: 'Gates open at noon on Saturday.', channel: 'SMS' });
    await messaging.sendCampaign(draft.id);

    const page = await freshAccount.newAuthedPage();
    const audience = new AudiencePage(page, organizerId);
    await page.goto(`/manage/organizer/${organizerId}/audience/campaigns/${draft.id}`);
    await expect(audience.campaignStatus()).toContainText('Waiting for review');
    await expect(page.getByText("We check every account's first campaign before it goes out.")).toBeVisible();
    expect(textRecipients(draft.id).every((recipient) => recipient.messageId === null)).toBe(true);

    await superAdminPage.goto('/admin/audience-campaigns');
    const row = superAdminPage.getByRole('row').filter({ hasText: name });
    await expect(row).toContainText('Text message');
    await row.getByRole('button', { name: 'Review' }).click();
    const review = superAdminPage.getByRole('dialog').filter({ hasText: name });
    await expect(review).toContainText('Goes to: Everyone who opted in to texts');
    await expect(review).toContainText('Recipients: 1');
    await expect(review).toContainText(`${organizer.name}: Gates open at noon on Saturday.`);
    await expect(review).toContainText('Reply STOP to opt out.');
    await expect(review).not.toContainText('Subject:');
    await expect(review.getByTitle('Email preview')).toHaveCount(0);
    await review.getByRole('button', { name: 'Approve and send' }).click();
    await expect(review).toBeHidden();
    await expect(row).toHaveCount(0);

    await waitForCampaignStatus(api, organizerId, draft.id, /^(SENDING|SENT)$/);
    await page.reload();
    await expect(audience.campaignStatus()).toContainText(/Sending|Sent/);
    await expect(page.getByTestId('campaign-recipient-row')).toHaveCount(1);
  });
});
