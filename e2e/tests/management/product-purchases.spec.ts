import { test, expect } from '../../fixtures';
import { ProductPurchasesPage, readCsv } from '../../pages/product-purchases.page';
import {
  createAwaitingOfflineOrder,
  createCompletedOrder,
  createCompletedPaidOrder,
  createLiveEventWithFreeTicket,
  createLiveEventWithPaidTicket,
  type SeededEvent,
} from '../../api/factory';
import type { ApiClient } from '../../api/api-client';
import { uniqueEmail, uniqueShort } from '../../utils/unique';

async function cancelOrder(api: ApiClient, eventId: number, orderShortId: string): Promise<void> {
  await api.cancelOrder(eventId, await api.findOrderIdByShortId(eventId, orderShortId));
}

async function addSecondTicket(api: ApiClient, event: SeededEvent): Promise<SeededEvent> {
  const [category] = await api.listProductCategories(event.eventId);
  const created = await api.createProduct(event.eventId, {
    title: uniqueShort('Balcony'),
    product_type: 'TICKET',
    type: 'FREE',
    product_category_id: category.id,
    prices: [{ price: 0 }],
  });
  const product = await api.getProduct(event.eventId, created.id);
  return { ...event, productId: created.id, productTitle: created.title, priceId: product.prices![0].id! };
}

test.describe('product purchases', () => {
  test('an organizer sees who bought a product, without cancelled orders counted as sold', { tag: '@smoke' }, async ({ authedPage, api, account, publicApi }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    const keptOrder = await createCompletedOrder(publicApi, event, { buyerEmail: uniqueEmail(), quantity: 2 });
    const cancelledOrder = await createCompletedOrder(publicApi, event, { buyerEmail: uniqueEmail() });
    await cancelOrder(api, event.eventId, cancelledOrder.orderShortId);

    const purchases = new ProductPurchasesPage(authedPage);
    await purchases.open(event.eventId);

    await expect(purchases.stat('Sold')).toHaveText('2');
    await expect(purchases.stat('Cancelled')).toHaveText('1');
    await expect(purchases.row(keptOrder.buyerEmail)).toContainText('Qty 2');
    await expect(purchases.row(cancelledOrder.buyerEmail)).toHaveCount(0);

    await purchases.showView('Cancelled');
    await expect(purchases.row(cancelledOrder.buyerEmail)).toBeVisible();
    await expect(purchases.row(keptOrder.buyerEmail)).toHaveCount(0);
  });

  test('offline payment orders are awaiting payment, not sold', async ({ authedPage, api, account, publicApi }) => {
    const event = await createLiveEventWithPaidTicket(api, account.organizerId, 25);
    const paidOrder = await createCompletedPaidOrder(api, publicApi, event, { buyerEmail: uniqueEmail() });
    const offlineOrder = await createAwaitingOfflineOrder(api, publicApi, event, { buyerEmail: uniqueEmail() });

    const purchases = new ProductPurchasesPage(authedPage);
    await purchases.open(event.eventId);

    await expect(purchases.stat('Sold')).toHaveText('1');
    await expect(purchases.stat('Awaiting payment')).toHaveText('1');
    await expect(purchases.stat('Gross sales')).toHaveText(`$${paidOrder.totalGross.toFixed(2)}`);
    await expect(purchases.row(offlineOrder.buyerEmail)).toContainText('Awaiting payment');

    await purchases.showView('Awaiting payment');
    await expect(purchases.row(offlineOrder.buyerEmail)).toBeVisible();
    await expect(purchases.row(paidOrder.buyerEmail)).toHaveCount(0);
  });

  test('cancelling an order opened from the purchases list updates the totals', async ({ authedPage, api, account, publicApi }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    const keptOrder = await createCompletedOrder(publicApi, event, { buyerEmail: uniqueEmail() });
    const cancelledOrder = await createCompletedOrder(publicApi, event, { buyerEmail: uniqueEmail(), quantity: 2 });

    const purchases = new ProductPurchasesPage(authedPage);
    await purchases.open(event.eventId);
    await expect(purchases.stat('Sold')).toHaveText('3');

    await purchases.cancelOrderFromRow(cancelledOrder.buyerEmail);

    await expect(purchases.stat('Sold')).toHaveText('1');
    await expect(purchases.stat('Cancelled')).toHaveText('2');
    await expect(purchases.row(cancelledOrder.buyerEmail)).toHaveCount(0);
    await expect(purchases.row(keptOrder.buyerEmail)).toBeVisible();
  });

  test('an organizer searches purchases by buyer email', async ({ authedPage, api, account, publicApi }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    const ada = await createCompletedOrder(publicApi, event, { buyerEmail: uniqueEmail('ada') });
    const grace = await createCompletedOrder(publicApi, event, { buyerEmail: uniqueEmail('grace') });

    const purchases = new ProductPurchasesPage(authedPage);
    await purchases.open(event.eventId);
    await expect(purchases.rows()).toHaveCount(2);

    await purchases.search(grace.buyerEmail);

    await expect(purchases.row(grace.buyerEmail)).toBeVisible();
    await expect(purchases.row(ada.buyerEmail)).toHaveCount(0);
  });

  test('the drawer export contains the purchases in the selected view', async ({ authedPage, api, account, publicApi }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    const keptOrder = await createCompletedOrder(publicApi, event, { buyerEmail: uniqueEmail(), quantity: 2 });
    const cancelledOrder = await createCompletedOrder(publicApi, event, { buyerEmail: uniqueEmail() });
    await cancelOrder(api, event.eventId, cancelledOrder.orderShortId);

    const purchases = new ProductPurchasesPage(authedPage);
    await purchases.open(event.eventId);

    const activeExport = await purchases.exportFromDrawer();
    expect(activeExport.suggestedFilename()).toBe('product-purchases.csv');
    const [header, ...activeRows] = await readCsv(activeExport);
    const emailColumn = header.indexOf('Email');
    const soldColumn = header.indexOf('Quantity Sold');
    expect(activeRows.map((row) => row[emailColumn])).toEqual([keptOrder.buyerEmail]);
    expect(activeRows[0][soldColumn]).toBe('2');

    await purchases.showView('Cancelled');
    const [, ...cancelledRows] = await readCsv(await purchases.exportFromDrawer());
    expect(cancelledRows.map((row) => row[emailColumn])).toEqual([cancelledOrder.buyerEmail]);
  });

  test('exporting active purchases covers every product and leaves out cancelled orders', async ({ authedPage, api, account, publicApi }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    const balcony = await addSecondTicket(api, event);
    const stallsOrder = await createCompletedOrder(publicApi, event, { buyerEmail: uniqueEmail() });
    const balconyOrder = await createCompletedOrder(publicApi, balcony, { buyerEmail: uniqueEmail() });
    const cancelledOrder = await createCompletedOrder(publicApi, balcony, { buyerEmail: uniqueEmail() });
    await cancelOrder(api, event.eventId, cancelledOrder.orderShortId);

    const purchases = new ProductPurchasesPage(authedPage);
    await purchases.gotoProducts(event.eventId);

    const [header, ...rows] = await readCsv(await purchases.exportActivePurchasesForAllProducts());
    const emailColumn = header.indexOf('Email');
    const productColumn = header.indexOf('Product');

    expect(rows.map((row) => [row[emailColumn], row[productColumn]])).toEqual(expect.arrayContaining([
      [stallsOrder.buyerEmail, event.productTitle],
      [balconyOrder.buyerEmail, balcony.productTitle],
    ]));
    expect(rows).toHaveLength(2);
  });
});
