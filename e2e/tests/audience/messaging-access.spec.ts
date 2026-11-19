import type { APIRequestContext, Page } from '@playwright/test';
import { expect, type FreshAccount } from '../../fixtures';
import type { AdminApiClient } from '../../api/api-client';
import { createLiveEventWithFreeTicket } from '../../api/factory';
import { AudiencePage } from '../../pages/audience.page';
import { simulateLicence, withLicence } from '../../utils/licence';
import { invitedMember } from '../../utils/team';
import { uniqueEmail, uniqueShort } from '../../utils/unique';
import { buildAudience, createOptedInOrder, waitForContact } from './audience.shared';
import { approveIfHeldForReview, automationCard, automationSwitch, test, tomorrow, waitForCampaignStatus, type MessagingApi } from './messaging.shared';

interface Messaging {
  subscriber: string;
  draft: { id: number; subject: string };
  sent: { id: number; subject: string };
  scheduled: { id: number; subject: string };
  template: string;
  automation: string;
  form: string;
}

const arrangeMessaging = async (
  { freshAccount, publicApi, adminApi, messaging }: { freshAccount: FreshAccount; publicApi: APIRequestContext; adminApi: AdminApiClient; messaging: MessagingApi },
): Promise<Messaging> => {
  const { api, organizerId } = freshAccount;
  const accountId = (await api.getAccount()).id;
  const event = await createLiveEventWithFreeTicket(api, organizerId);
  const subscriber = uniqueEmail('reader');
  await createOptedInOrder(publicApi, event, { email: subscriber, firstName: 'Rae', lastName: 'Reader' });
  await buildAudience(api, organizerId);
  await waitForContact(api, organizerId, subscriber);
  await adminApi.grantMessagingPrivileges(accountId);
  await adminApi.setAccountFeatureFlag(accountId, 'crm_sms', true);

  const body = '<p>News from the box office.</p>';
  const sentSubject = uniqueShort('Already out');
  const sent = await messaging.createCampaign({ name: sentSubject, subject: sentSubject, body });
  await messaging.sendCampaign(sent.id);
  await approveIfHeldForReview(adminApi, api, organizerId, sent.id);
  await waitForCampaignStatus(api, organizerId, sent.id, /^SENT$/);

  const scheduledSubject = uniqueShort('Going out tomorrow');
  const scheduled = await messaging.createCampaign({ name: scheduledSubject, subject: scheduledSubject, body });
  await messaging.sendCampaign(scheduled.id, tomorrow());
  await approveIfHeldForReview(adminApi, api, organizerId, scheduled.id);
  await waitForCampaignStatus(api, organizerId, scheduled.id, /^SCHEDULED$/);

  const draftSubject = uniqueShort('Still a draft');
  const draft = await messaging.createCampaign({ name: draftSubject, subject: draftSubject, body });
  const template = uniqueShort('House style');
  await messaging.createTemplate({ name: template, subject: 'From the house', body });
  const automation = uniqueShort('Announce shows');
  await messaging.switchOnAutomation((await messaging.createAutomation('EVENT_PUBLISHED', { name: automation })).id);
  const form = uniqueShort('Front page form');
  await api.updateOrganizerStatus(organizerId, 'LIVE');
  await messaging.createSignupForm({ name: form, title: 'Hear it first' });
  await messaging.connectSms(['GB']);

  return {
    subscriber,
    draft: { id: draft.id, subject: draftSubject },
    sent: { id: sent.id, subject: sentSubject },
    scheduled: { id: scheduled.id, subject: scheduledSubject },
    template,
    automation,
    form,
  };
};

const campaignPath = (organizerId: number, campaignId: number): string => `/manage/organizer/${organizerId}/audience/campaigns/${campaignId}`;

const expectNoCampaignWriteControls = async (page: Page): Promise<void> => {
  for (const action of ['Save draft', 'Send', 'Send me a test', 'Delete']) {
    await expect(page.getByRole('button', { name: action, exact: true })).toHaveCount(0);
  }
  await expect(page.getByTestId('campaign-more-actions')).toHaveCount(0);
  await expect(page.getByTestId('campaign-subject')).toHaveCount(0);
  await expect(page.getByTestId('campaign-body')).toHaveCount(0);
};

