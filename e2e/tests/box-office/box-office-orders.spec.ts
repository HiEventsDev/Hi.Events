import { test, expect } from '../../fixtures';
import { BoxOfficePage } from '../../pages/box-office.page';
import { createDoorSale, createLiveEventWithPaidTicket, defaultBoxOffice } from '../../api/factory';
import { uniqueEmail } from '../../utils/unique';

test.describe('box office orders tab', () => {
  test('a sale can be found, opened and voided from the orders tab', async ({ page, api, publicApi, account }) => {
    const event = await createLiveEventWithPaidTicket(api, account.organizerId, 25);
    const boxOffice = await defaultBoxOffice(api, event.eventId);
    const sale = await createDoorSale(publicApi, boxOffice, event, { operatorName: 'Robin' });

    const door = new BoxOfficePage(page);
    await door.goto(boxOffice.short_id);
    await door.startSession('Sam', boxOffice.pin);
    await door.openOrdersTab();

    await door.searchOrders(sale.public_id);
    await expect(door.orderRow(sale.public_id)).toContainText('Robin');

    await door.openOrder(sale.public_id);
    await expect(door.detailSheet().getByText('Cash')).toBeVisible();

    await door.voidSale();
    await expect(page.getByText('This sale was voided')).toBeVisible();

    const orders = await api.listOrders(event.eventId);
    expect(orders.find((order) => order.short_id === sale.short_id)?.status).toBe('CANCELLED');
  });

  test('a sale checked in at the door stays checked in when it is reopened', async ({ page, api, account }) => {
    const event = await createLiveEventWithPaidTicket(api, account.organizerId, 25);
    const boxOffice = await defaultBoxOffice(api, event.eventId);

    const door = new BoxOfficePage(page);
    await door.goto(boxOffice.short_id);
    await door.startSession('Sam', boxOffice.pin);
    await door.addProduct(event.priceId, 2);
    await door.charge();
    await door.payCash();
    await door.checkInNowButton().click();
    await expect(door.checkInNowButton()).toContainText('Checked in (2/2)');

    const orders = await api.listOrders(event.eventId);
    const sale = orders.find((order) => order.box_office_id === boxOffice.id);
    await door.newSale();
    await door.openOrdersTab();
    await door.openOrder(sale!.public_id);

    const reopenedCheckIn = door.detailSheet().getByTestId('box-office-check-in-now-button');
    await expect(reopenedCheckIn).toContainText('Checked in (2/2)');
    await expect(reopenedCheckIn).toBeDisabled();
  });

  test('tickets are emailed to a walk-up buyer who gives an address after paying', async ({ page, api, account, mailpit }) => {
    const event = await createLiveEventWithPaidTicket(api, account.organizerId, 25);
    const boxOffice = await defaultBoxOffice(api, event.eventId);
    const buyerEmail = uniqueEmail('walkup');

    const door = new BoxOfficePage(page);
    await door.goto(boxOffice.short_id);
    await door.startSession('Sam', boxOffice.pin);
    await door.addProduct(event.priceId, 1);
    await door.charge();
    await door.payCash();
    await expect(door.saleCompleteHeading()).toBeVisible();

    await door.sendTicketsTo(buyerEmail);

    await expect(page.getByText(`Tickets sent to ${buyerEmail}`)).toBeVisible();
    await mailpit.waitForMessage(buyerEmail, { timeoutMs: 30_000 });
  });
});
