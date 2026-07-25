import { test, expect } from '../../fixtures';
import { MyTicketsPage } from '../../pages/my-tickets.page';
import { createCompletedOrder, createLiveEventWithFreeTicket } from '../../api/factory';
import { sendTicketLookupEmail } from '../../api/public-client';
import { uniqueEmail } from '../../utils/unique';

test.describe('ticket lookup', () => {
  test('a buyer requests a lookup link and views their tickets', async ({ page, api, account, publicApi, mailpit }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    const buyerEmail = uniqueEmail('lookup');
    await createCompletedOrder(publicApi, event, { buyerEmail });

    await sendTicketLookupEmail(publicApi, buyerEmail);
    const lookupUrl = await mailpit.waitForLink(buyerEmail, /my-tickets/, { subjectContains: 'Your Tickets' });

    const myTickets = new MyTicketsPage(page);
    await myTickets.open(lookupUrl.href);

    await expect(page.getByRole('heading', { name: 'My Tickets' })).toBeVisible();
    await expect(myTickets.orderCardHeading(event.title)).toBeVisible();

    await myTickets.viewOrder();

    await expect(page.getByText(`You're going to ${event.title}`)).toBeVisible();
  });
});
