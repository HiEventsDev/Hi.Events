import { test, expect } from '../../fixtures';
import { AttendeePage } from '../../pages/attendee.page';
import { BoxOfficePage } from '../../pages/box-office.page';
import { PublicEventPage } from '../../pages/public-event.page';
import { defaultBoxOffice } from '../../api/factory';
import { canTakeStripePayments, STRIPE_PAYMENTS_SKIP_REASON } from '../../api/stripe';
import { liftResaleThrottles } from '../../utils/resale';
import { createSoldOutEventWithListings, listingFor } from '../../utils/stripe-resale';
import { uniqueEmail } from '../../utils/unique';

test.describe('listed tickets are only sold through online checkout', () => {
  test.skip(!canTakeStripePayments(), STRIPE_PAYMENTS_SKIP_REASON);
  test.beforeEach(() => liftResaleThrottles());

  test('a ticket type sold out apart from a listing is sold out at the box office and for manually added attendees', { tag: '@stripe' }, async ({ page, authedPage, api, account, publicApi, mailpit }) => {
    test.slow();
    const { event, seller } = await createSoldOutEventWithListings(api, publicApi, mailpit, account.organizerId);
    const sellerTicket = seller.attendees[0];
    const boxOffice = await defaultBoxOffice(api, event.eventId);
    await new PublicEventPage(page).expectTicketsAvailable(event, 1);

    const door = new BoxOfficePage(page);
    await door.goto(boxOffice.short_id);
    await door.startSession('Sam', boxOffice.pin);
    const tile = page.getByRole('button', { name: event.productTitle, exact: true });
    await expect(tile).toContainText('Sold out');
    await expect(tile).toHaveAttribute('data-disabled', 'true');
    await expect(page.getByTestId(`box-office-qty-plus-${event.priceId}`)).toBeDisabled();

    const manualEmail = uniqueEmail('manual');
    const attendees = new AttendeePage(authedPage);
    await attendees.goto(event.eventId);
    await attendees.createAttendee({ firstName: 'Manual', lastName: 'Entry', email: manualEmail, productTitle: event.productTitle });

    await expect(authedPage.getByText(/There are no tickets available/).first()).toBeVisible();
    expect((await api.listAttendees(event.eventId)).map((attendee) => attendee.email)).toEqual([seller.buyerEmail]);
    expect(listingFor(sellerTicket.shortId)).toMatchObject({ status: 'LISTED', claimedByOrder: null });
    await new PublicEventPage(page).expectTicketsAvailable(event, 1);
  });
});
