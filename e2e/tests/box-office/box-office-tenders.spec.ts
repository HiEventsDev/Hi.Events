import { test, expect } from '../../fixtures';
import { BoxOfficePage } from '../../pages/box-office.page';
import { createLiveEventWithFreeTicket, createLiveEventWithPaidTicket, defaultBoxOffice } from '../../api/factory';
import type { APIRequestContext } from '@playwright/test';
import type { ApiClient } from '../../api/api-client';
import { createBoxOfficeOrder, startBoxOfficeSession } from '../../api/public-client';
import { uniqueShort } from '../../utils/unique';

const doorOrder = async (api: ApiClient, eventId: number, boxOfficeId: number) => {
  const orders = await api.listOrders(eventId);
  return orders.find((order) => order.box_office_id === boxOfficeId);
};

const doorOrderStatus = async (publicApi: APIRequestContext, boxOfficeShortId: string, token: string, orderShortId: string) => {
  const response = await publicApi.get(`public/box-offices/${boxOfficeShortId}/orders/${orderShortId}`, {
    headers: { 'X-Box-Office-Session': token },
  });
  return (await response.json()).data.status as string;
};

test.describe('box office tenders', () => {
  test('a sale paid elsewhere is recorded with its reference', async ({ page, api, account }) => {
    const event = await createLiveEventWithPaidTicket(api, account.organizerId, 25);
    const boxOffice = await defaultBoxOffice(api, event.eventId);

    const door = new BoxOfficePage(page);
    await door.goto(boxOffice.short_id);
    await door.startSession('Sam', boxOffice.pin);
    await door.addProduct(event.priceId, 1);
    await door.charge();
    await door.payOther('SumUp 4471');

    await expect(door.saleCompleteHeading()).toBeVisible();
    await expect(page.getByText('Paid elsewhere')).toBeVisible();

    const order = await doorOrder(api, event.eventId, boxOffice.id);
    expect(order?.box_office_tender).toBe('OTHER');
    expect(order?.box_office_reference).toBe('SumUp 4471');
    expect(order?.status).toBe('COMPLETED');
  });

  test('a comp sale is recorded as comp with nothing owed', async ({ page, api, account }) => {
    const event = await createLiveEventWithPaidTicket(api, account.organizerId, 25);
    const boxOffice = await defaultBoxOffice(api, event.eventId);

    const door = new BoxOfficePage(page);
    await door.goto(boxOffice.short_id);
    await door.startSession('Sam', boxOffice.pin);
    await door.addProduct(event.priceId, 1);
    await door.charge();
    await door.payComp();

    await expect(door.saleCompleteHeading()).toBeVisible();

    const order = await doorOrder(api, event.eventId, boxOffice.id);
    expect(order?.box_office_tender).toBe('COMP');
    expect(Number(order?.total_gross)).toBe(0);
    expect(order?.status).toBe('COMPLETED');
  });

  test('comps are unavailable when the box office disallows discounts', async ({ page, api, account, publicApi }) => {
    const event = await createLiveEventWithPaidTicket(api, account.organizerId, 25);
    const boxOffice = await api.createBoxOffice(event.eventId, { name: uniqueShort('Strict door'), allow_discounts: false });

    const door = new BoxOfficePage(page);
    await door.goto(boxOffice.short_id);
    await door.startSession('Sam', boxOffice.pin);
    await door.addProduct(event.priceId, 1);
    await door.charge();
    await expect(page.getByTestId('box-office-tender-cash')).toBeVisible();
    await expect(page.getByTestId('box-office-tender-comp')).toHaveCount(0);

    const token = await startBoxOfficeSession(publicApi, boxOffice.short_id, { pin: boxOffice.pin, operatorName: 'Sam' });
    const order = await createBoxOfficeOrder(publicApi, boxOffice.short_id, token, [
      { product_id: event.productId, product_price_id: event.priceId, quantity: 1 },
    ]);
    const response = await publicApi.post(`public/box-offices/${boxOffice.short_id}/orders/${order.short_id}/tender`, {
      headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-Box-Office-Session': token },
      data: { tender: 'COMP' },
    });
    expect(response.status()).toBe(409);
  });

  test('a free ticket still completes at a box office that disallows discounts', async ({ page, api, account }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    const boxOffice = await api.createBoxOffice(event.eventId, { name: uniqueShort('Strict door'), allow_discounts: false });

    const door = new BoxOfficePage(page);
    await door.goto(boxOffice.short_id);
    await door.startSession('Sam', boxOffice.pin);
    await door.addProduct(event.priceId, 1);
    await door.charge();
    await door.completeFreeSale();

    await expect(door.saleCompleteHeading()).toBeVisible();
    expect((await doorOrder(api, event.eventId, boxOffice.id))?.box_office_tender).toBe('FREE');
  });

  test('backing out of a sale abandons the order and frees the stock', async ({ page, api, account, publicApi }) => {
    const event = await createLiveEventWithPaidTicket(api, account.organizerId, 25);
    const boxOffice = await defaultBoxOffice(api, event.eventId);

    const door = new BoxOfficePage(page);
    await door.goto(boxOffice.short_id);
    await door.startSession('Sam', boxOffice.pin);
    await door.addProduct(event.priceId, 1);
    await door.charge();
    await expect(page.getByTestId('box-office-tender-cash')).toBeVisible();

    const token = await page.evaluate(
      (shortId) => JSON.parse(localStorage.getItem(`boxOfficeSession:${shortId}`) ?? '{}').token as string,
      boxOffice.short_id,
    );
    const headers = { 'X-Box-Office-Session': token };
    const listed = (await (await publicApi.get(`public/box-offices/${boxOffice.short_id}/orders`, { headers })).json()).data as {
      short_id: string;
      public_id: string;
      status: string;
    }[];
    expect(listed).toHaveLength(1);
    expect(listed[0].status).toBe('RESERVED');

    await page.getByRole('button', { name: 'Back to sale' }).click();
    await expect(page.getByTestId('box-office-tender-cash')).toHaveCount(0);

    await expect.poll(async () => {
      const response = await publicApi.get(`public/box-offices/${boxOffice.short_id}/orders/${listed[0].short_id}`, { headers });
      return (await response.json()).data.status as string;
    }).toBe('ABANDONED');

    await door.openOrdersTab();
    await expect(page.getByTestId('box-office-orders-search')).toBeVisible();
    await expect(door.orderRow(listed[0].public_id)).toHaveCount(0);
  });

  test('a sale in progress survives a reload of the door', async ({ page, api, account }) => {
    const event = await createLiveEventWithPaidTicket(api, account.organizerId, 25);
    const boxOffice = await defaultBoxOffice(api, event.eventId);

    const door = new BoxOfficePage(page);
    await door.goto(boxOffice.short_id);
    await door.startSession('Sam', boxOffice.pin);
    await door.addProduct(event.priceId, 1);
    await door.charge();
    await expect(page.getByTestId('box-office-tender-cash')).toBeVisible();

    await page.reload();
    await expect(page.getByTestId('box-office-tender-cash')).toBeVisible();
    await door.payCash();
    await expect(door.saleCompleteHeading()).toBeVisible();

    const orders = (await api.listOrders(event.eventId)).filter((order) => order.box_office_id === boxOffice.id);
    expect(orders.map((order) => order.status)).toEqual(['COMPLETED']);
  });

  test('a sale stays open when backing out cannot reach the server', async ({ page, api, account, publicApi }) => {
    const event = await createLiveEventWithPaidTicket(api, account.organizerId, 25);
    const boxOffice = await defaultBoxOffice(api, event.eventId);

    const door = new BoxOfficePage(page);
    await door.goto(boxOffice.short_id);
    await door.startSession('Sam', boxOffice.pin);
    await door.addProduct(event.priceId, 1);
    await door.charge();
    await expect(page.getByTestId('box-office-tender-cash')).toBeVisible();

    const token = await page.evaluate(
      (shortId) => JSON.parse(localStorage.getItem(`boxOfficeSession:${shortId}`) ?? '{}').token as string,
      boxOffice.short_id,
    );
    const listed = (await (await publicApi.get(`public/box-offices/${boxOffice.short_id}/orders`, {
      headers: { 'X-Box-Office-Session': token },
    })).json()).data as { short_id: string }[];
    expect(listed).toHaveLength(1);

    await page.route('**/abandon', (route) => route.abort('internetdisconnected'));
    await page.getByRole('button', { name: 'Back to sale' }).click();
    await expect(page.getByText('Unable to cancel this sale. Check the connection and try again.')).toBeVisible();
    await expect(page.getByTestId('box-office-tender-cash')).toBeVisible();

    await page.unroute('**/abandon');
    await page.getByRole('button', { name: 'Back to sale' }).click();
    await expect(page.getByTestId('box-office-tender-cash')).toHaveCount(0);
    expect(await doorOrderStatus(publicApi, boxOffice.short_id, token, listed[0].short_id)).toBe('ABANDONED');
  });

  test('a sale left open on another device can be abandoned from the orders list', async ({ page, api, account, publicApi }) => {
    const event = await createLiveEventWithPaidTicket(api, account.organizerId, 25);
    const boxOffice = await defaultBoxOffice(api, event.eventId);
    const otherDevice = await startBoxOfficeSession(publicApi, boxOffice.short_id, { pin: boxOffice.pin, operatorName: 'Alex' });
    const stuckSale = await createBoxOfficeOrder(publicApi, boxOffice.short_id, otherDevice, [
      { product_id: event.productId, product_price_id: event.priceId, quantity: 1 },
    ]);

    const door = new BoxOfficePage(page);
    await door.goto(boxOffice.short_id);
    await door.startSession('Sam', boxOffice.pin);
    await door.openOrdersTab();
    await door.orderRow(stuckSale.public_id).click();
    await expect(page.getByText('This sale is still in progress')).toBeVisible();
    await page.getByTestId('box-office-abandon-sale-button').click();
    await page.getByRole('button', { name: 'Confirm' }).click();

    await expect(page.getByText('This sale was not completed')).toBeVisible();
    expect(await doorOrderStatus(publicApi, boxOffice.short_id, otherDevice, stuckSale.short_id)).toBe('ABANDONED');
  });

  test('a free ticket completes without asking for payment', async ({ page, api, account }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    const boxOffice = await defaultBoxOffice(api, event.eventId);

    const door = new BoxOfficePage(page);
    await door.goto(boxOffice.short_id);
    await door.startSession('Sam', boxOffice.pin);
    await door.addProduct(event.priceId, 1);
    await door.charge();

    await expect(page.getByText('Free order, no payment needed')).toBeVisible();
    await door.completeFreeSale();

    await expect(door.saleCompleteHeading()).toBeVisible();
    const order = await doorOrder(api, event.eventId, boxOffice.id);
    expect(order?.box_office_tender).toBe('FREE');
  });

  test('door staff void a cash sale they just took', async ({ page, api, account }) => {
    const event = await createLiveEventWithPaidTicket(api, account.organizerId, 25);
    const boxOffice = await defaultBoxOffice(api, event.eventId);

    const door = new BoxOfficePage(page);
    await door.goto(boxOffice.short_id);
    await door.startSession('Sam', boxOffice.pin);
    await door.addProduct(event.priceId, 1);
    await door.charge();
    await door.payCash();
    await expect(door.saleCompleteHeading()).toBeVisible();

    await door.voidSale();

    await expect(page.getByText('This sale was voided')).toBeVisible();
    const order = await doorOrder(api, event.eventId, boxOffice.id);
    expect(order?.status).toBe('CANCELLED');
  });
});
