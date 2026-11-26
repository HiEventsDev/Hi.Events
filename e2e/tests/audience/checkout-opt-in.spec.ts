import type { Browser } from '@playwright/test';
import { expect } from '../../fixtures';
import { createLiveEventWithFreeTicket, type SeededEvent } from '../../api/factory';
import { AudiencePage } from '../../pages/audience.page';
import { CheckoutPage, type BuyerDetails } from '../../pages/checkout.page';
import { uniqueEmail, uniqueShort } from '../../utils/unique';
import { buildAudience, marketingStatusOf, waitForContact } from './audience.shared';
import { emailsAbout } from './coverage.helpers';
import { approveIfHeldForReview, test, waitForCampaignStatus } from './messaging.shared';

const buyTicket = async (
  browser: Browser,
  event: SeededEvent,
  buyer: BuyerDetails,
  { ticksTheMarketingBox, organizerName }: { ticksTheMarketingBox: boolean; organizerName: string },
): Promise<void> => {
  const visitor = await browser.newPage();
  try {
    const checkout = new CheckoutPage(visitor);
    await checkout.gotoPublicEvent(event.eventId, event.slug);
    await checkout.setFirstProductQuantity(1);
    await checkout.continueToCheckout();
    await checkout.fillOrderDetails(buyer);
    await checkout.fillFirstAttendee(buyer);
    const marketingBox = visitor.getByRole('checkbox', { name: `Keep me updated on news and events from ${organizerName}` });
    await expect(marketingBox).not.toBeChecked();
    if (ticksTheMarketingBox) {
      await marketingBox.check();
    }
    await checkout.completeFreeOrder();
    await expect(visitor.getByText(`You're going to ${event.title}`)).toBeVisible();
  } finally {
    await visitor.close();
  }
};

test.describe('audience checkout opt-in', () => {
  test('a buyer who ticks the marketing box at checkout is subscribed and gets the next campaign, and one who leaves it unticked does not', async ({ freshAccount, adminApi, mailpit, messaging, browser }) => {
    test.slow();
    const { api, organizerId } = freshAccount;
    const organizer = await api.getOrganizer(organizerId);
    const event = await createLiveEventWithFreeTicket(api, organizerId);
    await api.updateEventSettings(event.eventId, { show_marketing_opt_in: true });
    await buildAudience(api, organizerId);
    await adminApi.grantMessagingPrivileges((await api.getAccount()).id);
    const ticker = { firstName: 'Tilly', lastName: 'Ticked', email: uniqueEmail('ticked') };
    const skipper = { firstName: 'Una', lastName: 'Unticked', email: uniqueEmail('unticked') };

    await buyTicket(browser, event, ticker, { ticksTheMarketingBox: true, organizerName: organizer.name });
    await buyTicket(browser, event, skipper, { ticksTheMarketingBox: false, organizerName: organizer.name });
    await waitForContact(api, organizerId, ticker.email);
    await waitForContact(api, organizerId, skipper.email);
    expect(await marketingStatusOf(api, organizerId, ticker.email)).toBe('SUBSCRIBED');
    expect(await marketingStatusOf(api, organizerId, skipper.email)).toBe('NOT_SUBSCRIBED');

    const page = await freshAccount.newAuthedPage();
    const audience = new AudiencePage(page, organizerId);
    await audience.gotoContactsMatching(ticker.email);
    await audience.contactRow('Tilly Ticked').click();
    await expect(audience.marketingStatus()).toContainText('Subscribed');
    await expect(audience.marketingStatus()).toContainText('Opted in to marketing emails. You can include them in campaigns.');
    await expect(audience.drawerSection('Consent history')).toContainText('Opted in');
    await expect(audience.drawerSection('Consent history')).toContainText('Ticked the marketing box at checkout');
    await page.keyboard.press('Escape');
    await expect(audience.drawer()).toBeHidden();

    await audience.gotoContactsMatching(skipper.email);
    await audience.contactRow('Una Unticked').click();
    await expect(audience.drawer()).toContainText(skipper.email);
    await expect(audience.marketingStatus()).toContainText('Not subscribed');
    await expect(audience.marketingStatus()).toContainText("Hasn't opted in to marketing emails, so campaigns skip them.");
    await expect(audience.drawerSection('Consent history')).not.toContainText('Ticked the marketing box at checkout');

    const subject = uniqueShort('Box ticked');
    await audience.composeCampaign(subject, 'You asked to hear from us, so here we are.');
    await expect(page.getByTestId('campaign-recipient-count')).toContainText('1 of 2 opted in');
    await page.getByTestId('campaign-review-send').click();
    const send = page.getByRole('dialog', { name: `Send "${subject}"` });
    await expect(send.getByTestId('campaign-send-summary'))
      .toHaveText('This goes to 1 person who opted in to marketing emails. Everyone else you selected is skipped.');
    await send.getByTestId('campaign-send-confirm').click();
    await page.waitForURL(/\/audience\/campaigns\/\d+$/);
    const campaignId = audience.campaignIdFromUrl();
    await expect(audience.campaignStatus()).not.toContainText('Draft');
    await approveIfHeldForReview(adminApi, api, organizerId, campaignId);

    await mailpit.waitForMessage(ticker.email, { subjectContains: subject, timeoutMs: 45_000 });
    await waitForCampaignStatus(api, organizerId, campaignId, /^SENT$/);
    expect(await emailsAbout(mailpit, skipper.email, subject)).toBe(0);
    await page.reload();
    await expect(page.getByTestId('campaign-recipient-row')).toHaveCount(1);
    await expect(page.getByTestId('campaign-recipient-row')).toContainText(ticker.email);
    await expect(page.getByTestId('campaign-recipient-row')).not.toContainText(skipper.email);
  });

  test('an event that does not show the marketing box never subscribes its buyers', async ({ freshAccount, browser }) => {
    const { api, organizerId } = freshAccount;
    const event = await createLiveEventWithFreeTicket(api, organizerId);
    await api.updateEventSettings(event.eventId, { show_marketing_opt_in: false });
    await buildAudience(api, organizerId);
    const buyer = { firstName: 'Noor', lastName: 'Notasked', email: uniqueEmail('not-asked') };

    const visitor = await browser.newPage();
    try {
      const checkout = new CheckoutPage(visitor);
      await checkout.gotoPublicEvent(event.eventId, event.slug);
      await checkout.setFirstProductQuantity(1);
      await checkout.continueToCheckout();
      await checkout.fillOrderDetails(buyer);
      await checkout.fillFirstAttendee(buyer);
      await expect(visitor.getByRole('checkbox', { name: /^Keep me updated on news and events from/ })).toHaveCount(0);
      await checkout.completeFreeOrder();
      await expect(visitor.getByText(`You're going to ${event.title}`)).toBeVisible();
    } finally {
      await visitor.close();
    }

    await waitForContact(api, organizerId, buyer.email);
    const page = await freshAccount.newAuthedPage();
    const audience = new AudiencePage(page, organizerId);
    await audience.gotoContactsMatching(buyer.email);
    await audience.contactRow('Noor Notasked').click();
    await expect(audience.marketingStatus()).toContainText('Not subscribed');
    await audience.gotoOverview();
    await expect(page.getByText(/Ticket buyers can only be emailed if they opt in at checkout\./)).toBeVisible();
  });
});
