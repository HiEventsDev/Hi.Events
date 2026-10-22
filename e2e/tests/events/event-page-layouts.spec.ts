import fs from 'node:fs';
import path from 'node:path';
import type { Browser, Page, TestInfo } from '@playwright/test';
import { test, expect } from '../../fixtures';
import { createFreshOrganizer, futureStartDate } from '../../api/factory';
import type { ApiClient } from '../../api/api-client';
import { renderPosterArt } from '../../utils/posterArt';
import { stressImageFit } from '../../utils/imageFit';
import { uniqueName } from '../../utils/unique';
import { grantedConsentCookie } from '../../utils/consent';
import { getDerivedColors } from '../../../frontend/src/utilites/themeUtils';

type Layout = 'MARQUEE' | 'FLYER' | 'STORY' | 'INVITE';

interface Theme {
  accent: string;
  background: string;
  mode: 'light' | 'dark';
}

interface Seeded {
  kind: string;
  eventId: number;
  slug: string;
  title: string;
  theme: Theme;
  hasCover: boolean;
}

const VIEWPORTS = [
  { name: 'phone', width: 390, height: 844 },
  { name: 'tablet', width: 820, height: 1180 },
  { name: 'desktop', width: 1440, height: 900 },
] as const;

type Viewport = typeof VIEWPORTS[number];

const LONG_COPY = [
  'Three nights of new music in the old harbour warehouse, with the bar open from six and the first set at seven.',
  'Every ticket includes a drink on arrival. Doors close at eleven and the last ferry leaves at half past.',
  'The venue has step-free access throughout, with seating along the back wall and a quiet room upstairs.',
].map(paragraph => `<p>${paragraph}</p>`).join('');

const MINUTE = 60_000;

const utcStamp = (date: Date): string => date.toISOString().slice(0, 16).replace('T', ' ') + ':00';

const slotAt = (start: Date, fromMinutes: number, toMinutes: number) => ({
  starts_at: utcStamp(new Date(start.getTime() + fromMinutes * MINUTE)),
  ends_at: utcStamp(new Date(start.getTime() + toMinutes * MINUTE)),
});

const seedEvent = async (
  api: ApiClient,
  browser: Browser,
  organizerId: number,
  layout: Layout,
  spec: { kind: string; theme: Theme; cover?: { width: number; height: number }; description?: string; tickets: number[]; rich?: boolean },
): Promise<Seeded> => {
  const title = uniqueName(`${spec.kind} night`);
  const startDate = futureStartDate();
  const event = await api.createEvent({
    title,
    type: 'SINGLE',
    organizer_id: organizerId,
    start_date: startDate,
    category: 'MUSIC',
    currency: 'USD',
    timezone: 'UTC',
    ...(spec.description ? { description: spec.description } : {}),
  });

  const [category] = await api.listProductCategories(event.id);
  for (const [index, price] of spec.tickets.entries()) {
    await api.createProduct(event.id, {
      title: ['General admission', 'Balcony', 'Backstage pass'][index],
      product_type: 'TICKET',
      type: price > 0 ? 'PAID' : 'FREE',
      product_category_id: category.id,
      prices: [{ price }],
    });
  }

  if (spec.cover) {
    const buffer = await renderPosterArt(browser, { ...spec.cover, title: 'The Paper Orchard', background: '#1f3a2e', ink: '#f3e7c9' });
    await api.uploadEventImage(event.id, { name: 'cover.jpg', mimeType: 'image/jpeg', buffer }, 'EVENT_COVER');
  }

  if (spec.rich) {
    const start = new Date(startDate);
    await api.importLineup(event.id, {
      people: [
        { name: 'Velvet Harbour', billing_tier: 'HEADLINER' },
        { name: 'Ada Byron', billing_tier: 'HEADLINER' },
        { name: 'The Lantern Club', billing_tier: 'FEATURED' },
        { name: 'Mira Osei', billing_tier: 'FEATURED' },
        { name: 'Tidewater', billing_tier: 'FEATURED' },
        { name: 'June Calloway' },
        { name: 'Nils Varga' },
        { name: 'Ruth Abara' },
      ],
      slots: [
        { ...slotAt(start, 0, 45), stage: 'Main Stage', people: ['June Calloway'] },
        { ...slotAt(start, 60, 120), stage: 'Main Stage', people: ['Ada Byron'] },
        { ...slotAt(start, 150, 240), stage: 'Main Stage', people: ['Velvet Harbour'] },
      ],
    });
  }

  await api.updateEventSettings(event.id, {
    homepage_theme_settings: { ...spec.theme, background_type: 'COLOR', layout },
    homepage_sections: [
      { id: 'tickets', type: 'TICKETS', enabled: true },
      { id: 'about', type: 'ABOUT', enabled: true },
      { id: 'lineup', type: 'LINEUP', enabled: true },
      { id: 'schedule', type: 'SCHEDULE', enabled: true },
      ...(spec.rich ? [
        { id: 'travel', type: 'TEXT', enabled: true, heading: 'Getting there', body: '<p>The 42 bus stops outside, and the ferry runs until half past eleven.</p>' },
        { id: 'faq', type: 'FAQ', enabled: true, heading: 'Good to know', faqs: [{ question: 'Is there a cloakroom?', answer: '<p>Yes, by the main doors.</p>' }, { question: 'Can I bring a camera?', answer: '<p>Phones and small cameras only.</p>' }] },
      ] : []),
      { id: 'organizer', type: 'ORGANIZER', enabled: true },
      { id: 'more_events', type: 'MORE_EVENTS', enabled: true },
    ],
  });
  await api.publishEvent(event.id);

  return { kind: spec.kind, eventId: event.id, slug: event.slug, title, theme: spec.theme, hasCover: !!spec.cover };
};

