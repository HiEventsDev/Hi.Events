import { test, expect } from '../../fixtures';
import { BoxOfficePage } from '../../pages/box-office.page';
import {
  createCompletedOrder,
  createEventWithQuestions,
  createLiveEventWithPaidTicket,
  createLiveEventWithProduct,
  createRecurringLiveEvent,
  defaultBoxOffice,
} from '../../api/factory';
import { uniqueEmail } from '../../utils/unique';

test.describe('box office door sales', () => {
  test('a staff member sells two tickets for cash and checks the buyer in', { tag: '@smoke' }, async ({ page, api, account }) => {
    const event = await createLiveEventWithPaidTicket(api, account.organizerId, 25);
    const boxOffice = await defaultBoxOffice(api, event.eventId);

    const door = new BoxOfficePage(page);
    await door.goto(boxOffice.short_id);
    await door.startSession('Sam', boxOffice.pin);

    await door.addProduct(event.priceId, 2);
    await expect(page.getByTestId('box-office-charge-button').first()).toContainText('50.00');

    await door.charge();
    await expect(page.getByText('Total to pay')).toBeVisible();

    await door.payCash(100);
    await expect(door.saleCompleteHeading()).toBeVisible();
    await expect(page.getByText('change')).toBeVisible();

    await door.checkInNowButton().click();
    await expect(door.checkInNowButton()).toContainText('Checked in (2/2)');

    const orders = await api.listOrders(event.eventId);
    const doorOrder = orders.find((order) => order.box_office_id === boxOffice.id);
    expect(doorOrder).toBeTruthy();
    expect(doorOrder?.box_office_tender).toBe('CASH');
    expect(doorOrder?.status).toBe('COMPLETED');

    await door.openOrdersTab();
    await expect(door.orderRow(doorOrder!.public_id)).toContainText('Cash');
    await expect(door.orderRow(doorOrder!.public_id)).toContainText('Sam');
  });

  test('a comp sale completes with nothing to pay', async ({ page, api, account }) => {
    const event = await createLiveEventWithPaidTicket(api, account.organizerId, 25);
    const boxOffice = await defaultBoxOffice(api, event.eventId);

    const door = new BoxOfficePage(page);
    await door.goto(boxOffice.short_id);
    await door.startSession('Alex', boxOffice.pin);
    await door.addProduct(event.priceId, 1);
    await door.charge();
    await door.payComp();

    await expect(door.saleCompleteHeading()).toBeVisible();
    await expect(page.getByText(/· Comp$/)).toBeVisible();
  });

  test('a wrong PIN is rejected and a box office without a PIN cannot start', async ({ page, api, account }) => {
    const event = await createLiveEventWithPaidTicket(api, account.organizerId, 25);
    const boxOffices = await api.listBoxOffices(event.eventId);
    const unset = boxOffices.find((boxOffice) => boxOffice.is_system_default);
    if (!unset) throw new Error('No default box office');

    const door = new BoxOfficePage(page);
    await door.goto(unset.short_id);
    await expect(page.getByText('This box office has no PIN yet')).toBeVisible();
    await expect(page.getByTestId('box-office-start-button')).toBeDisabled();

    const boxOffice = await api.resetBoxOfficePin(event.eventId, unset.id);
    await door.goto(boxOffice.short_id);
    await door.startSession('Sam', boxOffice.pin === '000000' ? '000001' : '000000');
    await expect(page.getByText('Incorrect PIN')).toBeVisible();
  });

  test('staff switch the date they are selling for without signing out', async ({ page, api, account }) => {
    const event = await createRecurringLiveEvent(api, account.organizerId, { price: 25 });
    const boxOffice = await defaultBoxOffice(api, event.eventId);

    const door = new BoxOfficePage(page);
    await door.goto(boxOffice.short_id);
    await page.getByTestId('box-office-operator-name').fill('Sam');
    await page.getByTestId('box-office-pin').fill(boxOffice.pin);
    await door.chooseStartDate(0);
    await page.getByTestId('box-office-start-button').click();

    await expect(door.occurrenceChip()).toBeVisible();
    const firstLabel = await door.occurrenceChip().innerText();

    await door.addProduct(event.priceId, 1);
    await expect(door.occurrenceChip()).toBeDisabled();
    await page.getByTestId(`box-office-qty-minus-${event.priceId}`).click();
    await expect(door.occurrenceChip()).toBeEnabled();

    await door.switchDate(1);
    await expect(door.occurrenceChip()).not.toHaveText(firstLabel);

    await door.addProduct(event.priceId, 1);
    await door.charge();
    await door.payCash();
    await expect(door.saleCompleteHeading()).toBeVisible();

    const orders = await api.listOrders(event.eventId);
    const doorOrder = orders.find((order) => order.box_office_id === boxOffice.id);
    expect(doorOrder?.order_items?.[0]?.event_occurrence_id).toBe(event.occurrences[1].id);
  });

  test('order and attendee questions are asked before the sale when the box office collects them', async ({ page, api, account }) => {
    const event = await createEventWithQuestions(api, account.organizerId, { orderQuestionTitle: 'How did you hear about us?' });
    const boxOffice = await api.createBoxOffice(event.eventId, { name: 'Questions door', collect_order_questions: true });

    const door = new BoxOfficePage(page);
    await door.goto(boxOffice.short_id);
    await door.startSession('Sam', boxOffice.pin);
    await door.addProduct(event.priceId, 1);
    await door.charge();

    const sheet = page.getByRole('dialog');
    await expect(sheet.getByText('Answer the required questions to continue the sale.')).toBeVisible();
    await sheet.getByTestId('box-office-buyer-save-button').click();
    await expect(sheet.getByText('This field is required')).toBeVisible();

    await sheet.getByLabel('How did you hear about us?').fill('Poster');
    await sheet.getByTestId('box-office-buyer-save-button').click();

    await expect(page.getByText('Attendee 1 of 1')).toBeVisible();
    await page.getByTestId('box-office-attendee-next-button').click();
    await expect(page.getByText('This field is required')).toBeVisible();
    await page.getByRole('radio', { name: 'Medium' }).check();
    await page.getByTestId('box-office-attendee-next-button').click();

    await expect(page.getByText('Free order, no payment needed')).toBeVisible();
    await page.getByTestId('box-office-free-complete-button').click();
    await expect(door.saleCompleteHeading()).toBeVisible();
  });

  test('the buyer entered while answering required order questions is kept on the sale', async ({ page, api, account }) => {
    const event = await createLiveEventWithPaidTicket(api, account.organizerId, 25);
    await api.createQuestion(event.eventId, {
      title: 'How did you hear about us?',
      type: 'SINGLE_LINE_TEXT',
      belongs_to: 'ORDER',
      product_ids: [],
      required: true,
      is_hidden: false,
    });
    const boxOffice = await api.createBoxOffice(event.eventId, { name: 'Questions door', collect_order_questions: true });
    const buyerEmail = uniqueEmail('door-buyer');

    const door = new BoxOfficePage(page);
    await door.goto(boxOffice.short_id);
    await door.startSession('Sam', boxOffice.pin);
    await door.addProduct(event.priceId, 1);
    await door.charge();

    const sheet = page.getByRole('dialog');
    await sheet.getByLabel('Email', { exact: true }).fill(buyerEmail);
    await sheet.getByLabel('How did you hear about us?').fill('Poster');
    await sheet.getByTestId('box-office-buyer-save-button').click();

    await door.payCash();
    await expect(door.saleCompleteHeading()).toBeVisible();

    const orders = await api.listOrders(event.eventId);
    expect(orders.find((order) => order.box_office_id === boxOffice.id)?.email).toBe(buyerEmail);
  });

  test('selling more than is left shows the error on the product', async ({ page, api, publicApi, account }) => {
    const event = await createLiveEventWithProduct(api, { organizerId: account.organizerId, price: 25, quantityAvailable: 2 });
    await createCompletedOrder(publicApi, event, { quantity: 1, buyerFirstName: 'Early', buyerLastName: 'Bird' });
    const boxOffice = await defaultBoxOffice(api, event.eventId);

    const door = new BoxOfficePage(page);
    await door.goto(boxOffice.short_id);
    await door.startSession('Sam', boxOffice.pin);

    await expect(page.getByText('1 left')).toBeVisible();
    await door.addProduct(event.priceId, 1);
    await expect(page.getByTestId(`box-office-qty-plus-${event.priceId}`)).toBeDisabled();
  });
});
