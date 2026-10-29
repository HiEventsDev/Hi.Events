import { test, expect } from '../../fixtures';
import { EventCreatePage } from '../../pages/event-create.page';
import { uniqueName } from '../../utils/unique';
import { cookieDomain } from '../../utils/env';
import { fileURLToPath } from 'node:url';

const toUtcDate = (value: string): Date =>
  new Date(/[zZ]|[+-]\d\d:?\d\d$/.test(value) ? value : `${value.replace(' ', 'T')}Z`);

const wallClock = (value: string, timeZone: string): string => {
  const parts = new Intl.DateTimeFormat('en-GB', {
    timeZone,
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    hourCycle: 'h23',
  }).formatToParts(toUtcDate(value));
  const part = (type: string) => parts.find((p) => p.type === type)?.value;
  return `${part('year')}-${part('month')}-${part('day')} ${part('hour')}:${part('minute')}`;
};

const tomorrowUtc = (): Date => {
  const date = new Date();
  date.setUTCDate(date.getUTCDate() + 1);
  return date;
};

const pad = (value: number) => String(value).padStart(2, '0');

test.describe('event creation', () => {
  test('an organizer creates a single event via the dashboard modal', { tag: '@smoke' }, async ({ freshAccount }) => {
    const title = uniqueName('E2E Single Event');
    const page = await freshAccount.newAuthedPage();

    const events = new EventCreatePage(page);
    await events.gotoDashboard();
    await events.openCreateModal();
    const eventId = await events.createSingleEvent({ title });

    await expect(page.getByText(title).first()).toBeVisible();

    const event = await freshAccount.api.getEvent(eventId);
    expect(event.type).toBe('SINGLE');
    expect(event.category).toBe('MUSIC');
    expect(event.organizer_id).toBe(freshAccount.organizerId);
    expect(event.timezone).toBe('UTC');
    expect(event.currency).toBe('USD');
    const tomorrow = tomorrowUtc();
    expect(wallClock(event.start_date!, 'UTC')).toBe(
      `${tomorrow.getUTCFullYear()}-${pad(tomorrow.getUTCMonth() + 1)}-${pad(tomorrow.getUTCDate())} 21:00`,
    );
  });

  test('date, end time, timezone, currency, category and description are saved', async ({ freshAccount }) => {
    const title = uniqueName('E2E Composer Event');
    const page = await freshAccount.newAuthedPage();
    const events = new EventCreatePage(page);
    await events.gotoDashboard();
    await events.openCreateModal();

    await events.titleInput.fill(title);

    await events.pickDate('start', { nextMonth: true, day: 15 });
    await events.setTime('start', { hours: '08', minutes: '30', amPm: 'PM' });
    await events.closePopover();
    await expect(events.chip('start')).toContainText('8:30 PM');
    await expect(events.titleInput).toBeVisible();

    await events.chip('end').click();
    await events.closePopover();
    await expect(events.chip('end')).toContainText('Ends 10:30 PM');

    await events.selectOption('timezone', 'America/New_York', 'new york');
    await expect(events.chip('timezone')).toContainText('America/New York');

    await events.selectOption('currency', 'EUR', 'eur');
    await expect(events.chip('currency')).toContainText('EUR');

    await events.selectOption('category', 'COMEDY', 'comedy');
    await expect(events.chip('category')).toContainText('Comedy');

    await events.addDescription(['Doors at 7 & bar open.', 'Over 18s only.']);
    await events.boldParagraph('Over 18s only.');

    const eventId = await events.submit();
    await expect(page.getByText(title).first()).toBeVisible();

    const event = await freshAccount.api.getEvent(eventId);
    const base = tomorrowUtc();
    const expectedMonth = new Date(Date.UTC(base.getUTCFullYear(), base.getUTCMonth() + 1, 15));
    const expectedDay = `${expectedMonth.getUTCFullYear()}-${pad(expectedMonth.getUTCMonth() + 1)}-15`;

    expect(event.timezone).toBe('America/New_York');
    expect(event.currency).toBe('EUR');
    expect(event.category).toBe('COMEDY');
    expect(wallClock(event.start_date!, 'America/New_York')).toBe(`${expectedDay} 20:30`);
    expect(wallClock(event.end_date!, 'America/New_York')).toBe(`${expectedDay} 22:30`);
    expect(event.description).toMatch(/<p[^>]*>Doors at 7 &amp; bar open\.<\/p>/);
    expect(event.description).toMatch(/<p[^>]*><strong>Over 18s only\.<\/strong><\/p>/);
  });

  test('the description supports headings and uploaded images', async ({ freshAccount }) => {
    const title = uniqueName('E2E Rich Description');
    const page = await freshAccount.newAuthedPage();
    const events = new EventCreatePage(page);
    await events.gotoDashboard();
    await events.openCreateModal();

    await events.titleInput.fill(title);
    await page.getByTestId('create-event-description-button').click();
    await events.descriptionEditor.click();

    await expect(events.floatingControl('Insert')).toBeHidden();
    await page.keyboard.type('Line-up');
    await page.keyboard.press('Enter');
    await page.keyboard.type('Two bands, one night.');
    await page.keyboard.press('Enter');
    await expect(events.floatingControl('Insert')).toBeVisible();
    await expect(events.floatingControl('Heading 3')).toBeHidden();

    await events.descriptionEditor.getByText('Line-up').click({ clickCount: 3 });
    await events.floatingControl('Heading 2').click();
    await events.descriptionEditor.locator('p').last().click();

    await events.insertUploadedImage(fileURLToPath(new URL('../../fixtures/assets/event-cover.png', import.meta.url)));
    await expect(events.descriptionEditor.locator('img')).toBeVisible();

    const eventId = await events.submit();
    const event = await freshAccount.api.getEvent(eventId);
    expect(event.description).toMatch(/<h2[^>]*>Line-up<\/h2>/);
    expect(event.description).toContain('Two bands, one night.');
    expect(event.description).toMatch(/<img[^>]+src="[^"]+"/);
  });

  test('a recurring event is created without dates', async ({ freshAccount }) => {
    const title = uniqueName('E2E Recurring Composer');
    const page = await freshAccount.newAuthedPage();
    const events = new EventCreatePage(page);
    await events.gotoDashboard();
    await events.openCreateModal();

    await events.titleInput.fill(title);
    await events.selectOption('repeat', 'RECURRING');

    await expect(events.chip('start')).toBeHidden();
    await expect(events.chip('end')).toBeHidden();
    await expect(page.getByTestId('create-event-recurring-note')).toBeVisible();

    const eventId = await events.submit();
    const event = await freshAccount.api.getEvent(eventId);
    expect(event.type).toBe('RECURRING');
    expect(event.title).toBe(title);
  });

  test('an empty name is rejected and the keyboard shortcut creates the event', async ({ freshAccount }) => {
    const title = uniqueName('E2E Shortcut Event');
    const page = await freshAccount.newAuthedPage();
    const events = new EventCreatePage(page);
    await events.gotoDashboard();
    await events.openCreateModal();

    await events.submitButton.click();
    await expect(page.getByText('Give your event a name')).toBeVisible();
    await expect(page).not.toHaveURL(/\/manage\/event\/\d+/);

    await events.titleInput.fill(title);
    await events.titleInput.press('Enter');
    await expect(events.titleInput).toBeVisible();
    await expect(page).not.toHaveURL(/\/manage\/event\/\d+/);

    await events.titleInput.press('ControlOrMeta+Enter');
    const eventId = await events.waitForCreatedEventId();
    const event = await freshAccount.api.getEvent(eventId);
    expect(event.title).toBe(title);
  });

  test('escape closes an open picker before the modal', async ({ freshAccount }) => {
    const page = await freshAccount.newAuthedPage();
    const events = new EventCreatePage(page);
    await events.gotoDashboard();
    await events.openCreateModal();

    await events.chip('start').click();
    await expect(page.getByTestId('create-event-start-chip-popover')).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(page.getByTestId('create-event-start-chip-popover')).toBeHidden();
    await expect(events.titleInput).toBeVisible();

    await events.chip('repeat').click();
    await expect(page.getByTestId('create-event-repeat-chip-option-SINGLE')).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(page.getByTestId('create-event-repeat-chip-option-SINGLE')).toBeHidden();
    await expect(events.titleInput).toBeVisible();

    await page.keyboard.press('Escape');
    await expect(events.titleInput).toBeHidden();
  });

  test('a new organizer can be created from the modal and owns the event', async ({ freshAccount }) => {
    const title = uniqueName('E2E New Org Event');
    const organizerName = uniqueName('E2E Modal Org');
    await freshAccount.api.createOrganizer(uniqueName('E2E Second Org'));
    const page = await freshAccount.newAuthedPage();
    const events = new EventCreatePage(page);
    await events.gotoDashboard();
    await events.openCreateModal();

    await events.titleInput.fill(title);
    await events.submitButton.click();
    await expect(page.getByText('Choose an organizer')).toBeVisible();

    await page.getByTestId('create-event-organizer-select').click();
    await expect(page.getByTestId('create-event-new-organizer')).toBeVisible();
    await page.keyboard.press('ArrowUp');
    await page.keyboard.press('Enter');
    await page.getByLabel(/Organization Name/).fill(organizerName);
    await page.getByRole('button', { name: 'Continue to event creation' }).click();

    await expect(page.getByTestId('create-event-organizer-select')).toContainText(organizerName);
    await expect(events.titleInput).toHaveValue(title);

    const eventId = await events.submit();

    const event = await freshAccount.api.getEvent(eventId);
    expect(event.organizer_id).not.toBe(freshAccount.organizerId);
  });

  test('dates follow the locale format (24-hour clock in German)', async ({ freshAccount }) => {
    const title = uniqueName('E2E German Event');
    const page = await freshAccount.newAuthedPage();
    await page.context().addCookies([{ name: 'locale', value: 'de', domain: cookieDomain(), path: '/' }]);

    const events = new EventCreatePage(page);
    await events.gotoDashboard();
    await events.openCreateModal();

    await expect(events.chip('start')).toContainText('21:00');
    await expect(events.chip('start')).not.toContainText(/AM|PM/);

    await events.chip('start').click();
    const popover = page.getByTestId('create-event-start-chip-popover');
    await expect(popover).toContainText(/Januar|Februar|März|April|Mai|Juni|Juli|August|September|Oktober|November|Dezember/);
    await events.closePopover();

    await events.titleInput.fill(title);
    const eventId = await events.submit();

    const event = await freshAccount.api.getEvent(eventId);
    const tomorrow = tomorrowUtc();
    expect(wallClock(event.start_date!, 'UTC')).toBe(
      `${tomorrow.getUTCFullYear()}-${pad(tomorrow.getUTCMonth() + 1)}-${pad(tomorrow.getUTCDate())} 21:00`,
    );
  });
});