const seedEvents = async (api: ApiClient, browser: Browser, layout: Layout): Promise<Seeded[]> => {
  const organizer = await createFreshOrganizer(api);
  await api.updateOrganizerStatus(organizer.id, 'LIVE');

  return [
    await seedEvent(api, browser, organizer.id, layout, {
      kind: 'Flyer',
      theme: { accent: '#0f766e', background: '#f0fdfa', mode: 'light' },
      cover: { width: 1080, height: 1350 },
      description: LONG_COPY,
      tickets: [0, 25, 45],
      rich: true,
    }),
    await seedEvent(api, browser, organizer.id, layout, {
      kind: 'Conference',
      theme: { accent: '#1d4ed8', background: '#eff6ff', mode: 'light' },
      cover: { width: 1600, height: 900 },
      description: LONG_COPY,
      tickets: [120, 240],
    }),
    await seedEvent(api, browser, organizer.id, layout, {
      kind: 'Bare',
      theme: { accent: '#f472b6', background: '#0f0f14', mode: 'dark' },
      tickets: [0],
    }),
  ];
};

const rgb = (hex: string) => {
  const value = parseInt(hex.slice(1), 16);
  return `rgb(${(value >> 16) & 255}, ${(value >> 8) & 255}, ${value & 255})`;
};

const measure = (page: Page) => page.evaluate(() => {
  const box = (element: Element | null) => {
    if (!element) {
      return null;
    }
    const rect = element.getBoundingClientRect();
    return { x: rect.x, y: rect.y + window.scrollY, width: rect.width, height: rect.height, right: rect.right, bottom: rect.bottom + window.scrollY };
  };
  const intro = document.querySelector('main h1')!.closest('header')!;
  const column = intro.parentElement!;
  const tickets = document.getElementById('tickets');
  const flow = Array.from(column.children)
    .filter(child => child !== tickets || getComputedStyle(child).position !== 'sticky')
    .map(box)
    .filter(rect => rect && rect.height > 0)
    .sort((a, b) => a!.y - b!.y);
  const gaps = flow.slice(1).map((rect, index) => Math.round(rect!.y - flow[index]!.bottom));
  const sheet = box(document.querySelector('main article'));
  const poster = box(document.querySelector('[class*="posterColumn"]'));
  const contentBottom = Math.max(...[...flow, box(tickets), poster].filter(rect => rect).map(rect => rect!.bottom));

  return {
    overflow: document.documentElement.scrollWidth - document.documentElement.clientWidth,
    title: box(document.querySelector('main h1')),
    tickets: box(tickets),
    sheet,
    poster,
    gaps,
    footerGap: Math.round(box(document.querySelector('main footer'))!.y - contentBottom),
    footerAtSheetBottom: Math.abs(box(document.querySelector('main footer'))!.bottom - sheet!.bottom) <= 1.5,
    spaceBelowFooterContent: Math.round(sheet!.bottom - box(document.querySelector('main footer')!.lastElementChild)!.bottom),
    fitsViewport: sheet!.bottom <= window.innerHeight + 1,
    bodyBackground: getComputedStyle(document.body).backgroundColor,
    sheetBackground: getComputedStyle(document.querySelector('main article')!).backgroundColor,
    accentBand: getComputedStyle(document.querySelector('[class*="dateTileMonth"]') ?? document.body).backgroundColor,
    titleAlign: getComputedStyle(document.querySelector('main h1')!).textAlign,
  };
});

