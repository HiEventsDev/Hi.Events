import { test, expect } from '../../fixtures';
import { BoxOfficePage } from '../../pages/box-office.page';
import { createLiveEventWithPaidTicket, createLiveEventWithProduct, defaultBoxOffice } from '../../api/factory';
import { uniqueShort } from '../../utils/unique';

test.describe('box office cart', () => {
  test('a manual discount comes off the total the customer pays', async ({ page, api, account }) => {
    const event = await createLiveEventWithPaidTicket(api, account.organizerId, 25);
    const boxOffice = await defaultBoxOffice(api, event.eventId);

    const door = new BoxOfficePage(page);
    await door.goto(boxOffice.short_id);
    await door.startSession('Sam', boxOffice.pin);
    await door.addProduct(event.priceId, 2);

    await door.applyPercentDiscount(50);
    await expect(page.getByText('Discount (50%)')).toBeVisible();
    await expect(page.getByTestId('box-office-charge-button').first()).toContainText('25.00');

    await door.charge();
    await door.payCash();

    await expect(door.saleCompleteHeading()).toBeVisible();
    const orders = await api.listOrders(event.eventId);
    expect(orders.find((order) => order.box_office_id === boxOffice.id)?.total_gross).toBe(25);
  });

  test('an operator changes a line price when the box office allows overrides', async ({ page, api, account }) => {
    const event = await createLiveEventWithPaidTicket(api, account.organizerId, 25);
    const boxOffice = await api.createBoxOffice(event.eventId, {
      name: uniqueShort('Override door'),
      allow_price_override: true,
    });

    const door = new BoxOfficePage(page);
    await door.goto(boxOffice.short_id);
    await door.startSession('Sam', boxOffice.pin);
    await door.addProduct(event.priceId, 1);

    await door.overrideLinePrice(event.priceId, 5);
    await expect(page.getByText('Price changed')).toBeVisible();
    await expect(page.getByTestId('box-office-charge-button').first()).toContainText('5.00');

    await door.charge();
    await door.payCash();

    await expect(door.saleCompleteHeading()).toBeVisible();
    const orders = await api.listOrders(event.eventId);
    expect(orders.find((order) => order.box_office_id === boxOffice.id)?.total_gross).toBe(5);
  });

  test('a changed price on a taxed ticket quotes the total the customer is charged', async ({ page, api, account }) => {
    const { id: accountId } = await api.getAccount();
    const tax = await api.createTaxOrFee(accountId, {
      name: uniqueShort('VAT'),
      calculation_type: 'PERCENTAGE',
      type: 'TAX',
      rate: 20,
      is_active: true,
      is_default: false,
    });
    const event = await createLiveEventWithProduct(api, { organizerId: account.organizerId, price: 25, taxIds: [tax.id] });
    const boxOffice = await api.createBoxOffice(event.eventId, {
      name: uniqueShort('Taxed door'),
      allow_price_override: true,
    });

    const door = new BoxOfficePage(page);
    await door.goto(boxOffice.short_id);
    await door.startSession('Sam', boxOffice.pin);
    await door.addProduct(event.priceId, 1);
    await expect(door.chargeButton()).toContainText('30.00');

    await door.overrideLinePrice(event.priceId, 10);
    await expect(door.chargeButton()).toContainText('12.00');

    await door.applyPercentDiscount(50);
    await expect(door.chargeButton()).toContainText('12.00');

    await door.charge();
    await expect(page.getByText('Total to pay')).toBeVisible();
    await door.payCash();

    await expect(door.saleCompleteHeading()).toBeVisible();
    const orders = await api.listOrders(event.eventId);
    expect(orders.find((order) => order.box_office_id === boxOffice.id)?.total_gross).toBe(12);
  });

  test('discounts and price overrides stay hidden when the box office disallows them', async ({ page, api, account }) => {
    const event = await createLiveEventWithPaidTicket(api, account.organizerId, 25);
    const boxOffice = await api.createBoxOffice(event.eventId, {
      name: uniqueShort('Locked door'),
      allow_discounts: false,
      allow_price_override: false,
    });

    const door = new BoxOfficePage(page);
    await door.goto(boxOffice.short_id);
    await door.startSession('Sam', boxOffice.pin);
    await door.addProduct(event.priceId, 1);

    await expect(door.discountButton()).toHaveCount(0);

    await door.cartLine(event.priceId).click();
    await expect(page.getByText('Change price')).toHaveCount(0);
  });

  test('a scoped box office only offers the products it was given', async ({ page, api, account }) => {
    const event = await createLiveEventWithPaidTicket(api, account.organizerId, 25);
    const categories = await api.listProductCategories(event.eventId);
    const otherProduct = await api.createProduct(event.eventId, {
      title: uniqueShort('Balcony'),
      product_type: 'TICKET',
      type: 'PAID',
      product_category_id: categories[0].id,
      prices: [{ price: 40 }],
    });
    const boxOffice = await api.createBoxOffice(event.eventId, {
      name: uniqueShort('Scoped door'),
      product_ids: [event.productId],
    });

    const door = new BoxOfficePage(page);
    await door.goto(boxOffice.short_id);
    await door.startSession('Sam', boxOffice.pin);

    await expect(page.getByText(event.productTitle)).toBeVisible();
    await expect(page.getByText(otherProduct.title)).toHaveCount(0);
  });

  test('going back to the sale and charging again completes with the same cart', async ({ page, api, account }) => {
    const event = await createLiveEventWithPaidTicket(api, account.organizerId, 25);
    const boxOffice = await defaultBoxOffice(api, event.eventId);

    const door = new BoxOfficePage(page);
    await door.goto(boxOffice.short_id);
    await door.startSession('Sam', boxOffice.pin);
    await door.addProduct(event.priceId, 2);

    await door.charge();
    await expect(page.getByTestId('box-office-tender-cash')).toBeVisible();
    await page.getByRole('button', { name: 'Back to sale' }).click();
    await expect(door.chargeButton()).toContainText('50.00');

    await door.charge();
    await expect(page.getByTestId('box-office-tender-cash')).toBeVisible();
    await door.payCash();
    await expect(door.saleCompleteHeading()).toBeVisible();

    const orders = (await api.listOrders(event.eventId)).filter((order) => order.box_office_id === boxOffice.id);
    const completed = orders.filter((order) => order.status === 'COMPLETED');
    expect(completed).toHaveLength(1);
    expect(completed[0].total_gross).toBe(50);
  });
});
