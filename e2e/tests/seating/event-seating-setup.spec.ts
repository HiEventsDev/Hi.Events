import {test, expect} from '../../fixtures';
import {attachFixtureSeatMap, createCompletedOrder, createDraftEvent, createLiveEventWithProduct} from '../../api/factory';
import {SeatMapDesignerPage} from '../../pages/seat-map-designer.page';
import {SeatingSettingsPage} from '../../pages/seating-settings.page';

test.describe('reserved seating setup', () => {
  test.beforeEach(async ({adminApi, account}) => {
    await adminApi.setAccountFeatureFlag(await adminApi.findAccountIdByEmail(account.email), 'seating', true);
  });

  test('an organizer creates a seat map from a template and sells an event by seat', {tag: '@smoke'}, async ({authedPage: page, api, account}) => {
    const event = await createDraftEvent(api, account.organizerId);
    const [category] = await api.listProductCategories(event.eventId);
    await api.createProduct(event.eventId, {
      title: 'Stalls Adult',
      product_type: 'TICKET',
      type: 'FREE',
      product_category_id: category.id,
      prices: [{price: 0}],
    });
    const seatMapName = `Studio ${Date.now()}`;

    const designer = new SeatMapDesignerPage(page);
    await designer.createSeatMap(account.organizerId, seatMapName, 'conference');
    await designer.expectSeatCount(182);
    await expect(designer.nameInput()).toHaveValue(seatMapName);

    const seating = new SeatingSettingsPage(page);
    await seating.goto(event.eventId);
    await seating.attach(seatMapName);

    await expect(page.getByText('Tickets for each band')).toBeVisible();
    await seating.toggleBandProducts('b_premium', ['Stalls Adult']);
    await seating.saveBands();
    await expect(page.getByText('Tickets linked')).toBeVisible();

    await page.goto(`/manage/organizer/${account.organizerId}/events`);
    await expect(page.getByText(event.title).first()).toBeVisible();
    await expect(page.getByText('Seated').first()).toBeVisible();

    await api.publishEvent(event.eventId);
    await page.goto(`/event/${event.eventId}/${event.slug}`);
    await expect(page.getByTestId('seated-products-section')).toBeVisible();
    await expect(page.getByTestId('seated-products-section').getByText('Premium')).toBeVisible();
    await expect(page.getByTestId('choose-seats-button')).toBeEnabled();
  });

  test('a ticket that has already sold without seats cannot be linked to seats', async ({authedPage: page, api, publicApi, account}) => {
    const event = await createLiveEventWithProduct(api, {organizerId: account.organizerId, productTitle: 'Walk-in'});
    await createCompletedOrder(publicApi, event);
    await attachFixtureSeatMap(api, account.organizerId, event.eventId, []);

    const seating = new SeatingSettingsPage(page);
    await seating.goto(event.eventId);
    await seating.toggleBandProducts('b_premium', ['Walk-in']);
    await seating.saveBands();

    await expect(page.getByText(/already have orders without seats.*Walk-in/)).toBeVisible();
    expect((await api.getEventSeatMap(event.eventId)).band_products).toEqual([]);
  });
});
