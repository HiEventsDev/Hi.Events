import type { Page } from '@playwright/test';
import { expect, type FreshAccount } from '../../fixtures';
import type { AdminApiClient } from '../../api/api-client';
import { createLiveEventWithFreeTicket } from '../../api/factory';
import { AudiencePage } from '../../pages/audience.page';
import { CheckoutPage } from '../../pages/checkout.page';
import { EventSettingsPage } from '../../pages/event-settings.page';
import { simulateLicence, withLicence } from '../../utils/licence';
import { askForMobileAtCheckout, buildAudience, UK_MOBILE, withAudienceApi } from './audience.shared';
import { test } from './messaging.shared';

const NOT_CLOUD = 'This backend is not running as Hi.Events Cloud and ignores licence simulation, so account feature flags cannot be switched off.';
const NOT_FAKED = 'This backend talks to real Twilio (SMS_DRIVER is not fake), so a Twilio number cannot be connected.';

const switchTextMessages = async (adminApi: AdminApiClient, freshAccount: FreshAccount, isOn: boolean): Promise<void> => {
  await adminApi.setAccountFeatureFlag((await freshAccount.api.getAccount()).id, 'crm_sms', isOn);
};

const textMessagesAreOn = (playwright: Parameters<typeof withLicence>[0], freshAccount: FreshAccount): Promise<boolean> =>
  withLicence(playwright, freshAccount.token, 'CLOUD', async (api) => (await api.getMe()).feature_flags.crm_sms);

const openCampaignPicker = async (page: Page, audience: AudiencePage): Promise<void> => {
  await audience.gotoCampaigns();
  await audience.campaignCreateButton().click();
  await expect(page.getByTestId('campaign-picker-start')).toBeVisible();
};

test.describe('text messages on Hi.Events Cloud', () => {
  test('text messages stay hidden from settings, campaigns and checkout until they are switched on for the account', async ({ freshAccount, adminApi, playwright, messaging, browser }) => {
    await switchTextMessages(adminApi, freshAccount, true);
    test.skip(!(await withAudienceApi(playwright, freshAccount, (audienceApi) => audienceApi.smsProviderIsFaked())), NOT_FAKED);
    await switchTextMessages(adminApi, freshAccount, false);
    test.skip(await textMessagesAreOn(playwright, freshAccount), NOT_CLOUD);
    const { api, organizerId } = freshAccount;
    const event = await createLiveEventWithFreeTicket(api, organizerId);
    await askForMobileAtCheckout(api, event.eventId, 'REQUIRED');
    await buildAudience(api, organizerId);

    const page = await freshAccount.newAuthedPage();
    await simulateLicence(page, 'CLOUD');
    const settings = new EventSettingsPage(page);
    const audience = new AudiencePage(page, organizerId);
    const checkoutSettings = settings.section('homepage-settings');
    const textOptIn = checkoutSettings.getByRole('switch', { name: 'Show text message opt-in checkbox' });
    await settings.goto(event.eventId, 'homepage-settings');
    await expect(checkoutSettings.getByTestId('phone-collection-select')).toContainText('Required');
    await expect(textOptIn).toHaveCount(0);

    await page.goto(`/manage/organizer/${organizerId}/settings/event-defaults`);
    await expect(page.getByRole('heading', { name: 'Event Defaults' }).first()).toBeVisible();
    await expect(page.getByRole('link', { name: 'Text Messages' })).toHaveCount(0);
    await expect(page.getByRole('switch', { name: 'Show text message opt-in checkbox by default' })).toHaveCount(0);

    await openCampaignPicker(page, audience);
    await expect(page.getByTestId('campaign-picker-channel')).toHaveCount(0);

    const visitorContext = await browser.newContext({ ignoreHTTPSErrors: true });
    try {
      const visitor = await visitorContext.newPage();
      await simulateLicence(visitor, 'CLOUD');
      const checkout = new CheckoutPage(visitor);
      await checkout.gotoPublicEvent(event.eventId, event.slug);
      await checkout.setFirstProductQuantity(1);
      await checkout.continueToCheckout();
      await visitor.getByRole('textbox', { name: 'Mobile number' }).fill(UK_MOBILE);
      await expect(visitor.getByRole('button', { name: 'Country code' })).toContainText('GB +44');
      await expect(visitor.getByTestId('checkout-sms-opt-in')).toHaveCount(0);

      await switchTextMessages(adminApi, freshAccount, true);
      await messaging.connectSms(['GB']);
      await settings.goto(event.eventId, 'homepage-settings');
      await expect(textOptIn).toBeChecked();
      await page.goto(`/manage/organizer/${organizerId}/settings/text-messages`);
      const texts = page.locator('#text-messages');
      await expect(texts.getByRole('button', { name: 'Edit', exact: true })).toBeVisible();
      await expect(texts).not.toContainText("Text messages aren't available on this account");
      await openCampaignPicker(page, audience);
      await expect(page.getByTestId('campaign-picker-channel')).toBeVisible();

      await checkout.gotoPublicEventAfterAvailabilityCacheExpires(event.eventId, event.slug);
      await checkout.setFirstProductQuantity(1);
      await checkout.continueToCheckout();
      await visitor.getByRole('textbox', { name: 'Mobile number' }).fill(UK_MOBILE);
      await expect(visitor.getByTestId('checkout-sms-opt-in')).toBeVisible();
    } finally {
      await visitorContext.close();
    }
  });

  test('once text messages are switched off for the account, a connected Twilio account can still be disconnected', async ({ freshAccount, adminApi, playwright, messaging }) => {
    const { api, organizerId } = freshAccount;
    await switchTextMessages(adminApi, freshAccount, true);
    await buildAudience(api, organizerId);
    await messaging.connectSms(['GB', 'IE']);
    await switchTextMessages(adminApi, freshAccount, false);
    test.skip(await textMessagesAreOn(playwright, freshAccount), NOT_CLOUD);

    const page = await freshAccount.newAuthedPage();
    await simulateLicence(page, 'CLOUD');
    const audience = new AudiencePage(page, organizerId);
    await openCampaignPicker(page, audience);
    await expect(page.getByTestId('campaign-picker-channel')).toHaveCount(0);

    await page.goto(`/manage/organizer/${organizerId}/settings/text-messages`);
    const texts = page.locator('#text-messages');
    await expect(texts).toContainText("Text messages aren't available on this account");
    await expect(texts).toContainText("Text message campaigns can't be sent, but your Twilio account is still connected. You can disconnect it here.");
    await expect(texts).toContainText('United Kingdom');
    await expect(texts.getByRole('button', { name: 'Edit', exact: true })).toHaveCount(0);
    await expect(texts.getByRole('button', { name: 'Send test' })).toHaveCount(0);
    await texts.getByRole('button', { name: 'Disconnect' }).click();
    await audience.confirm('Disconnect');
    await expect(page.getByText('Twilio disconnected')).toBeVisible();
    await expect(page.getByRole('link', { name: 'Text Messages' })).toHaveCount(0);
    await page.reload();
    await expect(page.getByRole('heading', { name: 'Basic Information' }).first()).toBeVisible();
    await expect(page.getByRole('link', { name: 'Text Messages' })).toHaveCount(0);
  });
});
