import { expect, type Locator, type Page } from '@playwright/test';

export type DesignerTool = 'select' | 'seats' | 'pan' | 'rows' | 'table' | 'zone' | 'object' | 'label';

export interface CanvasPoint {
  x: number;
  y: number;
}

export interface DragOffset {
  dx: number;
  dy: number;
}

export class SeatMapDesignerPage {
  constructor(private readonly page: Page) {}

  async gotoEventDesigner(eventId: number): Promise<void> {
    await this.page.goto(`/manage/event/${eventId}/seating/designer`);
    await expect(this.seatCount()).toHaveText(/\d+ seats/);
  }

  async createSeatMap(organizerId: number, name: string, template: string): Promise<number> {
    await this.page.goto(`/manage/organizer/${organizerId}/seat-maps`);
    await this.page.getByTestId('seat-map-create-button').click();
    await this.page.getByLabel(/^Name/).fill(name);
    await this.page.getByTestId(`seat-map-template-option-${template}`).click();
    await this.page.getByTestId('seat-map-create-submit-button').click();
    await this.page.waitForURL(/seat-maps\/\d+/);
    await expect(this.seatCount()).toHaveText(/\d+ seats/);
    return Number(/seat-maps\/(\d+)/.exec(this.page.url())![1]);
  }

  async gotoSeatMap(organizerId: number, seatMapId: number): Promise<void> {
    await this.page.goto(`/manage/organizer/${organizerId}/seat-maps/${seatMapId}`);
    await expect(this.seatCount()).toHaveText(/\d+ seats/);
  }

  canvas(): Locator {
    return this.page.getByTestId('seat-map-designer-canvas');
  }

  seat(uid: string): Locator {
    return this.canvas().locator(`[data-uid="${uid}"]`);
  }

  seats(): Locator {
    return this.canvas().locator('[data-uid]');
  }

  zones(): Locator {
    return this.canvas().locator('[data-zone-uid]');
  }

  canvasText(text: string): Locator {
    return this.canvas().getByText(text, { exact: true });
  }

  toolButton(tool: DesignerTool): Locator {
    return this.page.getByTestId(`seat-map-designer-tool-${tool}`);
  }

  async chooseTool(tool: DesignerTool): Promise<void> {
    await this.toolButton(tool).click();
    await expect(this.toolButton(tool)).toHaveAttribute('aria-pressed', 'true');
  }

  private async canvasOrigin(): Promise<CanvasPoint> {
    const box = await this.canvas().boundingBox();
    expect(box, 'designer canvas is not rendered').not.toBeNull();
    return { x: box!.x, y: box!.y };
  }

  private async centreOf(target: Locator): Promise<CanvasPoint> {
    const box = await target.boundingBox();
    expect(box, 'drag target is not rendered').not.toBeNull();
    return { x: box!.x + box!.width / 2, y: box!.y + box!.height / 2 };
  }

  private async dragBetween(from: CanvasPoint, to: CanvasPoint): Promise<void> {
    await this.page.mouse.move(from.x, from.y);
    await this.page.mouse.down();
    await this.page.mouse.move(to.x, to.y, { steps: 5 });
    await this.page.mouse.up();
  }

  async drawOnCanvas(from: CanvasPoint, to: CanvasPoint): Promise<void> {
    const origin = await this.canvasOrigin();
    await this.dragBetween({ x: origin.x + from.x, y: origin.y + from.y }, { x: origin.x + to.x, y: origin.y + to.y });
  }

  async clickOnCanvas(point: CanvasPoint): Promise<void> {
    await this.drawOnCanvas(point, point);
  }

  async dragBy(target: Locator, offset: DragOffset): Promise<void> {
    const from = await this.centreOf(target);
    await this.dragBetween(from, { x: from.x + offset.dx, y: from.y + offset.dy });
  }

  seatCount(): Locator {
    return this.page.getByTestId('seat-map-designer-seat-count');
  }

  async expectSeatCount(count: number): Promise<void> {
    await expect(this.seatCount()).toHaveText(`${count} seats`);
  }

  async readSeatCount(): Promise<number> {
    return parseInt((await this.seatCount().innerText()).replace(/\D/g, ''), 10);
  }

  accessibilitySelect(): Locator {
    return this.page.getByTestId('seat-map-designer-accessibility-select');
  }

  async setSeatAccessibility(label: 'None' | 'Wheelchair space' | 'Companion seat'): Promise<void> {
    await this.accessibilitySelect().click();
    await this.page.getByRole('option', {name: label, exact: true}).click();
  }

  async chooseField(key: string, option: string): Promise<void> {
    await this.page.getByTestId(`seat-map-designer-field-${key}`).click();
    await this.page.getByRole('option', { name: option, exact: true }).click();
  }

  async setSeatBand(bandName: string): Promise<void> {
    await this.page.getByRole('combobox', { name: 'Band', exact: true }).click();
    await this.page.getByRole('option', { name: bandName, exact: true }).click();
  }

  async addBand(): Promise<void> {
    await this.page.getByTestId('seat-map-designer-add-band-button').click();
  }

  deleteBandButton(bandName: string): Locator {
    return this.page.getByRole('button', { name: `Delete ${bandName}`, exact: true });
  }

  async addArea(): Promise<void> {
    await this.page.getByTestId('seat-map-designer-area-menu-button').click();
    await this.page.getByTestId('seat-map-designer-add-area-menu-item').click();
  }

  areaTab(name: string): Locator {
    return this.page.getByRole('navigation', { name: 'Areas' }).getByRole('button', { name, exact: true });
  }

  async deleteSelection(): Promise<void> {
    await this.page.getByTestId('seat-map-designer-delete-button').click();
  }

  aislesInput(): Locator {
    return this.page.getByTestId('seat-map-designer-field-aisles');
  }

  firstSeatNumberInput(): Locator {
    return this.page.getByLabel('First seat number', {exact: true});
  }

  rowLabelInput(): Locator {
    return this.page.getByTestId('seat-map-designer-row-label-input');
  }

  areaNameInput(): Locator {
    return this.page.getByLabel('Area name');
  }

  nameInput(): Locator {
    return this.page.getByLabel('Seat map name');
  }

  async removeSelectedSeats(): Promise<void> {
    await this.page.getByTestId('seat-map-designer-remove-seats-button').click();
  }

  async undo(): Promise<void> {
    await this.page.getByTestId('seat-map-designer-undo-button').click();
  }

  async redo(): Promise<void> {
    await this.page.getByRole('button', { name: 'Redo' }).click();
  }

  async save(): Promise<void> {
    await this.page.getByTestId('seat-map-designer-save-button').click();
  }
}
