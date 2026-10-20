import { readFile } from 'node:fs/promises';
import { test, expect } from '../../fixtures';
import { createLiveEventWithFreeTicket, createRecurringLiveEvent } from '../../api/factory';
import { PublicEventPage } from '../../pages/public-event.page';
import { openEmbedHost, resolveWidgetFrame, widgetMarkup } from '../../pages/embedded-widget';
import { uniqueName } from '../../utils/unique';

const festivalSlots = [
  { starts_at: '2030-06-14 10:00:00', ends_at: '2030-06-14 10:20:00', stage: 'Main Stage', title: 'Opening keynote on the future of independent festivals and the people who run them', people: [] },
  { starts_at: '2030-06-14 11:00:00', ends_at: '2030-06-14 12:00:00', stage: 'Main Stage', title: 'Talk A', people: [] },
  { starts_at: '2030-06-14 11:30:00', ends_at: '2030-06-14 12:30:00', stage: 'Main Stage', title: 'Talk B', people: [] },
  { starts_at: '2030-06-14 10:00:00', ends_at: '2030-06-14 12:00:00', stage: 'Side Stage', title: 'Workshop', people: [] },
  { starts_at: '2030-06-14 13:00:00', ends_at: '2030-06-14 13:05:00', stage: 'Side Stage', title: 'Lightning 1', people: [] },
  { starts_at: '2030-06-14 13:05:00', ends_at: '2030-06-14 13:10:00', stage: 'Side Stage', title: 'Lightning 2', people: [] },
  { starts_at: '2030-06-14 13:10:00', ends_at: '2030-06-14 13:15:00', stage: 'Side Stage', title: 'Lightning 3', people: [] },
  { starts_at: '2030-06-14 18:00:00', ends_at: '2030-06-14 23:00:00', title: 'Food trucks', people: [] },
  { starts_at: '2030-06-14 18:00:00', ends_at: '2030-06-15 02:00:00', title: 'Bar open', people: [] },
  { starts_at: '2030-06-15 20:00:00', ends_at: '2030-06-15 21:00:00', stage: 'Main Stage', title: 'Closing party', people: [] },
];

