import {test, expect} from '../../fixtures';
import {CheckoutPage} from '../../pages/checkout.page';
import {SeatPickerPage} from '../../pages/seat-picker.page';
import {createSeatedEvent} from '../../api/factory';
import {parsePaymentReturnUrl} from '../../api/stripe';
import {expireOrder} from '../../utils/db';
import {uniqueEmail} from '../../utils/unique';

const SEAT = 'e2.0.9';

test.describe('seat hold expiry', () => {
  test.beforeEach(async ({adminApi, account}) => {
    await adminApi.setAccountFeatureFlag(await adminApi.findAccountIdByEmail(account.email), 'seating', true);
  });

  test('a seat whose hold ran out goes to the next buyer and the first buyer cannot complete', async ({page, browser, api, account}) => {
    const event = await createSeatedEvent(api, account.organizerId);

    const firstBuyer = new CheckoutPage(page);
    await firstBuyer.gotoPublicEvent(event.eventId, event.slug);
    await new SeatPickerPage(page).selectSeat(SEAT);
    await new SeatPickerPage(page).continueToDetails();
    const {orderShortId} = parsePaymentReturnUrl(page.url());

    expireOrder(orderShortId);

    const secondContext = await browser.newContext();
    const secondPage = await secondContext.newPage();
    try {
      const secondBuyer = new CheckoutPage(secondPage);
      await secondBuyer.gotoPublicEvent(event.eventId, event.slug);
      const secondPicker = new SeatPickerPage(secondPage);
      await expect(secondPicker.seat(SEAT)).toHaveAttribute('data-state', 'free');
      await secondPicker.selectSeat(SEAT);
      await secondPicker.continueToDetails();
      await secondBuyer.fillOrderAndAttendees({firstName: 'Second', lastName: 'Buyer', email: uniqueEmail('second')}, 1);
      await secondBuyer.completeFreeOrder();
      await expect(secondPage.getByText('Stalls · A-10').filter({visible: true}).first()).toBeVisible();
    } finally {
      await secondContext.close();
    }

    await firstBuyer.fillOrderAndAttendees({firstName: 'First', lastName: 'Buyer', email: uniqueEmail('first')}, 1);
    await page.getByRole('button', {name: 'Complete Order'}).click();

    await expect(page.getByText('This order has expired').first()).toBeVisible();
    await expect(page).not.toHaveURL(/\/summary/);

    const [attendee] = await api.listAttendees(event.eventId);
    expect(attendee.first_name).toBe('Second');
    expect(attendee.seat_label).toBe('Stalls · A-10');
  });
});
