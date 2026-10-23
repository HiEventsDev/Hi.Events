import { expect, test } from '@playwright/test';
import { DEFAULT_EVENT_LAYOUT, EVENT_LAYOUTS, resolveEventLayout } from '../../../frontend/src/constants/eventLayouts';

test('a saved layout resolves to its definition', () => {
  expect(resolveEventLayout('FLYER')).toMatchObject({ value: 'FLYER', poster: true, ticketRail: false });
  expect(resolveEventLayout('INVITE')).toMatchObject({ value: 'INVITE', width: 'narrow', centeredIntro: true, sectionNav: false });
});

test('events without a layout, or with one that no longer exists, use the default', () => {
  expect(resolveEventLayout(undefined).value).toBe(DEFAULT_EVENT_LAYOUT);
  expect(resolveEventLayout(null).value).toBe(DEFAULT_EVENT_LAYOUT);
  expect(resolveEventLayout('CAROUSEL').value).toBe(DEFAULT_EVENT_LAYOUT);
});

test('only the marquee layout keeps tickets in a side rail', () => {
  expect(EVENT_LAYOUTS.filter(layout => layout.ticketRail).map(layout => layout.value)).toEqual(['MARQUEE']);
  expect(new Set(EVENT_LAYOUTS.map(layout => layout.value)).size).toBe(EVENT_LAYOUTS.length);
});
