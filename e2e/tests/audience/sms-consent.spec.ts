import type { APIRequest } from '@playwright/test';
import { expect, type FreshAccount } from '../../fixtures';
import type { AdminApiClient } from '../../api/api-client';
import { createLiveEventWithFreeTicket } from '../../api/factory';
import { AudiencePage } from '../../pages/audience.page';
import { evaluatePhp } from '../../utils/artisan';
import { textRecipients } from '../../utils/audience-db';
import { uniqueEmail, uniqueShort } from '../../utils/unique';
import { askForMobileAtCheckout, buildAudience, createOptedInOrder, UK_MOBILE, waitForContact, withAudienceApi } from './audience.shared';
import { readyMadeSegment } from './contacts.helpers';
import { approveIfHeldForReview, test, waitForCampaignStatus } from './messaging.shared';

const OPTED_OUT = 'Asked not to receive marketing text messages on this number.';
const OPTED_IN = 'Opted in to marketing text messages on this number.';

const switchOnTextMessages = async (freshAccount: FreshAccount, adminApi: AdminApiClient, playwright: { request: APIRequest }): Promise<void> => {
  const accountId = (await freshAccount.api.getAccount()).id;
  await adminApi.setAccountFeatureFlag(accountId, 'crm_sms', true);
  await adminApi.grantMessagingPrivileges(accountId);
  const isFaked = await withAudienceApi(playwright, freshAccount, (audienceApi) => audienceApi.smsProviderIsFaked());
  test.skip(!isFaked, 'This backend talks to real Twilio (SMS_DRIVER is not fake), so text messages cannot be exercised.');
};