const shotsDir = process.env.LAYOUT_SHOTS_DIR;

const attachShot = async (page: Page, testInfo: TestInfo, name: string) => {
  const body = await page.screenshot({ fullPage: true });
  await testInfo.attach(name, { body, contentType: 'image/png' });
  return body;
};

const saveShot = async (page: Page, testInfo: TestInfo, name: string) => {
  if (!shotsDir) {
    return;
  }
  const body = await attachShot(page, testInfo, name);
  fs.mkdirSync(shotsDir, { recursive: true });
  fs.writeFileSync(path.join(shotsDir, `${name}.png`), body);
};

const viewportState = (page: Page) => page.evaluate(async () => {
  await new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)));
  const inView = (element: Element) => {
    const rect = element.getBoundingClientRect();
    return rect.bottom > 0 && rect.top < window.innerHeight;
  };
  return {
    tickets: inView(document.getElementById('tickets')!),
    footer: inView(document.querySelector('main footer')!),
    titleScrolledAway: document.querySelector('main h1')!.getBoundingClientRect().bottom < 0,
  };
});

const expectStructure = (layout: Layout, viewport: Viewport, event: Seeded, metrics: Awaited<ReturnType<typeof measure>>) => {
  const { title, tickets, sheet, poster } = metrics;
  const beside = (left: { right: number }, right: { x: number }) => right.x >= left.right;

  if (layout === 'MARQUEE' && viewport.name === 'desktop') {
    expect(beside(title!, tickets!)).toBe(true);
  } else {
    expect(tickets!.y).toBeGreaterThanOrEqual(title!.bottom);
    expect(Math.abs(tickets!.x - title!.x)).toBeLessThan(2);
  }

  if (layout === 'FLYER') {
    expect(poster).not.toBeNull();
    if (viewport.name === 'phone') {
      expect(poster!.bottom).toBeLessThanOrEqual(title!.y);
    } else {
      expect(beside(poster!, title!)).toBe(true);
    }
  } else {
    expect(poster).toBeNull();
  }

  if (viewport.name === 'desktop') {
    const widths: Record<Layout, [number, number]> = { MARQUEE: [1000, 1200], FLYER: [1000, 1200], STORY: [700, 801], INVITE: [560, 641] };
    expect(sheet!.width).toBeGreaterThanOrEqual(widths[layout][0]);
    expect(sheet!.width).toBeLessThanOrEqual(widths[layout][1]);
  }

  if (layout === 'INVITE') {
    expect(metrics.titleAlign).toBe('center');
  }

  expect(metrics.bodyBackground).toBe(rgb(event.theme.background));
  expect(metrics.sheetBackground).toBe(rgb(getDerivedColors(event.theme.mode).surface));
  expect(metrics.accentBand).toBe(rgb(event.theme.accent));
};

