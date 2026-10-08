import {test, expect} from '../../fixtures';
import {createSeatedEvent, createSeatedOrder} from '../../api/factory';
import {SeatMapDesignerPage} from '../../pages/seat-map-designer.page';
import {SeatingSettingsPage} from '../../pages/seating-settings.page';

const SOLD_SEAT = 'e2.0.0';

test.describe('editing an event seat map that is already on sale', () => {
  test.beforeEach(async ({adminApi, account}) => {
    await adminApi.setAccountFeatureFlag(await adminApi.findAccountIdByEmail(account.email), 'seating', true);
  });

  test('renaming a sold seat asks for confirmation and the new name reaches the ticket', async ({authedPage: page, api, publicApi, account}) => {
    const event = await createSeatedEvent(api, account.organizerId);
    const order = await createSeatedOrder(publicApi, event, [SOLD_SEAT]);

    const seating = new SeatingSettingsPage(page);
    await seating.goto(event.eventId);
    await seating.openDesigner();
    const designer = new SeatMapDesignerPage(page);
    await designer.chooseTool('seats');
    await designer.seat(SOLD_SEAT).click();
    await expect(page.getByRole('heading', {name: 'Seat A-1'})).toBeVisible();
    await designer.rowLabelInput().fill('AA');
    await expect(page.getByRole('heading', {name: 'Seat AA-1'})).toBeVisible();
    await designer.save();

    const confirmation = page.getByRole('dialog');
    await expect(confirmation).toContainText('1 seats that are already sold or held will be renamed on their tickets');
    await confirmation.getByRole('button', {name: 'Rename and save'}).click();
    await expect(page.getByText('Seat map saved')).toBeVisible();

    await page.goto(`/manage/event/${event.eventId}/attendees`);
    await expect(page.getByRole('row').filter({hasText: order.buyerEmail})).toContainText('Stalls · AA-1');
  });

  test('a save from a tab that has fallen behind is refused instead of overwriting', async ({authedPage: page, api, account}) => {
    const event = await createSeatedEvent(api, account.organizerId);
    const staleTab = await page.context().newPage();
    try {
      const designer = new SeatMapDesignerPage(page);
      const staleDesigner = new SeatMapDesignerPage(staleTab);
      await designer.gotoEventDesigner(event.eventId);
      await staleDesigner.gotoEventDesigner(event.eventId);

      await designer.areaNameInput().fill('Main stalls');
      await designer.save();
      await expect(page.getByText('Seat map saved')).toBeVisible();

      await staleDesigner.areaNameInput().fill('Front stalls');
      await staleDesigner.save();
      const conflict = staleTab.getByRole('dialog', {name: /This seat map was changed somewhere else/});
      await expect(conflict).toBeVisible();
      await conflict.getByRole('button', {name: 'Load latest version'}).click();

      await expect(staleDesigner.areaNameInput()).toHaveValue('Main stalls');
      expect((await api.getEventSeatMap(event.eventId)).layout.areas[0].name).toBe('Main stalls');
    } finally {
      await staleTab.close();
    }
  });
});
