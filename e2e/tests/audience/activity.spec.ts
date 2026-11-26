import { test, expect } from '../../fixtures';
import {
  createCompletedOrder,
  createCompletedPaidOrder,
  createLiveEventWithFreeTicket,
  createLiveEventWithPaidTicket,
  createReservedOrder,
  createSoldOutEvent,
} from '../../api/factory';
import { completePublicOrder } from '../../api/public-client';
import { AudiencePage } from '../../pages/audience.page';
import { CheckInPage } from '../../pages/check-in.page';
import { OrderPage } from '../../pages/order.page';
import { PublicEventPage } from '../../pages/public-event.page';
import { endEventDatesAnHourAgo } from '../../utils/audience-db';
import { uniqueEmail, uniqueName } from '../../utils/unique';
import { buildAudience, waitForContact } from './audience.shared';
import { chooseRule, readyMadeSegment, ruleValue } from './contacts.helpers';

const money = (amount: number): string => `$${amount.toFixed(2)}`;

test.describe('audience contact activity', () => {
  test('a check-in at the door shows on the contact, and a ticket holder who stayed away counts as a no-show once the date has ended', async ({ freshAccount, publicApi, browser }) => {
    test.slow();
    const { api, organizerId } = freshAccount;
    const event = await createLiveEventWithFreeTicket(api, organizerId);
    const came = uniqueEmail('came');
    const stayedAway = uniqueEmail('stayed-away');
    const arrival = await createCompletedOrder(publicApi, event, { buyerEmail: came, buyerFirstName: 'Carla', buyerLastName: 'Came' });
    await createCompletedOrder(publicApi, event, { buyerEmail: stayedAway, buyerFirstName: 'Stan', buyerLastName: 'Stayedaway' });
    await buildAudience(api, organizerId);
    await waitForContact(api, organizerId, stayedAway);
    const door = await api.createCheckInList(event.eventId, { name: uniqueName('Front door') });

    const staff = await browser.newPage();
    try {
      const checkIn = new CheckInPage(staff);
      await checkIn.goto(door.short_id);
      await checkIn.openSearchTab();
      await checkIn.search('Came');
      await checkIn.checkInButton(arrival.attendees[0].publicId).click();
      await expect(checkIn.checkOutButton(arrival.attendees[0].publicId)).toBeVisible();
      await expect(checkIn.progressChip()).toHaveText('1/2');
    } finally {
      await staff.close();
    }

    const page = await freshAccount.newAuthedPage();
    const audience = new AudiencePage(page, organizerId);
    await expect(async () => {
      await audience.gotoContactsMatching(came);
      await audience.contactRow('Carla Came').click();
      await expect(audience.drawer()).toContainText(/Check-ins\s*1\s*No-shows\s*0/, { timeout: 3_000 });
    }).toPass({ timeout: 45_000 });
    const checkedIn = audience.drawerSection('Activity').getByText('Checked in', { exact: true });
    await expect(checkedIn).toHaveCount(1);
    await expect(audience.drawerSection('Activity')).toContainText(event.title);

    await page.goto(`/manage/event/${event.eventId}/dashboard`);
    const turnout = page.getByTestId('event-dashboard-audience');
    await expect(turnout).toContainText(/Checked in\s*1\s*50% of tickets/);

    await audience.gotoContactsMatching(stayedAway);
    await audience.contactRow('Stan Stayedaway').click();
    await expect(audience.drawer()).toContainText(/Check-ins\s*0\s*No-shows\s*0/);
    await expect(audience.drawerSection('Activity').getByText('Checked in', { exact: true })).toHaveCount(0);

    endEventDatesAnHourAgo(event.eventId);
    await page.reload();
    await expect(audience.drawer()).toContainText(/Check-ins\s*0\s*No-shows\s*1/);
    await page.keyboard.press('Escape');
    await expect(audience.drawer()).toBeHidden();

    await audience.gotoContacts();
    await expect(audience.contactRows()).toHaveCount(2);
    await audience.filterButton().click();
    await chooseRule(page, audience.filterPanel(), 'Number of no-shows');
    await ruleValue(audience.filterPanel()).fill('1');
    await expect(audience.contactTotal()).toHaveText('1 contact');
    await expect(audience.contactRow('Stan Stayedaway')).toBeVisible();
    await expect(audience.contactRow('Carla Came')).toHaveCount(0);

    await audience.filterPanel().getByRole('button', { name: 'Clear filter' }).click();
    await audience.filterButton().click();
    await chooseRule(page, audience.filterPanel(), 'Number of check-ins');
    await ruleValue(audience.filterPanel()).fill('1');
    await expect(audience.contactTotal()).toHaveText('1 contact');
    await expect(audience.contactRow('Carla Came')).toBeVisible();

    const next = await createLiveEventWithFreeTicket(api, organizerId);
    await createCompletedOrder(publicApi, next, { buyerEmail: came, buyerFirstName: 'Carla', buyerLastName: 'Came' });
    await createCompletedOrder(publicApi, next, { buyerEmail: stayedAway, buyerFirstName: 'Stan', buyerLastName: 'Stayedaway' });
    await expect.poll(async () => (await api.findAudienceContacts(organizerId, stayedAway))[0]?.events_count, { timeout: 30_000 }).toBe(2);
    await page.goto(`/manage/event/${next.eventId}/dashboard`);
    await expect(turnout).toContainText('Ticket holders: 2 of 2');
    await expect(turnout).toContainText(/Likely to turn up\s*1\s*Tickets: 2\. Usual turnout: 50%\./);
  });

  test('someone who joins a waitlist reaches the audience on the next refresh and counts as having missed out', async ({ freshAccount, publicApi, browser }) => {
    test.slow();
    const { api, organizerId } = freshAccount;
    const event = await createSoldOutEvent(api, publicApi, organizerId, { waitlist: true });
    await buildAudience(api, organizerId);
    await waitForContact(api, organizerId, event.consumedOrder.buyerEmail);
    const hopeful = uniqueEmail('hopeful');

    const visitor = await browser.newPage();
    try {
      const publicPage = new PublicEventPage(visitor);
      await publicPage.goto(event.eventId, event.slug);
      await publicPage.joinWaitlist({ firstName: 'Hope', email: hopeful });
      await expect(visitor.getByText("You're on the waitlist!")).toBeVisible();
    } finally {
      await visitor.close();
    }

    const page = await freshAccount.newAuthedPage();
    const audience = new AudiencePage(page, organizerId);
    await audience.gotoContacts();
    await expect(audience.contactTotal()).toHaveText('1 contact');
    await audience.contactsMoreActions().click();
    await page.getByTestId('audience-resync-button').click();
    const confirm = page.getByRole('dialog').filter({ hasText: 'Refresh your audience from all orders and check-ins?' });
    await expect(confirm).toContainText('New orders already reach your audience on their own. Use this if something looks out of date.');
    await confirm.getByRole('button', { name: 'Refresh', exact: true }).click();
    await expect(page.getByText('Refreshing your audience in the background')).toBeVisible();
    await expect(audience.contactTotal()).toHaveText('2 contacts', { timeout: 45_000 });

    await audience.search(hopeful);
    await expect(audience.contactRows()).toHaveCount(1);
    await audience.contactRows().first().click();
    await expect(audience.drawer()).toContainText(hopeful);
    await expect(audience.drawer()).toContainText(/Events\s*0\s*Tickets\s*0/);
    await expect(audience.drawerSection('Activity').getByText('Joined the waitlist', { exact: true })).toHaveCount(1);
    await expect(audience.drawerSection('Activity')).toContainText(event.title);
    await expect(audience.marketingStatus()).toContainText('Not subscribed');
    await page.keyboard.press('Escape');
    await expect(audience.drawer()).toBeHidden();

    await audience.gotoSegments();
    await expect(readyMadeSegment(page, 'Missed out')).toContainText('Joined a waitlist and never got a ticket');
    await expect(readyMadeSegment(page, 'Missed out')).toContainText(/^Missed out1Joined a waitlist and never got a ticket/);
    await readyMadeSegment(page, 'Missed out').click();
    await expect(audience.contactTotal()).toHaveText('1 contact');
    await expect(audience.contactRows().first()).toContainText(hopeful);
  });

  test('a refund is taken off what the contact has spent and shows in their activity, and a cancelled order is marked as cancelled', async ({ freshAccount, publicApi }) => {
    test.slow();
    const { api, organizerId } = freshAccount;
    const event = await createLiveEventWithPaidTicket(api, organizerId, 25);
    const refunded = uniqueEmail('refunded');
    const cancelled = uniqueEmail('cancelled');
    const paid = await createCompletedPaidOrder(api, publicApi, event, { buyerEmail: refunded, buyerFirstName: 'Rhea', buyerLastName: 'Refunded' });
    await createCompletedPaidOrder(api, publicApi, event, { buyerEmail: cancelled, buyerFirstName: 'Cal', buyerLastName: 'Cancelled' });
    await buildAudience(api, organizerId);
    await waitForContact(api, organizerId, cancelled);

    const page = await freshAccount.newAuthedPage();
    const audience = new AudiencePage(page, organizerId);
    await audience.gotoContactsMatching(refunded);
    await audience.contactRow('Rhea Refunded').click();
    await expect(audience.drawerSection('Spend')).toContainText(money(paid.totalGross));
    await expect(audience.drawerSection('Spend')).not.toContainText('refunded');

    const orders = new OrderPage(page);
    await orders.goto(event.eventId);
    await orders.chooseRowAction(refunded, 'Refund order');
    await expect(page.getByText('This order was paid offline.')).toBeVisible();
    await page.getByLabel(/^Refund amount/).fill('10');
    await page.getByRole('button', { name: 'Record Refund' }).click();
    await expect(page.getByText('Refund recorded successfully.')).toBeVisible();

    await orders.chooseRowAction(cancelled, 'Cancel order');
    await orders.confirmCancelOrder();
    await expect(orders.rowByEmail(cancelled)).toContainText('Cancelled');

    await expect(async () => {
      await audience.gotoContactsMatching(refunded);
      await audience.contactRow('Rhea Refunded').click();
      await expect(audience.drawerSection('Spend')).toContainText(`${money(10)} refunded`, { timeout: 3_000 });
    }).toPass({ timeout: 45_000 });
    await expect(audience.drawerSection('Spend')).toContainText(money(paid.totalGross - 10));
    const refundRow = audience.drawerSection('Activity').getByRole('link').filter({ hasText: 'Refunded' });
    await expect(refundRow).toHaveCount(1);
    await expect(refundRow).toContainText(`−${money(10)}`);
    await expect(audience.drawer()).toContainText(/Events\s*1\s*Tickets\s*1/);

    await expect(async () => {
      await audience.gotoContactsMatching(cancelled);
      await audience.contactRow('Cal Cancelled').click();
      await expect(audience.drawerSection('Activity').getByText('Order cancelled', { exact: true })).toHaveCount(1, { timeout: 3_000 });
    }).toPass({ timeout: 45_000 });
    await expect(audience.drawer()).toContainText(/Events\s*0\s*Tickets\s*0/);
    await expect(audience.drawerSection('Spend')).toHaveCount(0);
  });

  test('tickets bought for someone else and an order under another name show on the buyer, not as extra contacts', async ({ freshAccount, publicApi }) => {
    const { api, organizerId } = freshAccount;
    const event = await createLiveEventWithFreeTicket(api, organizerId);
    const later = await createLiveEventWithFreeTicket(api, organizerId);
    const email = uniqueEmail('host');
    const { orderShortId, sessionId } = await createReservedOrder(publicApi, event, { quantity: 2 });
    const ticket = { product_id: event.productId, product_price_id: event.priceId, email };
    await completePublicOrder(publicApi, event.eventId, orderShortId, sessionId, {
      order: { first_name: 'Holly', last_name: 'Host', email },
      attendees: [
        { ...ticket, first_name: 'Holly', last_name: 'Host' },
        { ...ticket, first_name: 'Greta', last_name: 'Guest' },
      ],
    });
    await createCompletedOrder(publicApi, later, { buyerEmail: email, buyerFirstName: 'Hol', buyerLastName: 'Hostess' });
    await buildAudience(api, organizerId);
    await waitForContact(api, organizerId, email);

    const page = await freshAccount.newAuthedPage();
    const audience = new AudiencePage(page, organizerId);
    await audience.gotoContacts();
    await expect(audience.contactTotal()).toHaveText('1 contact');
    await audience.contactRows().first().click();
    await expect(audience.drawer()).toContainText(email);
    await expect(audience.drawer()).toContainText(/Events\s*2\s*Tickets\s*3/);
    await expect(audience.drawerSection('Also buys tickets for')).toContainText('Greta Guest');
    await expect(audience.drawerSection('Activity').getByText('Ticket for Greta Guest (guest)', { exact: true })).toHaveCount(1);
    const names = audience.drawerSection('Other names used');
    await expect(names).toContainText(/Holly Host|Hol Hostess/);
    await expect(audience.drawerSection('Activity').getByText('Placed an order', { exact: true })).toHaveCount(2);
  });
});
