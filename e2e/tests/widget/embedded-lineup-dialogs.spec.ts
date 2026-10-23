import { type Page } from '@playwright/test';
import { test, expect } from '../../fixtures';
import { createLiveEventWithFreeTicket } from '../../api/factory';
import { openEmbedHost, resolveWidgetFrame, widgetMarkup } from '../../pages/embedded-widget';
import { uniqueName } from '../../utils/unique';

const SPACER = '<div style="height: 1400px">Host page content</div>';

const iframeHeight = (page: Page, title: string) =>
  page.locator(`iframe[title="${title}"]`).evaluate((frame) => frame.getBoundingClientRect().height);

const settledIframeHeight = async (page: Page, title: string) => {
  const frame = page.locator(`iframe[title="${title}"]`);
  await frame.scrollIntoViewIfNeeded();
  let previous = -1;
  await expect.poll(async () => {
    const { height, resized } = await frame.evaluate((element: HTMLIFrameElement) => ({
      height: element.getBoundingClientRect().height,
      resized: element.style.height !== '',
    }));
    const settled = resized && height === previous;
    previous = height;
    return settled;
  }, { intervals: [300] }).toBe(true);
  return previous;
};

const scrollIframeTopAboveViewport = (page: Page, title: string, distance: number) =>
  page.evaluate(([selector, by]) => {
    const frame = document.querySelector(selector)!;
    window.scrollTo(0, frame.getBoundingClientRect().top + window.scrollY + by);
  }, [`iframe[title="${title}"]`, distance] as const);

for (const viewport of [{ name: 'desktop', width: 1280, height: 800 }, { name: 'phone', width: 390, height: 664 }]) {
  test.describe(`embedded lineup dialogs on ${viewport.name}`, () => {
    test.use({ viewport: { width: viewport.width, height: viewport.height } });

    test('a session opened after scrolling past the top of a short schedule widget is fully in view', async ({ page, api, account, baseURL }) => {
      const event = await createLiveEventWithFreeTicket(api, account.organizerId);
      const slots = Array.from({ length: 5 }, (_, index) => ({
        starts_at: `2030-06-14 1${index}:00:00`,
        ends_at: `2030-06-14 1${index}:45:00`,
        stage: 'Main Stage',
        title: `Session ${index + 1}`,
        description: Array.from({ length: 10 }, (_, paragraph) => `<p>Part ${paragraph + 1} of a session description long enough to fill the dialog.</p>`).join(''),
        people: [],
      }));
      await api.importLineup(event.eventId, { people: [], slots });

      await openEmbedHost(page, baseURL!, `${SPACER}${widgetMarkup({ id: String(event.eventId), 'widget-type': 'schedule' })}${SPACER}`);
      const schedule = await resolveWidgetFrame(page, 'Hi.Events Schedule');
      await expect(schedule.getByTestId('event-schedule-item')).toHaveCount(5);
      const collapsedHeight = await settledIframeHeight(page, 'Hi.Events Schedule');

      await scrollIframeTopAboveViewport(page, 'Hi.Events Schedule', 200);
      await schedule.getByRole('button', { name: 'Session 4' }).click();

      const dialog = schedule.getByRole('dialog', { name: 'Session 4' });
      await expect(dialog.getByRole('heading', { name: 'Session 4', level: 3 })).toBeVisible();
      await expect.poll(() => iframeHeight(page, 'Hi.Events Schedule')).toBeGreaterThan(collapsedHeight);
      await expect.poll(async () => (await dialog.boundingBox())!.height).toBeGreaterThan(300);

      const dialogBox = (await dialog.boundingBox())!;
      const closeBox = (await dialog.getByRole('button', { name: 'Close' }).boundingBox())!;
      expect(dialogBox.y).toBeGreaterThanOrEqual(0);
      expect(dialogBox.y + dialogBox.height).toBeLessThanOrEqual(viewport.height);
      expect(closeBox.y).toBeGreaterThanOrEqual(0);

      await dialog.getByRole('button', { name: 'Close' }).click();
      await expect(dialog).toBeHidden();
      await expect.poll(() => iframeHeight(page, 'Hi.Events Schedule')).toBe(collapsedHeight);
    });

    test('a profile with a long bio in a one-person lineup widget gets a full-size dialog', async ({ page, api, account, baseURL }) => {
      const event = await createLiveEventWithFreeTicket(api, account.organizerId);
      const name = uniqueName('Solo Headliner');
      const profile = await api.createLineupProfile(account.organizerId, {
        name,
        bio: Array.from({ length: 12 }, (_, index) => `<p>Chapter ${index + 1} of a biography long enough to need its own scroll area.</p>`).join(''),
      });
      await api.addLineupEntry(event.eventId, { profile_id: profile.id, billing_tier: 'FEATURED' });

      await openEmbedHost(page, baseURL!, `${SPACER}${widgetMarkup({ id: String(event.eventId), 'widget-type': 'lineup' })}${SPACER}`);
      const lineup = await resolveWidgetFrame(page, 'Hi.Events Lineup');
      const card = lineup.getByRole('button', { name: `About ${name}` });
      await expect(card).toBeVisible();
      const collapsedHeight = await settledIframeHeight(page, 'Hi.Events Lineup');
      await card.click();

      const dialog = lineup.getByRole('dialog', { name });
      await expect(dialog.getByRole('heading', { name, level: 3 })).toBeVisible();
      await expect.poll(async () => (await dialog.boundingBox())!.height).toBeGreaterThan(Math.max(collapsedHeight, 300));

      const dialogBox = (await dialog.boundingBox())!;
      expect(dialogBox.y).toBeGreaterThanOrEqual(0);
      expect(dialogBox.y + dialogBox.height).toBeLessThanOrEqual(viewport.height);

      await page.keyboard.press('Escape');
      await expect(dialog).toBeHidden();
      await expect.poll(() => iframeHeight(page, 'Hi.Events Lineup')).toBe(collapsedHeight);
    });
  });
}
