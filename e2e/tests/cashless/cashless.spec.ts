import { test, expect } from '../../fixtures';
import {
  CashlessOverviewPage,
  CashlessSettingsPage,
  CashlessTransactionsPage,
  CashlessPosPage,
  CashlessTopupEntryPage,
  CashlessSalesPointPage,
  CashlessWalletPublicPage,
  CashlessWalletsPage,
} from '../../pages/cashless.page';
import { createCompletedOrder, createLiveEventWithFreeTicket } from '../../api/factory';
import { uniqueName } from '../../utils/unique';

const SALES_POINT_PIN = '4321';

const seedCashlessEvent = async (api: any, publicApi: any, organizerId: number) => {
  const event = await createLiveEventWithFreeTicket(api, organizerId);

  await api.updateCashlessSettings(event.eventId, {
    cashless_enabled: true,
    cashless_min_topup_amount: 5,
    cashless_allow_remaining_balance_refund: true,
  });

  const categories = await api.listProductCategories(event.eventId);
  const drink = await api.createProduct(event.eventId, {
    title: 'Beer',
    product_type: 'GENERAL',
    type: 'PAID',
    product_category_id: categories[0].id,
    prices: [{ price: 5 }],
  });
  const drinkWithPrices = await api.getProduct(event.eventId, drink.id);

  const order = await createCompletedOrder(publicApi, event);

  return {
    event,
    order,
    drinkId: drink.id as number,
    drinkPriceId: drinkWithPrices.prices[0].id as number,
  };
};