test.describe('audience text message consent', () => {
  test('two contacts who share a mobile number get one text between them, and when one opts out with the link neither is texted again', async ({ freshAccount, publicApi, adminApi, playwright, messaging, browser }) => {
    test.slow();
    await switchOnTextMessages(freshAccount, adminApi, playwright);
    const { api, organizerId } = freshAccount;
    const event = await createLiveEventWithFreeTicket(api, organizerId);
    await askForMobileAtCheckout(api, event.eventId);
    await messaging.connectSms(['GB']);
    const first = { email: uniqueEmail('shared-first'), firstName: 'Petra', lastName: 'Partner', phone: UK_MOBILE, agreesToTexts: true };
    const second = { email: uniqueEmail('shared-second'), firstName: 'Paul', lastName: 'Partner', phone: UK_MOBILE, agreesToTexts: true };
    await createOptedInOrder(publicApi, event, first);
    await createOptedInOrder(publicApi, event, second);
    await buildAudience(api, organizerId);
    await waitForContact(api, organizerId, first.email);
    await waitForContact(api, organizerId, second.email);

    const page = await freshAccount.newAuthedPage();
    const audience = new AudiencePage(page, organizerId);
    const texts = audience.drawerSection('Text message status');
    for (const person of [first, second]) {
      await audience.gotoContactsMatching(person.email);
      await audience.contactRow(`${person.firstName} ${person.lastName}`).click();
      await expect(texts).toContainText(OPTED_IN);
      await page.keyboard.press('Escape');
      await expect(audience.drawer()).toBeHidden();
    }

    const name = uniqueShort('Shared number text');
    const campaign = await messaging.createCampaign({ name, body: 'Two tickets left for Saturday.', channel: 'SMS' });
    await messaging.sendCampaign(campaign.id);
    await approveIfHeldForReview(adminApi, api, organizerId, campaign.id);
    await waitForCampaignStatus(api, organizerId, campaign.id, /^(SENDING|SENT)$/);
    const recipients = textRecipients(campaign.id);
    expect(recipients).toHaveLength(1);
    await page.goto(`/manage/organizer/${organizerId}/audience/campaigns/${campaign.id}`);
    await expect(page.getByTestId('campaign-recipient-count')).toContainText('1 recipient');
    await expect(page.getByTestId('campaign-recipient-row')).toHaveCount(1);
    await expect(page.getByTestId('campaign-recipient-row')).toContainText(/\+44•+456/);

    const optOutLink = new URL(evaluatePhp(
      `app(\\HiEvents\\Enterprise\\Crm\\Services\\Domain\\Campaign\\CampaignUnsubscribeTokenService::class)->smsUrl(${recipients[0].id})`,
    ));
    const visitor = await browser.newPage();
    try {
      await visitor.goto(optOutLink.pathname);
      await visitor.getByTestId('audience-unsubscribe-button').click();
      await expect(visitor.getByRole('heading', { name: "You've opted out of texts" })).toBeVisible();
      await expect(visitor.getByText(/won't send marketing text messages to \+44•+456 any more\./)).toBeVisible();
    } finally {
      await visitor.close();
    }

    for (const person of [first, second]) {
      await audience.gotoContactsMatching(person.email);
      await audience.contactRow(`${person.firstName} ${person.lastName}`).click();
      await expect(texts).toContainText(OPTED_OUT);
      await expect(audience.drawerSection('Consent history')).toContainText('Opt-out link in a text message');
      await expect(audience.marketingStatus()).toContainText('Subscribed');
      await page.keyboard.press('Escape');
      await expect(audience.drawer()).toBeHidden();
    }

    await audience.gotoSegments();
    await expect(readyMadeSegment(page, 'Text subscribers')).toContainText(/^Text subscribers0Opted in/);

    const again = uniqueShort('Second text');
    await page.goto(`/manage/organizer/${organizerId}/audience/campaigns/new?channel=SMS`);
    await expect(page.getByTestId('campaign-recipient-count')).toContainText('0 of 2 opted in to texts');
    await page.getByTestId('campaign-name').fill(again);
    await page.getByTestId('campaign-sms-body').fill('Nobody should get this one.');
    await page.getByTestId('campaign-review-send').click();
    const send = page.getByRole('dialog', { name: `Send "${again}"` });
    await expect(send.getByTestId('campaign-send-summary'))
      .toHaveText('Nobody in this segment has opted in to text messages in a country you send to, so there is no one to send to.');
    await expect(send.getByTestId('campaign-send-confirm')).toBeDisabled();
  });

  test('a segment with people who agreed to texts can be texted straight from the contact list once Twilio is connected', async ({ freshAccount, publicApi, adminApi, playwright, messaging }) => {
    await switchOnTextMessages(freshAccount, adminApi, playwright);
    const { api, organizerId } = freshAccount;
    const event = await createLiveEventWithFreeTicket(api, organizerId);
    await askForMobileAtCheckout(api, event.eventId);
    await messaging.connectSms(['GB']);
    const texter = uniqueEmail('texter');
    const emailOnly = uniqueEmail('email-only');
    await createOptedInOrder(publicApi, event, { email: texter, firstName: 'Tess', lastName: 'Texter', phone: UK_MOBILE, agreesToTexts: true });
    await createOptedInOrder(publicApi, event, { email: emailOnly, firstName: 'Emil', lastName: 'Emailer' });
    await buildAudience(api, organizerId);
    await waitForContact(api, organizerId, emailOnly);
    await messaging.disconnectSms();
    const segmentName = uniqueShort('Ticket holders');
    await messaging.createSegment(segmentName, [{ field: 'events_count', operator: 'gte', value: 1 }]);

    const page = await freshAccount.newAuthedPage();
    const audience = new AudiencePage(page, organizerId);
    const textThisSegment = page.getByRole('link', { name: 'Text this segment' });
    const chooseSegment = async (option: string): Promise<void> => {
      await audience.segmentSelect().click();
      await page.getByRole('option', { name: option, exact: true }).click();
    };
    await audience.gotoContacts();
    await chooseSegment(segmentName);
    await expect(audience.contactTotal()).toHaveText('2 contacts');
    await expect(page.getByRole('link', { name: 'Email this segment' })).toBeVisible();
    await expect(textThisSegment).toHaveCount(0);

    await messaging.connectSms(['GB']);
    await page.reload();
    await expect(audience.contactTotal()).toHaveText('2 contacts');
    await expect(textThisSegment).toBeVisible();
    await chooseSegment('All contacts');
    await expect(page.getByRole('link', { name: 'Email your subscribers' })).toBeVisible();
    await expect(textThisSegment).toHaveCount(0);
    await chooseSegment('Text subscribers');
    await expect(audience.contactTotal()).toHaveText('1 contact');
    await expect(textThisSegment).toBeVisible();
    await expect(page.getByRole('link', { name: 'Email this segment' })).toHaveCount(0);

    await chooseSegment(segmentName);
    await textThisSegment.click();
    await expect(page).toHaveURL(/\/audience\/campaigns\/new\?channel=SMS&segment=/);
    await expect(page.getByRole('combobox', { name: 'Send to' })).toHaveValue(segmentName);
    await expect(page.getByTestId('campaign-recipient-count')).toHaveText('1 of 2 opted in to texts');
    await expect(page.getByTestId('campaign-sms-body')).toBeVisible();
    await expect(page.getByTestId('campaign-subject')).toHaveCount(0);
  });
});
