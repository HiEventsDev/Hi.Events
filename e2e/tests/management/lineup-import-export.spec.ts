import { readFile, writeFile } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';
import { test, expect } from '../../fixtures';
import { createCompletedOrder, createLiveEventWithFreeTicket } from '../../api/factory';
import { LineupPage } from '../../pages/lineup.page';
import { PublicEventPage } from '../../pages/public-event.page';
import { uniqueEmail, uniqueName } from '../../utils/unique';
import { waitForHydration } from '../../utils/hydration';

const EXCEL_FIXTURE = fileURLToPath(new URL('../../fixtures/assets/lineup-excel.xlsx', import.meta.url));

test.describe('lineup import and export', () => {
  test('an organizer previews a spreadsheet import, then exports and re-imports it without duplicates', { tag: '@smoke' }, async ({ authedPage: page, api, account }, testInfo) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    const headliner = uniqueName('Nova');
    const guest = uniqueName('Ada');
    const csvPath = testInfo.outputPath('schedule.csv');
    await writeFile(csvPath, [
      'Date,Start,End,Stage,Title,People',
      '2030-06-14,19:00,19:30,Main Stage,Doors open,',
      `2030-06-14,23:00,00:30,Main Stage,,${headliner}`,
      `2030-06-14,20:00,20:45,Tent,Panel,"${headliner}; ${guest}"`,
      '',
      '2030-06-15,later,21:00,Tent,Broken row,',
    ].join('\n'));

    const lineup = new LineupPage(page);
    await lineup.goto(event.eventId);
    await lineup.importFile(csvPath);

    await expect(lineup.summaryValue('people')).toHaveText('2');
    await expect(lineup.summaryValue('slots')).toHaveText('3');
    await expect(lineup.summaryValue('stages')).toHaveText('2');
    await expect(lineup.summaryValue('errors')).toHaveText('1');
    await expect(lineup.importRow('Broken row')).toContainText('Time not recognised');
    await expect(lineup.importRow('Broken row')).toContainText('6');
    await expect(lineup.importDialog()).toContainText("Times are read in the event's time zone (UTC)");
    await expect(lineup.importDialog().getByTestId('lineup-import-footer-summary')).toHaveText('3 time slots, 2 people to add');

    await lineup.importDialog().getByTestId('lineup-import-submit').click();
    await expect(page.getByText('2 people added, 3 time slots added')).toBeVisible();
    await expect(lineup.person(headliner)).toContainText('2 slots');
    await expect(lineup.person(guest)).toContainText('1 slot');

    const download = await lineup.exportSpreadsheet();
    expect(download.suggestedFilename()).toBe(`${event.slug}-run-sheet.xlsx`);
    const xlsxPath = testInfo.outputPath('run-sheet.xlsx');
    await download.saveAs(xlsxPath);

    await lineup.importFile(xlsxPath);
    await expect(lineup.summaryValue('slots')).toHaveText('0');
    await expect(lineup.importDialog().getByTestId('lineup-import-slots')).toContainText('3 already scheduled');
    await expect(lineup.summaryValue('people')).toHaveText('0');
    await expect(lineup.importDialog().getByTestId('lineup-import-rows').getByText('Already scheduled')).toHaveCount(3);
    await expect(lineup.importDialog().getByTestId('lineup-import-footer-summary')).toHaveText('Nothing new to import');
    await expect(lineup.importDialog().getByTestId('lineup-import-submit')).toBeDisabled();
    await lineup.importDialog().getByRole('button', { name: 'Cancel' }).click();

    await page.goto(`/manage/event/${event.eventId}/lineup/print`);
    await expect(page).toHaveTitle(`Run sheet · ${event.title}`);
    await expect(page.getByRole('heading', { name: event.title })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Tent' })).toBeVisible();
    await expect(page.getByRole('row').filter({ hasText: 'Panel' })).toContainText('45 min');
    await page.getByRole('switch', { name: 'Group by stage or room' }).click();
    await expect(page.getByRole('heading', { name: 'Tent' })).toHaveCount(0);
    await expect(page.getByRole('columnheader', { name: 'Stage' })).toBeVisible();
    await expect(page.getByRole('row').filter({ hasText: 'Panel' })).toContainText('Tent');

    await page.emulateMedia({ media: 'print' });
    await expect(page.getByRole('switch', { name: 'Group by stage or room' })).toBeHidden();
    expect(await page.evaluate(() => getComputedStyle(document.body).backgroundColor)).toBe('rgb(255, 255, 255)');
  });

  test('pasted rows show which people change, let the organizer pick the date order and flag rows to fix', async ({ authedPage: page, api, account }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    const existing = uniqueName('Nova');
    const newcomer = uniqueName('Ada');
    await api.importLineup(event.eventId, { people: [{ name: existing, billing_tier: 'HEADLINER' }], slots: [] });

    const lineup = new LineupPage(page);
    await lineup.goto(event.eventId);
    await lineup.openImport();
    await lineup.pasteRows([
      ['Name', 'Billing', 'Tagline'],
      [existing, 'Headliner', ''],
      [newcomer, '', 'Engineer'],
    ]);

    await expect(lineup.importRow(existing)).toContainText('No changes');
    await expect(lineup.importRow(newcomer)).toContainText('New');
    await expect(lineup.importDialog().getByTestId('lineup-import-footer-summary')).toHaveText('0 time slots, 1 person to add');
    await lineup.importDialog().getByTestId('lineup-import-submit').click();
    await expect(page.getByText('1 person added, 0 time slots added')).toBeVisible();
    await expect(lineup.person(newcomer)).toBeVisible();

    await lineup.openImport();
    await lineup.pasteRows([
      ['Date', 'Start', 'End', 'Stage', 'Artist'],
      ['06/07/2030', '19:00', '20:00', 'Main Stage', existing],
      ['06/07/2030', '21:00', '22:00', 'S'.repeat(101), newcomer],
    ]);

    const dateOrder = lineup.importDialog().getByTestId('lineup-import-date-order');
    await expect(dateOrder).toBeVisible();
    await dateOrder.getByText('Day/Month').click();
    await expect(lineup.importRow(existing)).toContainText('Jul 6');
    await dateOrder.getByText('Month/Day').click();
    await expect(lineup.importRow(existing)).toContainText('Jun 7');
    await expect(lineup.importRow(newcomer)).toContainText('Some text is too long');
    await expect(lineup.summaryValue('errors')).toHaveText('1');

    await lineup.importDialog().getByRole('switch', { name: 'Only rows to fix' }).click();
    await expect(lineup.importDialog().getByTestId('lineup-import-rows').getByRole('listitem')).toHaveCount(1);
  });

  test('an Excel workbook with a title row, real date and time cells and a people sheet imports correctly', async ({ authedPage: page, api, account }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    const lineup = new LineupPage(page);
    await lineup.goto(event.eventId);
    await lineup.importFile(EXCEL_FIXTURE);

    await expect(lineup.importRow('Fixture Opener')).toContainText('Fri, Jun 14 · 7:30 PM – 8:30 PM · Main Stage');
    await expect(lineup.importRow('Fixture Opener')).toContainText('4');
    await expect(lineup.importRow('Fixture Headliner')).toContainText('11:15 PM – 12:45 AM');
    await expect(lineup.summaryValue('people')).toHaveText('2');
    await expect(lineup.summaryValue('errors')).toHaveText('0');

    await lineup.importDialog().getByText('People (1)').click();
    await expect(lineup.importRow('Fixture Headliner')).toContainText('Headliner · Café sessions · 1 link');

    await lineup.importDialog().getByTestId('lineup-import-submit').click();
    await expect(page.getByText('2 people added, 2 time slots added')).toBeVisible();
    await expect(lineup.person('Fixture Headliner')).toContainText('Headliner');

    const saved = await api.getLineup(event.eventId);
    const headliner = saved.entries.find(entry => entry.profile.name === 'Fixture Headliner');
    expect(headliner?.profile.links).toEqual([{ type: 'WEBSITE', url: 'https://www.fixture.example.com' }]);
    expect(headliner?.profile.tagline).toBe('Café sessions');
    expect(saved.schedule_items.map(item => item.starts_at).sort()).toEqual(['2030-06-14 19:30:00', '2030-06-14 23:15:00']);
  });

  test('rows the server rejects are pointed out and nothing is imported', async ({ authedPage: page, api, account }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    const lineup = new LineupPage(page);
    await lineup.goto(event.eventId);

    await page.route(`**/events/${event.eventId}/lineup/import`, route => route.fulfill({
      status: 422,
      contentType: 'application/json',
      body: JSON.stringify({ message: 'Invalid', errors: { 'slots.1.title': ['The title is not allowed.'] } }),
    }));

    await lineup.openImport();
    await lineup.pasteRows([
      ['Date', 'Start', 'End', 'Title'],
      ['2030-06-14', '19:00', '20:00', 'Opening'],
      ['2030-06-14', '20:00', '21:00', 'Rejected talk'],
    ]);
    await lineup.importDialog().getByTestId('lineup-import-submit').click();

    await expect(lineup.importDialog().getByTestId('lineup-import-rejected')).toBeVisible();
    await expect(lineup.importRow('Rejected talk')).toContainText('The title is not allowed.');
    await expect(lineup.importRow('Opening')).toHaveCount(0);
    await expect(lineup.importDialog().getByTestId('lineup-import-submit')).toBeDisabled();
  });

  test('a set that runs past midnight stays on one timetable day', async ({ authedPage: page, api, account }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    const headliner = uniqueName('Night Owl');
    await api.importLineup(event.eventId, {
      people: [{ name: headliner, billing_tier: 'HEADLINER' }],
      slots: [{ starts_at: '2030-06-14 23:00:00', ends_at: '2030-06-15 01:00:00', stage: 'Club', people: [headliner] }],
    });

    const lineup = new LineupPage(page);
    await lineup.goto(event.eventId);
    await lineup.openView('Timetable');

    await expect(page.getByRole('group', { name: 'Days' }).getByRole('button')).toHaveCount(1);
    await expect(page.getByRole('group', { name: 'Days' }).getByRole('button')).toContainText('Jun 14');
    const block = page.getByText(headliner, { exact: true }).locator('xpath=ancestor::*[contains(@class, "calendarEventText")]');
    await expect(block).toHaveCount(1);
    await expect(block).toContainText('11:00 PM–1:00 AM');
  });

  test('the widget settings can show the lineup above the tickets', async ({ authedPage: page, api, account }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    const headliner = uniqueName('Velvet Harbour');
    await api.importLineup(event.eventId, { people: [{ name: headliner, billing_tier: 'HEADLINER' }], slots: [] });

    await page.goto(`/manage/event/${event.eventId}/widget`);
    const preview = page.frameLocator('iframe[title="Widget preview"]');
    await expect(preview.getByRole('heading', { name: event.productTitle })).toBeVisible();
    await expect(preview.getByTestId('widget-lineup-strip')).toHaveCount(0);
    await page.getByTestId('widget-group-sections').click();
    const showLineup = page.getByTestId('widget-show-lineup-switch');
    await expect(() => showLineup.check()).toPass();
    await expect(preview.getByTestId('widget-lineup-strip')).toContainText(headliner);
    await expect(page.getByTestId('widget-embed-code')).toContainText('data-hievents-show-lineup="true"');
  });
});

