import { test, expect } from '../../fixtures';
import { AttendeePage } from '../../pages/attendee.page';
import { OrderPage } from '../../pages/order.page';
import { createDoorSale, createLiveEventWithPaidTicket, defaultBoxOffice } from '../../api/factory';
import { uniqueShort } from '../../utils/unique';

test.describe('walk-up sales without a buyer email', () => {
  test('an organizer renames a walk-up attendee that has no email', { tag: '@smoke' }, async ({ authedPage, api, account, publicApi }) => {
    const event = await createLiveEventWithPaidTicket(api, account.organizerId, 25);
    const boxOffice = await defaultBoxOffice(api, event.eventId);
    await createDoorSale(publicApi, boxOffice, event);

    const attendees = new AttendeePage(authedPage);
    await attendees.goto(event.eventId);

    await expect(attendees.rowByText('Box office sale')).toBeVisible();
    await expect(attendees.rowByText('Box office sale')).toContainText('No email provided');

    const newFirstName = uniqueShort('Renamed');
    await attendees.openRowAction('Box office sale', 'Manage attendee');
    await attendees.renameFirstName(newFirstName);

    await expect(attendees.editButton()).toBeVisible();
    await attendees.closeDrawer();
    await expect(attendees.rowByText(newFirstName)).toBeVisible();
  });

  test('message and resend actions are not offered for a walk-up attendee', async ({ authedPage, api, account, publicApi }) => {
    const event = await createLiveEventWithPaidTicket(api, account.organizerId, 25);
    const boxOffice = await defaultBoxOffice(api, event.eventId);
    await createDoorSale(publicApi, boxOffice, event);

    const attendees = new AttendeePage(authedPage);
    await attendees.goto(event.eventId);
    await attendees.rowByText('Box office sale').getByTestId('attendee-actions-trigger').click();

    await expect(authedPage.getByRole('menuitem', { name: 'Manage attendee' })).toBeVisible();
    await expect(authedPage.getByRole('menuitem', { name: 'Message attendee' })).toBeHidden();
    await expect(authedPage.getByRole('menuitem', { name: 'Resend ticket email' })).toBeHidden();
  });

  test('an organizer renames a walk-up order that has no email', async ({ authedPage, api, account, publicApi }) => {
    const event = await createLiveEventWithPaidTicket(api, account.organizerId, 25);
    const boxOffice = await defaultBoxOffice(api, event.eventId);
    await createDoorSale(publicApi, boxOffice, event);

    const orders = new OrderPage(authedPage);
    await orders.goto(event.eventId);

    await expect(orders.rowByEmail('Box office sale')).toContainText('No email provided');

    const newLastName = uniqueShort('Buyer');
    await orders.chooseRowAction('Box office sale', 'Manage order');
    await authedPage.getByTestId('order-edit-button').click();
    await authedPage.getByLabel(/^Last name/).fill(newLastName);
    await authedPage.getByRole('button', { name: 'Save Changes' }).click();

    await expect(authedPage.getByTestId('order-edit-button')).toBeVisible();
    await authedPage.keyboard.press('Escape');
    await expect(authedPage.getByRole('row').filter({ hasText: newLastName })).toBeVisible();
  });

  test('message and resend actions are not offered for a walk-up order', async ({ authedPage, api, account, publicApi }) => {
    const event = await createLiveEventWithPaidTicket(api, account.organizerId, 25);
    const boxOffice = await defaultBoxOffice(api, event.eventId);
    await createDoorSale(publicApi, boxOffice, event);

    const orders = new OrderPage(authedPage);
    await orders.goto(event.eventId);
    await orders.rowByEmail('Box office sale').getByTestId('order-actions-trigger').click();

    await expect(authedPage.getByRole('menuitem', { name: 'Message buyer' })).toBeHidden();
    await expect(authedPage.getByRole('menuitem', { name: 'Resend order email' })).toBeHidden();
  });
});
