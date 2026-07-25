import { test, expect } from '../../fixtures';
import { WaitlistPage } from '../../pages/waitlist.page';
import { createSoldOutEvent } from '../../api/factory';
import { joinWaitlist } from '../../api/public-client';
import { uniqueEmail } from '../../utils/unique';

test.describe('waitlist management', () => {
  test('an organizer reviews entries, offers a freed spot, and removes an entry', async ({
    authedPage,
    api,
    account,
    publicApi,
    mailpit,
  }) => {
    const event = await createSoldOutEvent(api, publicApi, account.organizerId, { waitlist: true });
    await api.updateEventSettings(event.eventId, { waitlist_auto_process: false });
    const firstEmail = uniqueEmail('first-waitlister');
    const secondEmail = uniqueEmail('second-waitlister');
    await joinWaitlist(publicApi, event.eventId, {
      product_price_id: event.priceId,
      email: firstEmail,
      first_name: 'First',
    });
    await joinWaitlist(publicApi, event.eventId, {
      product_price_id: event.priceId,
      email: secondEmail,
      first_name: 'Second',
    });

    const waitlist = new WaitlistPage(authedPage);
    await waitlist.goto(event.eventId);

    await expect(waitlist.entryStatus(firstEmail, 'WAITING')).toBeVisible();
    await expect(waitlist.entryStatus(secondEmail, 'WAITING')).toBeVisible();
    await expect(waitlist.statsCard('Waiting').getByText('2', { exact: true })).toBeVisible();

    const consumedOrderId = await api.findOrderIdByShortId(event.eventId, event.consumedOrder.orderShortId);
    await api.cancelOrder(event.eventId, consumedOrderId);

    await waitlist.goto(event.eventId);
    await waitlist.offerTickets(event.productTitle);

    await expect(waitlist.entryStatus(firstEmail, 'OFFERED')).toBeVisible();
    await mailpit.waitForMessage(firstEmail, { subjectContains: 'spot has opened up' });

    await waitlist.removeEntry(secondEmail);
    await expect(waitlist.entryStatus(secondEmail, 'CANCELLED')).toBeVisible();
  });
});