test.describe('lineup for visitors', () => {
  test('visitors open a session, add it to their calendar and see the lineup in the widget, search data and emails', { tag: '@smoke' }, async ({ page, api, account, publicApi, mailpit }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    const headliner = uniqueName('Velvet Harbour');
    await api.importLineup(event.eventId, {
      people: [{ name: headliner, billing_tier: 'HEADLINER' }],
      slots: [{ starts_at: '2030-06-14 21:30:00', ends_at: '2030-06-14 23:00:00', stage: 'Main Stage', people: [headliner] }],
    });

    const pageResponse = await page.request.get(`/event/${event.eventId}/${event.slug}`);
    const jsonLd = JSON.parse((await pageResponse.text()).match(/<script[^>]*type="application\/ld\+json"[^>]*>([\s\S]*?)<\/script>/)![1]);
    expect(jsonLd.performer).toEqual([expect.objectContaining({ name: headliner })]);
    expect(jsonLd.subEvent).toEqual([expect.objectContaining({
      name: headliner,
      startDate: '2030-06-14T21:30:00+00:00',
      url: expect.stringMatching(/#session-\d+$/),
    })]);

    const publicPage = new PublicEventPage(page);
    await publicPage.goto(event.eventId, event.slug);
    await page.getByRole('button', { name: 'Add to Calendar' }).first().click();
    await expect(page.getByText('Choose calendar')).toBeVisible();
    await page.getByRole('button', { name: 'Add to Calendar' }).first().click();
    await expect(page.getByText('Choose calendar')).toBeHidden();

    await page.getByTestId('event-schedule-item').getByRole('button', { name: headliner }).click();

    const session = page.getByRole('dialog', { name: headliner });
    await expect(session).toContainText('Main Stage');
    await expect(session).toContainText('1 h 30 min');
    await expect(page).toHaveURL(/#session-\d+$/);

    await session.getByTestId('session-add-to-calendar').click();
    const [ics] = await Promise.all([
      page.waitForEvent('download'),
      page.getByRole('menuitem', { name: 'Download .ics' }).click(),
    ]);
    const calendar = await readFile(await ics.path(), 'utf8');
    expect(calendar).toContain('DTSTART:20300614T213000Z');
    expect(calendar).toContain('DTEND:20300614T230000Z');
    expect(calendar).toContain(`SUMMARY:${headliner}`);
    await expect(page.getByText('Choose calendar')).toBeHidden();

    await session.getByRole('button', { name: new RegExp(headliner) }).click();
    const profile = page.getByRole('dialog', { name: headliner });
    await expect(profile.getByRole('heading', { level: 3, name: headliner })).toBeVisible();
    await profile.getByRole('button', { name: 'Back' }).click();
    await expect(page.getByTestId('session-modal')).toBeVisible();

    const sessionUrl = page.url();
    await page.goto('about:blank');
    await page.goto(sessionUrl);
    await expect(page.getByTestId('session-modal')).toContainText(headliner);
    await page.getByRole('dialog').getByRole('button', { name: 'Close' }).click();
    await expect(page).not.toHaveURL(/#session-/);

    await page.goto(`/widget/${event.eventId}?ShowLineup=true`);
    await expect(page.getByTestId('widget-lineup-strip')).toContainText(headliner);
    await page.goto(`/widget/${event.eventId}`);
    await expect(page.getByRole('heading', { name: 'Tickets' })).toBeVisible();
    await expect(page.getByTestId('widget-lineup-strip')).toHaveCount(0);

    const buyerEmail = uniqueEmail('lineup-buyer');
    await createCompletedOrder(publicApi, event, { buyerEmail });
    for (const subject of ['Order', 'Ticket']) {
      const message = await mailpit.waitForMessage(buyerEmail, { subjectContains: subject });
      const body = (await mailpit.getMessage(message.ID)).HTML;
      expect(body).toContain(headliner);
      expect(body).toContain('Main Stage');
      expect(body).toContain('See the full schedule');
    }
  });

  test('the lineup widget follows the Lineup section toggle, and a missing event shows its message', async ({ page, api, account }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    const headliner = uniqueName('Late Bill');
    await api.importLineup(event.eventId, {
      people: [{ name: headliner, billing_tier: 'HEADLINER' }],
      slots: [{ starts_at: '2030-06-14 21:30:00', ends_at: '2030-06-14 23:00:00', people: [headliner] }],
    });
    await api.updateEventSettings(event.eventId, {
      homepage_sections: [{ id: 'lineup', type: 'LINEUP', enabled: false }],
    });

    await page.goto(`/widget/${event.eventId}/lineup`);
    await expect(page.getByTestId('lineup-widget')).toContainText('Coming soon');
    await expect(page.getByTestId('lineup-widget')).not.toContainText(headliner);
    await page.goto(`/widget/${event.eventId}/schedule`);
    await expect(page.getByTestId('schedule-widget')).toContainText(headliner);

    const errors: string[] = [];
    page.on('pageerror', error => errors.push(error.message));
    page.on('console', message => {
      if (message.type() === 'error' && /hydrat|#418|#423|#425/i.test(message.text())) {
        errors.push(message.text());
      }
    });
    for (const variant of ['lineup', 'schedule']) {
      await page.goto(`/widget/999999999/${variant}`);
      await waitForHydration(page, 'main.hi-widget-tokens');
      await expect(page.getByText('This event is not available')).toBeVisible();
      await expect(page.locator('.hi-widget-loading')).toHaveCount(0);
    }
    expect(errors).toEqual([]);
  });

  test('turning the lineup sections off hides them from the widget, search data and emails', async ({ page, api, account, publicApi, mailpit }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    const headliner = uniqueName('Secret Set');
    await api.importLineup(event.eventId, {
      people: [{ name: headliner, billing_tier: 'HEADLINER' }],
      slots: [{ starts_at: '2030-06-14 21:30:00', ends_at: '2030-06-14 23:00:00', people: [headliner] }],
    });
    await api.updateEventSettings(event.eventId, {
      homepage_sections: [
        { id: 'lineup', type: 'LINEUP', enabled: false },
        { id: 'schedule', type: 'SCHEDULE', enabled: false },
      ],
    });

    const html = await (await page.request.get(`/event/${event.eventId}/${event.slug}`)).text();
    expect(html).not.toContain('"performer"');
    expect(html).not.toContain('"subEvent"');
    expect(html).not.toContain(headliner);
    const publicLineup = await (await page.request.get(`/api/public/events/${event.eventId}/lineup`, { headers: { Accept: 'application/json' } })).json();
    expect(publicLineup.data.entries).toEqual([]);
    expect(publicLineup.data.schedule_items).toEqual([]);

    await page.goto(`/widget/${event.eventId}?ShowLineup=true`);
    await expect(page.getByRole('heading', { name: 'Tickets' })).toBeVisible();
    await expect(page.getByTestId('widget-lineup-strip')).toHaveCount(0);

    const buyerEmail = uniqueEmail('lineup-off');
    await createCompletedOrder(publicApi, event, { buyerEmail });
    const message = await mailpit.waitForMessage(buyerEmail, { subjectContains: 'Order' });
    expect((await mailpit.getMessage(message.ID)).HTML).not.toContain(headliner);
  });
});