for (const layout of ['MARQUEE', 'FLYER', 'STORY', 'INVITE'] as Layout[]) {
  test(`the ${layout.toLowerCase()} layout holds together on phones, tablets and desktops`, async ({ page, api, browser }, testInfo) => {
    test.setTimeout(300_000);
    const events = await seedEvents(api, browser, layout);
    await page.context().addCookies([{ ...grantedConsentCookie(), value: 'analytics=0&advertising=0' }]);
    let ticketBarShown = 0;
    let sectionBarShown = 0;
    const hydrationErrors: string[] = [];
    page.on('console', message => {
      if (message.type() === 'error' && /hydrat|#418|#423|#425/i.test(message.text())) {
        hydrationErrors.push(message.text());
      }
    });

    for (const viewport of VIEWPORTS) {
      await page.setViewportSize({ width: viewport.width, height: viewport.height });

      for (const event of events) {
        const shotName = `${layout.toLowerCase()}-${viewport.name}-${event.kind.toLowerCase()}`;
        try {
          await page.goto(`/event/${event.eventId}/${event.slug}`);
          await page.waitForLoadState('networkidle');
          await expect(page.getByRole('heading', { level: 1, name: event.title })).toBeVisible();
          await expect(page.locator('#more-events')).toBeVisible();
          await page.evaluate(() => document.fonts.ready);

          const metrics = await measure(page);
          expect(metrics.overflow, `${event.kind} on ${viewport.name} scrolls sideways`).toBeLessThanOrEqual(0);
          expect(metrics.gaps.every(gap => Math.abs(gap) <= 1), `${event.kind} on ${viewport.name} has gaps ${metrics.gaps}`).toBe(true);
          expect(metrics.footerAtSheetBottom, `${event.kind} on ${viewport.name} footer is not the last thing on the page`).toBe(true);
          expect(metrics.spaceBelowFooterContent, `${event.kind} on ${viewport.name} leaves space under the footer`).toBeLessThanOrEqual(40);
          expect(metrics.fitsViewport || metrics.footerGap <= 96, `${event.kind} on ${viewport.name} leaves ${metrics.footerGap}px above the footer`).toBe(true);
          expectStructure(layout, viewport, event, metrics);

          if (event.hasCover) {
            const cover = page.getByRole('img', { name: event.title });
            await expect(cover).toBeVisible();
            expect(await cover.evaluate(image => getComputedStyle(image).objectFit)).toBe('contain');
          }

          await saveShot(page, testInfo, shotName);

          const getTickets = page.getByRole('button', { name: 'Get Tickets' });
          await page.evaluate(() => {
            const tickets = document.getElementById('tickets')!;
            window.scrollTo({ top: tickets.getBoundingClientRect().bottom + window.scrollY + 40, behavior: 'instant' });
          });
          const pastTickets = await viewportState(page);
          if (!pastTickets.tickets && !pastTickets.footer) {
            ticketBarShown++;
            await expect(getTickets, `${event.kind} on ${viewport.name} has no bar back to the tickets`).toBeVisible();
          } else {
            await expect(getTickets, `${event.kind} on ${viewport.name} shows the tickets bar while the tickets or footer are in view`).toBeHidden();
          }

          await page.mouse.move(viewport.width / 2, viewport.height / 2);
          await page.mouse.wheel(0, 100_000);
          await expect.poll(() => page.evaluate(() => Math.ceil(window.scrollY + window.innerHeight) >= document.documentElement.scrollHeight)).toBe(true);
          await expect(getTickets, `${event.kind} on ${viewport.name} covers the footer`).toBeHidden();
          const atBottom = await viewportState(page);
          const sectionBar = page.getByRole('navigation', { name: 'Sections' });
          if (viewport.name === 'desktop' && layout !== 'INVITE' && atBottom.titleScrolledAway) {
            sectionBarShown++;
            await expect(sectionBar, `${event.kind} on ${viewport.name} has no section bar once the title scrolls away`).toBeVisible();
          } else {
            await expect(sectionBar, `${event.kind} on ${viewport.name} shows the section bar`).toBeHidden();
          }

          await page.mouse.move(0, 0);
          const fit = await stressImageFit(page);
          expect(fit.problems, `${event.kind} on ${viewport.name}`).toEqual([]);
          expect(fit.horizontalOverflow).toBeLessThanOrEqual(0);
        } catch (error) {
          await attachShot(page, testInfo, `${shotName}-failure`).catch(() => undefined);
          throw error;
        }
      }
    }

    expect(ticketBarShown, 'no page scrolled far enough past the tickets to show the bar').toBeGreaterThan(0);
    if (layout !== 'INVITE') {
      expect(sectionBarShown, 'no desktop page scrolled far enough to show the section bar').toBeGreaterThan(0);
    }
    expect(hydrationErrors).toEqual([]);
  });
}
