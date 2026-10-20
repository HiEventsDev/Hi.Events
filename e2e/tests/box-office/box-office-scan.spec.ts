import { test, expect } from '../../fixtures';
import { BoxOfficePage } from '../../pages/box-office.page';
import { CheckInPage } from '../../pages/check-in.page';
import { createCompletedOrder, createLiveEventWithFreeTicket, defaultBoxOffice } from '../../api/factory';

test.describe('box office scan tab', () => {
  test('door staff check an existing attendee in from the scan tab', async ({ page, api, publicApi, account }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    const order = await createCompletedOrder(publicApi, event, { buyerLastName: 'Nolan' });
    const boxOffice = await defaultBoxOffice(api, event.eventId);

    const door = new BoxOfficePage(page);
    await door.goto(boxOffice.short_id);
    await door.startSession('Sam', boxOffice.pin);
    await door.openScanTab();

    await door.setScanMode('Search');

    const checkIn = new CheckInPage(page);
    await checkIn.search('Nolan');

    const attendee = order.attendees[0];
    await expect(checkIn.attendeeRow(attendee.publicId)).toBeVisible();

    await checkIn.checkInButton(attendee.publicId).click();
    await expect(checkIn.checkOutButton(attendee.publicId)).toBeVisible();
  });
});
