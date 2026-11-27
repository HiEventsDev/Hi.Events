import { expect } from '../../fixtures';
import { createCompletedOrder, createLiveEventWithFreeTicket } from '../../api/factory';
import { AudiencePage } from '../../pages/audience.page';
import { CheckoutPage } from '../../pages/checkout.page';
import { uniqueEmail, uniqueShort } from '../../utils/unique';
import { buildAudience, createOptedInOrder, waitForContact } from './audience.shared';
import { approveIfHeldForReview, test, waitForCampaignStatus } from './messaging.shared';

test.describe('audience early access', () => {
  test('early access hides the ticket when the campaign sends, the emailed link unlocks it, and the organizer makes it public again', async ({ freshAccount, publicApi, mailpit, adminApi, browser }) => {
    test.slow();
    const { api, organizerId } = freshAccount;
    const earlier = await createLiveEventWithFreeTicket(api, organizerId);
    const event = await createLiveEventWithFreeTicket(api, organizerId);
    const subscriber = uniqueEmail('early-bird');
    const outsider = uniqueEmail('outsider');
    await createOptedInOrder(publicApi, earlier, { email: subscriber, firstName: 'Erin', lastName: 'Early' });
    await createCompletedOrder(publicApi, earlier, { buyerEmail: outsider, buyerFirstName: 'Otto', buyerLastName: 'Outsider' });
    await buildAudience(api, organizerId);
    await waitForContact(api, organizerId, outsider);
    await adminApi.grantMessagingPrivileges((await api.getAccount()).id);
    const subject = uniqueShort('First dibs');

    const visitor = await browser.newPage();
    const publicPage = new CheckoutPage(visitor);
    const ticketOnPublicPage = visitor.getByText(event.productTitle, { exact: true });
    await publicPage.gotoPublicEvent(event.eventId, event.slug);
    await expect(ticketOnPublicPage).toBeVisible();

    const page = await freshAccount.newAuthedPage();
    const audience = new AudiencePage(page, organizerId);
    await audience.composeCampaign(subject, 'You get to book before anyone else.');
    await page.getByTestId('campaign-event').click();
    await page.getByRole('option', { name: event.title }).click();
    await page.getByTestId('campaign-early-access').click();
    await page.getByRole('option', { name: event.productTitle }).click();
    await page.keyboard.press('Escape');
    await expect(page.getByTestId('campaign-early-access-notes')).toContainText(`Tickets that will be hidden from your event page when you send: ${event.productTitle}.`);
    await expect(page.getByTestId('campaign-preview').contentFrame().getByText('Get early access')).toBeVisible();

    await page.getByTestId('campaign-review-send').click();
    const send = page.getByRole('dialog', { name: `Send "${subject}"` });
    await expect(send.getByTestId('campaign-send-early-access')).toContainText(`Only people with this campaign's link can buy: ${event.productTitle}. The link can be forwarded.`);
    await expect(send.getByTestId('campaign-send-early-access')).toContainText('They stay hidden from everyone else until you make them public again from this campaign.');
    await publicPage.gotoPublicEvent(event.eventId, event.slug);
    await expect(ticketOnPublicPage).toBeVisible();
    await send.getByTestId('campaign-send-confirm').click();
    await expect(audience.campaignStatus()).not.toContainText('Draft');
    const campaignId = audience.campaignIdFromUrl();
    await approveIfHeldForReview(adminApi, api, organizerId, campaignId);
    await waitForCampaignStatus(api, organizerId, campaignId, /^SENT$/);

    await publicPage.gotoPublicEventAfterAvailabilityCacheExpires(event.eventId, event.slug);
    await expect(visitor.getByRole('heading', { name: event.title }).first()).toBeVisible();
    await expect(ticketOnPublicPage).toHaveCount(0);

    const earlyLink = await mailpit.waitForLink(subscriber, /promo_code=/, { subjectContains: subject, timeoutMs: 45_000 });
    expect((await mailpit.search(outsider)).filter((message) => message.Subject.includes(subject))).toHaveLength(0);
    await visitor.goto(earlyLink.toString());
    await expect(ticketOnPublicPage).toBeVisible();
    const buyer = { firstName: 'Erin', lastName: 'Early', email: subscriber };
    await publicPage.setFirstProductQuantity(1);
    await publicPage.continueToCheckout();
    await publicPage.fillOrderDetails(buyer);
    await publicPage.fillFirstAttendee(buyer);
    await publicPage.completeFreeOrder();
    await expect(visitor.getByText(`You're going to ${event.title}`)).toBeVisible();

    await page.reload();
    const eventLine = page.getByText(/^Early access with code /);
    await expect(eventLine).toHaveText(new RegExp(`^Early access with code \\S+: ${event.productTitle}\\. Hidden from everyone else\\.$`));
    await page.getByRole('button', { name: 'Make tickets public' }).click();
    await expect(page.getByRole('dialog', { name: 'Make 1 ticket public?' }))
      .toContainText(`Tickets that will show on your event page for everyone: ${event.productTitle}. The early access link keeps working.`);
    await audience.confirm('Make public');
    await expect(page.getByText('Tickets are public again')).toBeVisible();
    await expect(eventLine).toHaveText(new RegExp(`^Early access with code \\S+: ${event.productTitle}\\. Now public\\.$`));
    await expect(page.getByRole('button', { name: 'Make tickets public' })).toHaveCount(0);

    await publicPage.gotoPublicEventAfterAvailabilityCacheExpires(event.eventId, event.slug);
    await expect(ticketOnPublicPage).toBeVisible();
    await visitor.close();
  });
});
