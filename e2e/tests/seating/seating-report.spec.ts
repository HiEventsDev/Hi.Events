import {test, expect} from '../../fixtures';
import {createCompletedPaidOrder, createSeatedEvent} from '../../api/factory';

const BASE_PRICE = 30;
const PREMIUM_ADJUSTMENT_MINOR = 1500;

test.describe('seating sales report', () => {
  test.beforeEach(async ({adminApi, account}) => {
    await adminApi.setAccountFeatureFlag(await adminApi.findAccountIdByEmail(account.email), 'seating', true);
  });

  test('sold seats and revenue are reported per price band', async ({authedPage: page, api, publicApi, account}) => {
    const event = await createSeatedEvent(api, account.organizerId, {price: BASE_PRICE});
    await api.linkSeatMapBands(event.eventId, [
      {band_key: 'b_premium', products: [{product_id: event.productId, price_adjustment: PREMIUM_ADJUSTMENT_MINOR}]},
      {band_key: 'b_standard', products: [{product_id: event.productId, price_adjustment: 0}]},
    ]);
    await api.updateEventSettings(event.eventId, {pass_platform_fee_to_buyer: false});

    const seatOptions = {eventOccurrenceId: event.occurrenceId};
    const premium = await createCompletedPaidOrder(api, publicApi, event, {...seatOptions, seatUids: ['e2.0.0']});
    const standard = await createCompletedPaidOrder(api, publicApi, event, {...seatOptions, seatUids: ['e3.0.0']});
    expect(premium.totalGross).toBe(45);
    expect(standard.totalGross).toBe(30);

    await page.goto(`/manage/event/${event.eventId}/reports`);
    await page.getByRole('link', {name: /Seating Sales/}).click();
    await expect(page.getByRole('heading', {name: 'Seating Sales'})).toBeVisible();

    const bandTotal = (band: string) => page.getByRole('row').filter({hasText: band}).filter({hasText: 'All areas'});
    await expect(bandTotal('Premium').getByRole('cell').nth(3)).toHaveText('1');
    await expect(bandTotal('Premium')).toContainText('$45.00');
    await expect(bandTotal('Standard').getByRole('cell').nth(3)).toHaveText('1');
    await expect(bandTotal('Standard')).toContainText('$30.00');
    await expect(bandTotal('Value').getByRole('cell').nth(3)).toHaveText('0');
  });
});