test.describe('public lineup and schedule', () => {
  test('the stages view keeps every session readable and clickable, and says which stage it is on', async ({ page, api, account }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    await api.importLineup(event.eventId, { people: [], slots: festivalSlots });

    await page.setViewportSize({ width: 1440, height: 900 });
    await new PublicEventPage(page).goto(event.eventId, event.slug);
    const schedule = page.getByTestId('event-schedule-section');

    const days = schedule.getByRole('group', { name: 'Days' }).getByRole('button');
    await expect(days).toHaveCount(2);
    await expect(days.first()).toHaveAttribute('aria-pressed', 'true');
    await expect(days.last()).toHaveAttribute('aria-pressed', 'false');
    await expect(schedule.getByRole('button', { name: 'All stages', pressed: true })).toBeVisible();
    await expect(schedule.getByRole('button', { name: 'Side Stage', pressed: false })).toBeVisible();
    await schedule.getByRole('button', { name: 'Stages', exact: true }).click();
    await expect(schedule.getByRole('button', { name: 'Stages', exact: true, pressed: true })).toBeVisible();
    await expect(schedule.getByRole('button', { name: 'List', exact: true, pressed: false })).toBeVisible();

    const blocks = schedule.getByTestId('event-stage-block');
    await expect(blocks).toHaveCount(9);
    await expect(blocks.filter({ hasText: 'Talk B' })).toHaveAccessibleName(/Talk B.*Main Stage/);
    await expect(blocks.filter({ hasText: 'Food trucks' })).toHaveAccessibleName(/Food trucks.*Everywhere/);

    for (const block of await blocks.all()) {
      await block.scrollIntoViewIfNeeded();
      const title = block.locator('[class*="stageBlockTitle"]');
      const titleBox = (await title.boundingBox())!;
      const blockBox = (await block.boundingBox())!;
      expect(titleBox.y + titleBox.height).toBeLessThanOrEqual(blockBox.y + blockBox.height + 0.5);

      const topmost = await page.evaluate(([x, y]) => document.elementFromPoint(x, y)?.closest('[data-testid="event-stage-block"]')?.textContent ?? null,
        [titleBox.x + Math.min(titleBox.width / 2, 12), titleBox.y + Math.min(titleBox.height / 2, 6)]);
      expect(topmost).toBe(await block.textContent());
    }

    const hourLabels = schedule.locator('[class*="timeGutter"] span');
    for (const label of await hourLabels.all()) {
      expect((await label.boundingBox())!.height).toBeLessThan(20);
    }
  });

  test('keyboard users get back to the session they opened and can add it to their calendar', async ({ page, api, account }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    const headliner = uniqueName('Keyboard Act');
    await api.importLineup(event.eventId, {
      people: [{ name: headliner, billing_tier: 'HEADLINER' }],
      slots: [{ starts_at: '2030-06-14 21:30:00', ends_at: '2030-06-14 23:00:00', stage: 'Main Stage', people: [headliner] }],
    });

    await new PublicEventPage(page).goto(event.eventId, event.slug);

    const sessionButton = page.getByTestId('event-schedule-item').getByRole('button', { name: headliner });
    await sessionButton.focus();
    await page.keyboard.press('Enter');
    const session = page.getByTestId('session-modal');
    await expect(session).toBeVisible();

    await session.getByTestId('session-add-to-calendar').focus();
    await page.keyboard.press('Enter');
    await expect(page.getByRole('menuitem', { name: 'Google Calendar' })).toBeFocused();
    await page.keyboard.press('ArrowDown');
    await expect(page.getByRole('menuitem', { name: 'Download .ics' })).toBeFocused();
    const [ics] = await Promise.all([
      page.waitForEvent('download'),
      page.keyboard.press('Enter'),
    ]);
    expect(await readFile(await ics.path(), 'utf8')).toContain('DTSTART:20300614T213000Z');
    await expect(session.getByTestId('session-add-to-calendar')).toBeFocused();

    await page.keyboard.press('Enter');
    await expect(page.getByRole('menuitem', { name: 'Google Calendar' })).toBeFocused();
    await page.keyboard.press('Escape');
    await expect(page.getByRole('menuitem', { name: 'Google Calendar' })).toBeHidden();
    await expect(session).toBeVisible();

    await page.keyboard.press('Escape');
    await expect(session).toBeHidden();
    await expect(sessionButton).toBeFocused();

    const card = page.getByTestId('event-lineup-section').getByRole('button', { name: `About ${headliner}` });
    await card.focus();
    await page.keyboard.press('Enter');
    const profile = page.getByRole('dialog', { name: headliner });
    await expect(profile.getByRole('heading', { name: headliner, level: 3 })).toBeVisible();
    await profile.getByRole('button', { name: /Main Stage/ }).click();
    await expect(page.getByTestId('session-modal')).toBeVisible();
    await page.getByRole('dialog').getByRole('button', { name: 'Back' }).click();
    await expect(profile.getByRole('heading', { name: headliner, level: 3 })).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(card).toBeFocused();
  });

  test('a photo-less headliner is a compact card, not an empty poster', async ({ page, api, account }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    const name = uniqueName('No Photo Headliner');
    await api.importLineup(event.eventId, { people: [{ name, billing_tier: 'HEADLINER' }], slots: [] });

    for (const viewport of [{ width: 390, height: 844 }, { width: 1440, height: 900 }]) {
      await page.setViewportSize(viewport);
      await new PublicEventPage(page).goto(event.eventId, event.slug);
      const card = page.getByTestId('event-lineup-card').filter({ hasText: name });
      await expect(card).toBeVisible();
      expect((await card.boundingBox())!.height).toBeLessThan(200);
    }
  });

  test('a link to one date of a recurring event renders that date’s lineup on the server', async ({ browser, api, account, baseURL }) => {
    const event = await createRecurringLiveEvent(api, account.organizerId, { count: 3 });
    const [first, second] = event.occurrences;
    const firstAct = uniqueName('First Friday');
    const secondAct = uniqueName('Second Friday');
    const resident = uniqueName('Resident');
    const slotOn = (occurrence: { start_date: string }, person: string) => {
      const day = occurrence.start_date.slice(0, 10);
      return { starts_at: `${day} 19:30:00`, ends_at: `${day} 20:30:00`, people: [person] };
    };
    await api.importLineup(event.eventId, {
      people: [firstAct, secondAct, resident].map((name) => ({ name, billing_tier: 'FEATURED' as const })),
      slots: [slotOn(first, firstAct), slotOn(second, secondAct)],
    });
    const url = `${baseURL}/event/${event.eventId}/${event.slug}?occurrence_id=${second.id}`;

    const noScript = await browser.newContext({ javaScriptEnabled: false, ignoreHTTPSErrors: true });
    const serverPage = await noScript.newPage();
    await serverPage.goto(url);
    const serverLineup = serverPage.getByTestId('event-lineup-section');
    await expect(serverLineup).toContainText(secondAct);
    await expect(serverLineup).toContainText(resident);
    await expect(serverLineup).not.toContainText(firstAct);
    await noScript.close();

    const hydratedPage = await browser.newPage({ ignoreHTTPSErrors: true });
    const consoleErrors: string[] = [];
    hydratedPage.on('console', (message) => message.type() === 'error' && consoleErrors.push(message.text()));
    await hydratedPage.goto(url);
    await hydratedPage.waitForLoadState('networkidle');
    await expect(hydratedPage.getByTestId('event-lineup-card')).toHaveCount(2);
    await expect(hydratedPage.getByTestId('event-lineup-section')).not.toContainText(firstAct);
    expect(consoleErrors.filter((message) => /Hydration|#418|#425/.test(message))).toEqual([]);
    await hydratedPage.close();
  });

  test('with the schedule hidden, each date of a recurring event still shows only its own acts', async ({ page, api, account }) => {
    const event = await createRecurringLiveEvent(api, account.organizerId, { count: 3 });
    const [first, second] = event.occurrences;
    const firstAct = uniqueName('First Friday');
    const secondAct = uniqueName('Second Friday');
    const resident = uniqueName('Resident');
    const slotOn = (occurrence: { start_date: string }, person: string) => {
      const day = occurrence.start_date.slice(0, 10);
      return { starts_at: `${day} 19:30:00`, ends_at: `${day} 20:30:00`, people: [person] };
    };
    await api.importLineup(event.eventId, {
      people: [firstAct, secondAct, resident].map((name) => ({ name, billing_tier: 'FEATURED' as const })),
      slots: [slotOn(first, firstAct), slotOn(second, secondAct)],
    });
    await api.updateEventSettings(event.eventId, {
      homepage_sections: [{ id: 'schedule', type: 'SCHEDULE', enabled: false }],
    });

    await page.goto(`/event/${event.eventId}/${event.slug}?occurrence_id=${second.id}`);
    const lineup = page.getByTestId('event-lineup-section');
    await expect(lineup).toContainText(secondAct);
    await expect(lineup).toContainText(resident);
    await expect(lineup).not.toContainText(firstAct);
    await expect(page.getByTestId('event-schedule-item')).toHaveCount(0);

    await page.goto(`/widget/${event.eventId}/lineup?OccurrenceId=${first.id}`);
    await expect(page.getByTestId('lineup-widget')).toContainText(firstAct);
    await expect(page.getByTestId('lineup-widget')).not.toContainText(secondAct);
  });

  test('the sections widget leaves the lineup out when the Lineup section is turned off', async ({ page, api, account, baseURL }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    const name = uniqueName('Hidden Lineup Act');
    await api.importLineup(event.eventId, {
      people: [{ name, billing_tier: 'HEADLINER' }],
      slots: [{ starts_at: '2030-06-14 21:30:00', ends_at: '2030-06-14 23:00:00', stage: 'Main Stage', people: [name] }],
    });
    await api.updateEventSettings(event.eventId, {
      homepage_sections: [{ id: 'lineup', type: 'LINEUP', enabled: false }],
    });

    await openEmbedHost(page, baseURL!, widgetMarkup({ id: String(event.eventId), sections: 'lineup,schedule,tickets' }));
    const widget = await resolveWidgetFrame(page);

    await expect(widget.getByTestId('event-schedule-item')).toContainText(name);
    await expect(widget.getByTestId('event-lineup-section')).toHaveCount(0);
  });
});
