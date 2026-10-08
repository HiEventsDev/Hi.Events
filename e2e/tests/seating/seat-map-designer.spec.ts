import {test, expect} from '../../fixtures';
import {SeatMapDesignerPage} from '../../pages/seat-map-designer.page';
import {createFixtureSeatMap, createSeatedEvent} from '../../api/factory';

interface SavedSeat {
  uid: string;
  label: string;
  band: string;
}

interface SavedElement {
  id: string;
  type: string;
  text?: string;
  seats?: SavedSeat[];
}

test.describe('seat map designer', () => {
  test.beforeEach(async ({adminApi, account}) => {
    await adminApi.setAccountFeatureFlag(await adminApi.findAccountIdByEmail(account.email), 'seating', true);
  });

  test('an organizer draws rows, drags them, marks an accessible seat, places a table and saves', async ({authedPage: page, api, account}) => {
    const designer = new SeatMapDesignerPage(page);
    const seatMapId = await designer.createSeatMap(account.organizerId, `Blank room ${Date.now()}`, 'empty');
    await designer.expectSeatCount(0);

    await designer.chooseTool('rows');
    await designer.drawOnCanvas({x: 200, y: 420}, {x: 520, y: 560});
    await expect.poll(() => designer.readSeatCount()).toBeGreaterThan(10);
    const sectionSeats = await designer.readSeatCount();
    await expect(designer.toolButton('rows')).toHaveAttribute('aria-pressed', 'true');

    const firstSeat = designer.seats().first();
    const before = (await firstSeat.boundingBox())!;
    await designer.dragBy(firstSeat, {dx: 60, dy: 20});
    await expect.poll(async () => (await firstSeat.boundingBox())!.x).toBeGreaterThan(before.x + 30);
    await designer.expectSeatCount(sectionSeats);

    await designer.chooseTool('seats');
    await designer.seats().first().click();
    await designer.setSeatAccessibility('Wheelchair space');
    await expect(designer.accessibilitySelect()).toHaveValue('Wheelchair space');

    await designer.chooseTool('table');
    await designer.clickOnCanvas({x: 300, y: 100});
    await designer.expectSeatCount(sectionSeats + 8);

    await designer.undo();
    await designer.expectSeatCount(sectionSeats);

    await designer.save();
    await expect(page.getByText('Seat map saved')).toBeVisible();

    await page.reload();
    await designer.expectSeatCount(sectionSeats);
    await designer.chooseTool('seats');
    await designer.seats().first().click();
    await expect(designer.accessibilitySelect()).toHaveValue('Wheelchair space');

    await designer.nameInput().fill(`Renamed room ${Date.now()}`);
    await designer.save();
    await expect(page.getByText('Seat map saved')).toBeVisible();

    const saved = await api.getSeatMap(account.organizerId, seatMapId);
    const seats = saved.layout.areas.flatMap(area => area.elements.flatMap(element => element.seats ?? []));
    expect(seats).toHaveLength(sectionSeats);
    expect(seats.filter(seat => seat.acc)).toHaveLength(1);
  });

  test('an organizer adds a standing zone, a stage, a label and a second area', async ({authedPage: page, api, account}) => {
    const seatMapId = await createFixtureSeatMap(api, account.organizerId, 'empty', `Club ${Date.now()}`);
    const designer = new SeatMapDesignerPage(page);
    await designer.gotoSeatMap(account.organizerId, seatMapId);
    await expect(designer.canvasText('Stage')).toHaveCount(1);

    await designer.chooseTool('zone');
    for (const corner of [{x: 200, y: 300}, {x: 420, y: 300}, {x: 420, y: 460}, {x: 200, y: 460}, {x: 200, y: 300}]) {
      await designer.clickOnCanvas(corner);
    }
    await expect(designer.zones()).toHaveCount(1);
    await expect(designer.canvasText('Standing').first()).toBeVisible();

    await designer.chooseTool('object');
    await designer.drawOnCanvas({x: 520, y: 320}, {x: 700, y: 420});
    await expect(designer.canvasText('Stage')).toHaveCount(2);

    await designer.chooseTool('label');
    await designer.clickOnCanvas({x: 600, y: 540});
    await page.getByLabel('Text', {exact: true}).fill('Cloakroom');
    await expect(designer.canvasText('Cloakroom')).toBeVisible();

    await designer.deleteSelection();
    await expect(designer.canvasText('Cloakroom')).toHaveCount(0);
    await designer.undo();
    await expect(designer.canvasText('Cloakroom')).toBeVisible();
    await designer.redo();
    await expect(designer.canvasText('Cloakroom')).toHaveCount(0);

    await designer.addArea();
    await expect(designer.areaTab('New area')).toBeVisible();
    await expect(designer.canvasText('Stage')).toHaveCount(0);

    await designer.save();
    await expect(page.getByText('Seat map saved')).toBeVisible();
    await page.reload();
    await expect(designer.areaTab('New area')).toBeVisible();
    await expect(designer.zones()).toHaveCount(1);
    await expect(designer.canvasText('Stage')).toHaveCount(2);
    await expect(designer.canvasText('Cloakroom')).toHaveCount(0);
  });

  test('an organizer adds a price band and moves seats into it', async ({authedPage: page, api, account}) => {
    const seatMapId = await createFixtureSeatMap(api, account.organizerId, 'theatre', `Theatre ${Date.now()}`);
    const designer = new SeatMapDesignerPage(page);
    await designer.gotoSeatMap(account.organizerId, seatMapId);

    await designer.addBand();
    await expect(designer.deleteBandButton('New band')).toBeEnabled();

    await designer.chooseTool('seats');
    await designer.seat('e2.0.0').click();
    await designer.seat('e2.0.1').click();
    await expect(page.getByRole('heading', {name: '2 seats selected'})).toBeVisible();
    await designer.setSeatBand('New band');
    await expect(designer.deleteBandButton('New band')).toBeDisabled();

    await designer.save();
    await expect(page.getByText('Seat map saved')).toBeVisible();

    const saved = await api.getSeatMap(account.organizerId, seatMapId);
    const newBand = (saved.layout as unknown as {bands: {key: string; name: string}[]}).bands.find(band => band.name === 'New band')!;
    const seats = saved.layout.areas.flatMap(area => (area.elements as SavedElement[]).flatMap(element => element.seats ?? []));
    expect(seats.filter(seat => seat.band === newBand.key).map(seat => seat.uid).sort()).toEqual(['e2.0.0', 'e2.0.1']);
  });

  test('seat numbering schemes and a custom row label are saved', async ({authedPage: page, api, account}) => {
    const seatMapId = await createFixtureSeatMap(api, account.organizerId, 'theatre', `Numbering ${Date.now()}`);
    const designer = new SeatMapDesignerPage(page);
    await designer.gotoSeatMap(account.organizerId, seatMapId);
    const seatHeading = (label: string) => page.getByRole('heading', {name: `Seat ${label}`, exact: true});

    await designer.chooseTool('select');
    await designer.seat('e6.0.0').click();
    await designer.chooseField('numbering', 'Even numbers only');
    await designer.chooseField('direction', 'Right');

    await designer.seat('e3.4.0').click();
    await designer.chooseField('rowLabelStyle', 'Letters skipping I and O');
    await designer.firstSeatNumberInput().fill('12');
    await expect(designer.aislesInput()).toHaveValue('19, 27');
    await designer.aislesInput().fill('5');
    await expect(page.getByText('No aisle can go after seat 5 in the first row')).toBeVisible();
    await designer.aislesInput().fill('20, 28');

    await designer.chooseTool('seats');
    await designer.seat('e2.0.0').click();
    await designer.rowLabelInput().fill('AA');
    await expect(seatHeading('AA-1')).toBeVisible();

    await designer.save();
    await expect(page.getByText('Seat map saved')).toBeVisible();
    await page.reload();
    await designer.chooseTool('seats');

    for (const [uid, label] of [['e6.0.0', 'W-12'], ['e6.0.5', 'W-2'], ['e3.4.0', 'J-12'], ['e2.0.0', 'AA-1'], ['e2.1.0', 'B-1']]) {
      await designer.seat(uid).click();
      await expect(seatHeading(label)).toBeVisible();
      await designer.seat(uid).click();
    }

    await designer.chooseTool('select');
    await designer.seat('e3.0.0').click();
    await expect(designer.aislesInput()).toHaveValue('20, 28');
    const saved = await api.getSeatMap(account.organizerId, seatMapId);
    const block = saved.layout.areas.flatMap(area => area.elements).find(element => element.id === 'e3');
    expect(block?.aisles).toEqual([9, 17]);
  });

  test('leaving the designer with unsaved changes asks first and cancelling stays put', async ({authedPage: page, api, account}) => {
    const event = await createSeatedEvent(api, account.organizerId);
    const designer = new SeatMapDesignerPage(page);
    await designer.gotoEventDesigner(event.eventId);
    const designerUrl = page.url();
    const seatCount = await designer.readSeatCount();

    await designer.chooseTool('seats');
    await designer.seat('e2.0.0').click();
    await designer.removeSelectedSeats();
    await designer.expectSeatCount(seatCount - 1);

    await page.getByRole('button', {name: 'Back', exact: true}).click();
    const leaveDialog = page.getByRole('dialog', {name: /Leave without saving/});
    await expect(leaveDialog).toBeVisible();
    await leaveDialog.getByRole('button', {name: 'Cancel'}).click();

    await expect(leaveDialog).toBeHidden();
    expect(page.url()).toBe(designerUrl);
    await designer.expectSeatCount(seatCount - 1);
  });
});