test.describe('audience messaging access', () => {
  test('a viewer reads campaigns, templates, automations, forms and the text message setup, with nothing to change them', async ({ freshAccount, publicApi, adminApi, messaging, mailpit, browser }) => {
    test.slow();
    const { organizerId } = freshAccount;
    const arranged = await arrangeMessaging({ freshAccount, publicApi, adminApi, messaging });

    const { page } = await invitedMember(freshAccount, mailpit, publicApi, browser, { role: 'VIEWER' });
    try {
      const audience = new AudiencePage(page, organizerId);
      await audience.gotoCampaigns();
      await expect(audience.campaignRow(arranged.draft.subject)).toContainText('Draft');
      await expect(audience.campaignRow(arranged.sent.subject)).toContainText('Sent');
      await expect(audience.campaignRow(arranged.scheduled.subject)).toContainText('Goes out');
      await expect(audience.campaignCreateButton()).toHaveCount(0);
      await page.goto(`/manage/organizer/${organizerId}/audience/campaigns/new`);
      await expect(page).toHaveURL(/\/audience\/campaigns$/);

      await page.goto(campaignPath(organizerId, arranged.draft.id));
      await expect(page.getByTestId('campaign-title')).toContainText(arranged.draft.subject);
      await expect(audience.campaignStatus()).toContainText('Draft');
      await expect(page.getByTestId('campaign-preview').contentFrame().getByText('News from the box office.')).toBeVisible();
      await expectNoCampaignWriteControls(page);

      await page.goto(campaignPath(organizerId, arranged.scheduled.id));
      await expect(audience.campaignStatus()).toContainText('Scheduled');
      await expect(page.getByRole('button', { name: 'Move back to draft' })).toHaveCount(0);
      await expectNoCampaignWriteControls(page);

      await page.goto(campaignPath(organizerId, arranged.sent.id));
      await expect(audience.campaignStatus()).toContainText('Sent');
      await expect(page.getByTestId('campaign-recipient-row')).toContainText(arranged.subscriber);
      await expect(page.getByText('What was sent')).toBeVisible();
      await expectNoCampaignWriteControls(page);

      await page.goto(`/manage/organizer/${organizerId}/audience/templates`);
      const template = page.getByTestId('template-row').filter({ hasText: arranged.template });
      await expect(template).toContainText('From the house');
      await expect(page.getByTestId('template-create-button')).toHaveCount(0);
      await expect(template.getByTestId('template-use')).toHaveCount(0);
      await expect(template.getByRole('button', { name: 'Template actions' })).toHaveCount(0);

      await audience.gotoAutomations();
      const automation = automationCard(page, arranged.automation);
      await expect(automation).toContainText('On');
      await expect(automation).toContainText('When you publish an event · Everyone who opted in');
      await expect(automation.getByRole('switch')).toHaveCount(0);
      await expect(automation.getByTestId('automation-edit')).toHaveCount(0);
      await expect(automation.getByTestId('automation-menu')).toHaveCount(0);
      await expect(page.getByTestId('automation-new-button')).toHaveCount(0);

      await audience.gotoForms();
      const form = page.getByTestId('signup-form-row').filter({ hasText: arranged.form });
      await expect(form).toContainText('Live');
      await expect(form.getByTestId('signup-form-started')).toHaveText('0');
      await expect(page.getByTestId('signup-form-create-button')).toHaveCount(0);
      await expect(form.getByRole('button', { name: 'Form actions' })).toHaveCount(0);

      await page.goto(`/manage/organizer/${organizerId}/settings#text-messages`);
      const texts = page.locator('#text-messages');
      await expect(texts).toContainText('United Kingdom');
      await expect(texts).toContainText(/Messaging ServiceMG[0-9a-f]{32}/);
      for (const action of ['Edit', 'Disconnect', 'Send test', 'Connect Twilio']) {
        await expect(texts.getByRole('button', { name: action, exact: true })).toHaveCount(0);
      }
    } finally {
      await page.context().close();
    }
  });

  test('with a lapsed licence campaigns and automations are paused and read-only, but a schedule can be cancelled, an automation switched off and Twilio disconnected', async ({ freshAccount, publicApi, adminApi, messaging, playwright }) => {
    test.slow();
    const me = await withLicence(playwright, freshAccount.token, 'LAPSED', (api) => api.getMe());
    test.skip(me.licence.status !== 'LAPSED', 'This backend ignores X-Hi-Licence-Simulation (APP_ENV is not e2e or testing).');
    const { api, organizerId } = freshAccount;
    const arranged = await arrangeMessaging({ freshAccount, publicApi, adminApi, messaging });

    const page = await freshAccount.newAuthedPage();
    await simulateLicence(page, 'LAPSED');
    const audience = new AudiencePage(page, organizerId);
    await audience.gotoCampaigns();
    await expect(page.getByText('Scheduled campaigns are paused')).toBeVisible();
    await expect(page.getByText("Scheduled campaigns don't send while your licence is inactive. One that misses its time by more than 6 hours goes back to your drafts and we email you.")).toBeVisible();
    await expect(audience.campaignRow(arranged.scheduled.subject)).toContainText('Paused');
    await expect(audience.campaignCreateButton()).toHaveCount(0);

    await page.goto(campaignPath(organizerId, arranged.draft.id));
    await expect(page.getByTestId('campaign-title')).toContainText(arranged.draft.subject);
    for (const action of ['Save draft', 'Send', 'Send me a test']) {
      await expect(page.getByRole('button', { name: action, exact: true })).toHaveCount(0);
    }
    await expect(page.getByTestId('campaign-subject')).toHaveCount(0);

    await page.goto(campaignPath(organizerId, arranged.scheduled.id));
    await expect(page.getByText("Scheduled campaigns don't send while your licence is inactive. If this one misses its time by more than 6 hours, it goes back to your drafts and we email you.")).toBeVisible();
    await page.getByRole('button', { name: 'Move back to draft' }).click();
    await expect(page.getByText('Moved back to draft')).toBeVisible();
    await expect(audience.campaignStatus()).toContainText('Draft');
    expect((await api.getAudienceCampaign(organizerId, arranged.scheduled.id)).status).toBe('DRAFT');

    await page.goto(`/manage/organizer/${organizerId}/audience/templates`);
    await expect(page.getByTestId('template-row').filter({ hasText: arranged.template })).toBeVisible();
    await expect(page.getByTestId('template-create-button')).toHaveCount(0);
    await expect(page.getByTestId('template-use')).toHaveCount(0);

    await audience.gotoForms();
    await expect(page.getByTestId('signup-form-row').filter({ hasText: arranged.form })).toBeVisible();
    await expect(page.getByTestId('signup-form-create-button')).toHaveCount(0);

    await audience.gotoAutomations();
    await expect(page.getByText('Automations are paused')).toBeVisible();
    await expect(page.getByText("Automations don't send while your licence is inactive. They pick up again once it's renewed.")).toBeVisible();
    const automation = automationCard(page, arranged.automation);
    await expect(automation).toContainText('Paused');
    await expect(automation.getByTestId('automation-edit')).toHaveCount(0);
    await expect(page.getByTestId('automation-new-button')).toHaveCount(0);
    await automationSwitch(page, arranged.automation).press('Space');
    await expect(page.getByText('Automation turned off')).toBeVisible();
    await expect(page.getByText('Automations are paused')).toHaveCount(0);
    await expect(automationSwitch(page, arranged.automation)).toBeDisabled();

    await page.goto(`/manage/organizer/${organizerId}/settings#text-messages`);
    const texts = page.locator('#text-messages');
    await expect(texts).toContainText('United Kingdom');
    await expect(texts.getByRole('button', { name: 'Edit', exact: true })).toHaveCount(0);
    await texts.getByRole('button', { name: 'Disconnect' }).click();
    await audience.confirm('Disconnect');
    await expect(page.getByText('Twilio disconnected')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Connect Twilio' })).toHaveCount(0);
    await expect(texts.getByText('United Kingdom')).toHaveCount(0);
  });
});
