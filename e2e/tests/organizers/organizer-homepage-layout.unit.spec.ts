import { expect, test } from '@playwright/test';
import {
  EVENT_DEFAULT_SECTIONS,
  ORGANIZER_DEFAULT_SECTIONS,
  organizerHomepageLayout,
  withDefaultSections,
} from '../../../frontend/src/utilites/homepageSectionLayout';
import type { HomepageSection, HomepageSectionType } from '../../../frontend/src/types';

const section = (type: HomepageSectionType, enabled = true): HomepageSection => ({ id: type.toLowerCase(), type, enabled });

const describe = (layout: ReturnType<typeof organizerHomepageLayout>) => ({
  heroAbout: !!layout.heroAbout,
  heroContact: !!layout.heroContact,
  blocks: layout.blocks.map((block) => (block.kind === 'events'
    ? `EVENTS${block.includesPastEvents ? '+PAST' : ''}`
    : block.section.type)),
});

test.describe('organizer homepage layout', () => {
  test('the default layout matches the classic organizer page', () => {
    const layout = organizerHomepageLayout(withDefaultSections(null, ORGANIZER_DEFAULT_SECTIONS));

    expect(describe(layout)).toEqual({ heroAbout: true, heroContact: true, blocks: ['EVENTS+PAST'] });
  });

  test('about and contact leave the profile card once something comes before them', () => {
    const layout = organizerHomepageLayout([
      section('CONTACT'),
      section('TEXT'),
      section('ABOUT'),
      section('EVENTS'),
      section('PAST_EVENTS'),
    ]);

    expect(describe(layout)).toEqual({ heroAbout: false, heroContact: true, blocks: ['TEXT', 'ABOUT', 'EVENTS+PAST'] });
  });

  test('past events become their own section when moved away from the events list', () => {
    const layout = organizerHomepageLayout([
      section('ABOUT'),
      section('CONTACT'),
      section('EVENTS'),
      section('GALLERY'),
      section('PAST_EVENTS'),
    ]);

    expect(describe(layout).blocks).toEqual(['EVENTS', 'GALLERY', 'PAST_EVENTS']);
  });

  test('hidden sections are skipped but the events list never is', () => {
    const layout = organizerHomepageLayout([
      section('ABOUT', false),
      section('VIDEO'),
      section('CONTACT'),
      section('EVENTS', false),
      section('PAST_EVENTS', false),
    ]);

    expect(describe(layout)).toEqual({ heroAbout: false, heroContact: false, blocks: ['VIDEO', 'CONTACT', 'EVENTS'] });
  });

  test('missing built-ins are restored at their default position', () => {
    const organizer = withDefaultSections([section('EVENTS'), section('FAQ')], ORGANIZER_DEFAULT_SECTIONS);
    const event = withDefaultSections([section('TEXT'), section('TICKETS')], EVENT_DEFAULT_SECTIONS);

    expect(organizer.map((item) => item.type)).toEqual(['ABOUT', 'CONTACT', 'EVENTS', 'PAST_EVENTS', 'FAQ']);
    expect(event.map((item) => item.type)).toEqual(['TEXT', 'TICKETS', 'ABOUT', 'LINEUP', 'SCHEDULE', 'LOCATION', 'ORGANIZER', 'MORE_EVENTS']);
  });
});