test.describe('cashless', () => {
  test('@smoke an organizer creates a sales point and sees it listed', async ({ authedPage, api, account, publicApi }) => {
    const { event } = await seedCashlessEvent(api, publicApi, account.organizerId);
    const salesPointName = uniqueName('Main Bar');

    const salesPoints = new CashlessSalesPointPage(authedPage);
    await salesPoints.goto(event.eventId);
    await salesPoints.create(salesPointName, 'Beer');

    await expect(salesPoints.row(salesPointName)).toBeVisible();
    await expect(salesPoints.row(salesPointName)).toContainText('Beer');
  });

  test('an organizer tops up a ticket and the attendee sees the balance', async ({
    authedPage,
    api,
    account,
    publicApi,
  }) => {
    const { event, order } = await seedCashlessEvent(api, publicApi, account.organizerId);
    const attendee = order.attendees[0];

    const wallets = new CashlessWalletsPage(authedPage);
    await wallets.goto(event.eventId);
    await wallets.topUpByTicketId(attendee.publicId, 30);

    await expect(authedPage.getByText('Balance topped up')).toBeVisible();
    await expect(wallets.row(attendee.publicId)).toContainText('$30.00');
  });

  test('staff charge a ticket at a sales point and the balance drops', async ({
    authedPage,
    page,
    api,
    account,
    publicApi,
  }) => {
    const { event, order, drinkId, drinkPriceId } = await seedCashlessEvent(api, publicApi, account.organizerId);
    const attendee = order.attendees[0];

    const salesPoint = await api.createCashlessSalesPoint(event.eventId, {
      name: uniqueName('Bar'),
      product_ids: [drinkId],
      access_pin: SALES_POINT_PIN,
    });

    const wallets = new CashlessWalletsPage(authedPage);
    await wallets.goto(event.eventId);
    await wallets.topUpByTicketId(attendee.publicId, 40);
    await expect(authedPage.getByText('Balance topped up')).toBeVisible();

    const pos = new CashlessPosPage(page);
    await pos.goto(salesPoint.short_id);
    await pos.unlock(SALES_POINT_PIN);

    await pos.lookUpTicket(attendee.publicId);
    await expect(page.getByText(attendee.publicId)).toBeVisible();

    await pos.addProduct(drinkPriceId);
    await pos.addProduct(drinkPriceId);
    await expect(pos.chargeButton()).toContainText('$10.00');

    await pos.chargeButton().click();
    await expect(page.getByText('$30.00 left')).toBeVisible();

    await page.getByRole('button', { name: 'History' }).click();
    await expect(page.getByText('2 × Beer')).toBeVisible();

    await page.reload();
    await page.getByRole('button', { name: 'History' }).click();
    await expect(page.getByText('2 × Beer')).toBeVisible();
    await expect(page.getByText(attendee.publicId)).toBeVisible();

    const publicWallet = new CashlessWalletPublicPage(page);
    await publicWallet.goto(event.eventId, attendee.shortId);
    await expect(publicWallet.balance()).toBeVisible();
    await expect(page.getByText('2 × Beer')).toBeVisible();
  });

  test('the Balances menu item is only highlighted on the balances screen', async ({ authedPage, api, account, publicApi }) => {
    const { event } = await seedCashlessEvent(api, publicApi, account.organizerId);

    const balances = authedPage.getByRole('link', { name: 'Balances' });

    await authedPage.goto(`/manage/event/${event.eventId}/cashless`);
    await expect(balances).toHaveAttribute('aria-current', 'page');

    await authedPage.goto(`/manage/event/${event.eventId}/cashless/sales-points`);
    await expect(balances).not.toHaveAttribute('aria-current', 'page');
    await expect(authedPage.getByRole('link', { name: 'Sales Points' })).toHaveAttribute('aria-current', 'page');
  });

  test('a top-up-only sales point shows the amount to key into the card terminal', async ({ page, api, account, publicApi }) => {
    const { event, order } = await seedCashlessEvent(api, publicApi, account.organizerId);
    const attendee = order.attendees[0];

    const { id: accountId } = await api.getAccount();
    const fee = await api.createTaxOrFee(accountId, {
      name: uniqueName('Card handling'),
      calculation_type: 'FIXED',
      type: 'FEE',
      rate: 0.35,
      is_active: true,
      is_default: false,
    });
    await api.updateCashlessSettings(event.eventId, {
      cashless_enabled: true,
      cashless_min_topup_amount: 5,
      cashless_allow_remaining_balance_refund: false,
      cashless_topup_tax_and_fee_ids: [fee.id],
    });

    const salesPoint = await api.createCashlessSalesPoint(event.eventId, {
      name: uniqueName('Top-up desk'),
      product_ids: [],
    });

    const pos = new CashlessPosPage(page);
    await pos.goto(salesPoint.short_id);

    await expect(page.getByRole('button', { name: 'Charge' })).toHaveCount(0);

    await pos.lookUpTicket(attendee.publicId, 'cashless-pos-topup-ticket-input');
    await page.getByRole('combobox', { name: 'How did they pay?' }).click();
    await page.getByRole('option', { name: 'Card terminal' }).click();
    await page.getByTestId('cashless-pos-topup-amount-input').fill('20');

    await expect(page.getByTestId('cashless-pos-terminal-total')).toHaveText('$20.35');
  });

  test('a sales point refuses the wrong PIN', async ({ page, api, account, publicApi }) => {
    const { event, drinkId } = await seedCashlessEvent(api, publicApi, account.organizerId);

    const salesPoint = await api.createCashlessSalesPoint(event.eventId, {
      name: uniqueName('Bar'),
      product_ids: [drinkId],
      access_pin: SALES_POINT_PIN,
    });

    const pos = new CashlessPosPage(page);
    await pos.goto(salesPoint.short_id);
    await pos.unlock('0000');

    await expect(page.getByText('That PIN is not correct.')).toBeVisible();
  });

  test('a visitor reaches their balance from the event page by typing their ticket ID', async ({
    page,
    api,
    account,
    publicApi,
  }) => {
    const { event, order } = await seedCashlessEvent(api, publicApi, account.organizerId);
    const attendee = order.attendees[0];

    const entry = new CashlessTopupEntryPage(page);
    await entry.gotoFromEventPage(event.eventId, event.slug);

    await entry.submitTicketId('not-a-ticket');
    await expect(page.getByText("That doesn't look like a ticket ID")).toBeVisible();

    await entry.submitTicketId(attendee.publicId.toLowerCase());
    await expect(page).toHaveURL(new RegExp(`/cashless/${event.eventId}/${attendee.publicId}$`));
    await expect(new CashlessWalletPublicPage(page).balance()).toBeVisible();
  });

  test('a visitor tops up online and reaches a checkout already filled from the ticket', async ({
    page,
    api,
    account,
    publicApi,
  }) => {
    const { event, order } = await seedCashlessEvent(api, publicApi, account.organizerId);
    const attendee = order.attendees[0];

    const wallet = new CashlessWalletPublicPage(page);
    await wallet.goto(event.eventId, attendee.publicId);
    await page.getByTestId('cashless-topup-button').click();

    await expect(page).toHaveURL(new RegExp(`/checkout/${event.eventId}/o_[^/]+/details`));
    await expect(page.getByRole('textbox', { name: 'First Name', exact: true })).toHaveValue(order.buyerFirstName);
    await expect(page.getByRole('textbox', { name: 'Email Address', exact: true })).toHaveValue(order.buyerEmail);
    await page.getByRole('button', { name: 'Continue to Payment' }).click();

    await expect(page).toHaveURL(/\/(payment|summary)$/);
    await expect(page.getByText(/already been processed/i)).toHaveCount(0);
  });

  test('the overview totals the balances and closing moves what is left into sales', async ({
    authedPage,
    api,
    account,
    publicApi,
  }) => {
    const { event, order } = await seedCashlessEvent(api, publicApi, account.organizerId);
    const attendee = order.attendees[0];

    const wallets = new CashlessWalletsPage(authedPage);
    await wallets.goto(event.eventId);
    await wallets.topUpByTicketId(attendee.publicId, 30);
    await expect(authedPage.getByText('Balance topped up')).toBeVisible();

    const overview = new CashlessOverviewPage(authedPage);
    await overview.goto(event.eventId);
    await expect(overview.kpiValue('outstanding')).toHaveText('$30.00');
    await expect(overview.kpiValue('topped-up')).toHaveText('$30.00');
    await expect(overview.kpiValue('spent')).toHaveText('$0.00');

    const settings = new CashlessSettingsPage(authedPage);
    await settings.goto(event.eventId);
    await expect(settings.dangerZone()).toBeVisible();
    await settings.openCloseModal();
    await expect(authedPage.getByTestId('cashless-close-refund-warning')).toBeVisible();
    await authedPage.getByTestId('cashless-close-confirm-checkbox').check();
    await expect(settings.closeSubmitButton()).toBeDisabled();
    await authedPage.keyboard.press('Escape');

    await api.updateCashlessSettings(event.eventId, {
      cashless_enabled: true,
      cashless_min_topup_amount: 5,
      cashless_allow_remaining_balance_refund: false,
    });
    await authedPage.reload();

    await settings.openCloseModal();
    await expect(authedPage.getByTestId('cashless-close-amount')).toHaveText('$30.00');
    await settings.confirmAndClose();

    await expect(authedPage.getByTestId('cashless-close-button')).toHaveCount(0);

    await overview.goto(event.eventId);
    await expect(authedPage.getByTestId('cashless-closed-notice')).toBeVisible();
    await expect(overview.kpiValue('outstanding')).toHaveText('$0.00');
    await expect(overview.kpiValue('closed')).toHaveText('$30.00');

    await wallets.goto(event.eventId);
    await expect(wallets.row(attendee.publicId)).toContainText('CLOSED');
  });

  test('a closed balance can no longer be topped up online', async ({ page, authedPage, api, account, publicApi }) => {
    const { event, order } = await seedCashlessEvent(api, publicApi, account.organizerId);
    const attendee = order.attendees[0];

    await api.updateCashlessSettings(event.eventId, {
      cashless_enabled: true,
      cashless_min_topup_amount: 5,
      cashless_allow_remaining_balance_refund: false,
    });
    const settings = new CashlessSettingsPage(authedPage);
    await settings.goto(event.eventId);
    await settings.openCloseModal();
    await settings.confirmAndClose();
    await expect(authedPage.getByTestId('cashless-close-button')).toHaveCount(0);

    const wallet = new CashlessWalletPublicPage(page);
    await wallet.goto(event.eventId, attendee.publicId);
    await expect(page.getByText('Cashless is closed for this event')).toBeVisible();
    await expect(page.getByTestId('cashless-topup-button')).toHaveCount(0);
  });

  test('an organizer jumps from a balance or a sales point to its transactions and filters them', async ({
    authedPage,
    api,
    account,
    publicApi,
  }) => {
    const { event, order, drinkId } = await seedCashlessEvent(api, publicApi, account.organizerId);
    const attendee = order.attendees[0];
    const salesPointName = uniqueName('Bar');
    await api.createCashlessSalesPoint(event.eventId, {
      name: salesPointName,
      product_ids: [drinkId],
      access_pin: SALES_POINT_PIN,
    });

    const wallets = new CashlessWalletsPage(authedPage);
    await wallets.goto(event.eventId);
    await wallets.topUpByTicketId(attendee.publicId, 30);
    await expect(authedPage.getByText('Balance topped up')).toBeVisible();

    await wallets.row(attendee.publicId).getByRole('button').click();
    await authedPage.getByTestId('cashless-wallet-transactions-menu-item').click();

    const transactions = new CashlessTransactionsPage(authedPage);
    await expect(authedPage).toHaveURL(/cashless\/transactions\?.*cashless_wallet_id/);
    await expect(authedPage.getByTestId('cashless-transactions-wallet-pill')).toBeVisible();
    await expect(transactions.row(attendee.publicId)).toBeVisible();

    await transactions.goto(event.eventId);
    await transactions.filterBy('Type', 'Purchase');
    await expect(authedPage.getByText('No cashless activity yet')).toBeVisible();
    await transactions.resetFilters();
    await expect(transactions.row(attendee.publicId)).toBeVisible();
    await transactions.filterBy('Type', 'Staff top-up');
    await expect(transactions.row(attendee.publicId)).toBeVisible();

    await transactions.resetFilters();
    await expect(authedPage.getByRole('button', { name: 'Filters', exact: true })).toBeVisible();
    await transactions.search('nobody-with-that-name');
    await expect(authedPage.getByText('No search results.')).toBeVisible();
    await transactions.search(attendee.publicId);
    await expect(transactions.row(attendee.publicId)).toBeVisible();

    const salesPoints = new CashlessSalesPointPage(authedPage);
    await salesPoints.goto(event.eventId);
    await salesPoints.row(salesPointName).getByRole('button').click();
    await authedPage.getByTestId('cashless-sales-point-transactions-menu-item').click();
    await expect(authedPage).toHaveURL(/cashless\/transactions\?.*cashless_sales_point_id/);
    await expect(authedPage.getByRole('button', { name: 'Filters (1)' })).toBeVisible();
    await authedPage.getByRole('button', { name: 'Filters (1)' }).click();
    await expect(authedPage.getByRole('dialog').getByText(salesPointName)).toBeVisible();
  });
});
