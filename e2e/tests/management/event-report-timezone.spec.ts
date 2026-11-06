import { test, expect } from '../../fixtures';
import { openAuthedPage } from '../../fixtures/auth.fixture';
import { createLiveEventWithProduct } from '../../api/factory';
import { EventReportPage } from '../../pages/event-report.page';

const EVENT_TIMEZONE = 'America/Los_Angeles';
const BROWSER_TIMEZONE = 'Asia/Tokyo';

const wallClock = (iso: string, timeZone: string): string =>
  new Intl.DateTimeFormat('sv-SE', {
    timeZone,
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
    hourCycle: 'h23',
  }).format(new Date(iso));

test.describe('event report custom date range', () => {
  test('a picked day maps to that day in the event timezone, not the browser timezone', async ({ browser, api, account }) => {
    const event = await createLiveEventWithProduct(api, { organizerId: account.organizerId, timezone: EVENT_TIMEZONE });
    const page = await openAuthedPage(browser, account.token, { timezoneId: BROWSER_TIMEZONE });
    const report = new EventReportPage(page);

    await report.goto(event.eventId, 'daily_sales_report');
    await report.chooseCustomRange();

    const dropdown = await report.openDateRangePicker();
    const monthHeading = (await dropdown.locator('[class*="calendarHeaderLevel"]').innerText()).trim();
    const monthStart = new Date(`1 ${monthHeading} 12:00 UTC`);
    const isoDay = (day: number): string =>
      `${monthStart.getUTCFullYear()}-${String(monthStart.getUTCMonth() + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;

    await dropdown.getByRole('button', { name: `10 ${monthHeading}`, exact: true }).click();

    const reportRequest = page.waitForRequest((request) =>
      request.url().includes(`/events/${event.eventId}/reports/daily_sales_report`) && request.url().includes('end_date='),
    );
    await dropdown.getByRole('button', { name: `12 ${monthHeading}`, exact: true }).click();
    const params = new URL((await reportRequest).url()).searchParams;

    expect(wallClock(params.get('start_date')!, EVENT_TIMEZONE)).toBe(`${isoDay(10)} 00:00:00`);
    expect(wallClock(params.get('end_date')!, EVENT_TIMEZONE)).toBe(`${isoDay(12)} 23:59:59`);

    const [monthName, year] = monthHeading.split(' ');
    await expect(report.dateRangePicker).toContainText(`${monthName} 10, ${year}`);
    await expect(report.dateRangePicker).toContainText(`${monthName} 12, ${year}`);

    await page.context().close();
  });
});
