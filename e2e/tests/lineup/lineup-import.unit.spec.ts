import { expect, test } from '@playwright/test';
import {
  buildImportPlan,
  ImportContext,
  importPayload,
  nameKey,
  parseDateCell,
  parseTimeCell,
  PEOPLE_COLUMN_ALIASES,
  peopleToAdd,
  plainTextToHtml,
  SCHEDULE_COLUMN_ALIASES,
  slotKey,
  splitLinks,
} from '../../../frontend/src/utilites/lineupImport';
import { decodeText, parseDelimitedText, SpreadsheetCell, SpreadsheetSheet } from '../../../frontend/src/utilites/spreadsheetReader';

const context = (overrides: Partial<ImportContext> = {}): ImportContext => ({
  profiles: new Map(),
  lineupNames: new Set(),
  stageNames: new Set(),
  existingSlotKeys: new Set(),
  billingLabels: { Headliner: 'HEADLINER' },
  yesWords: [],
  dayFirst: true,
  peopleAliases: PEOPLE_COLUMN_ALIASES,
  scheduleAliases: SCHEDULE_COLUMN_ALIASES,
  ...overrides,
});

const sheet = (name: string, rows: SpreadsheetCell[][], extra: Partial<SpreadsheetSheet> = {}): SpreadsheetSheet => ({
  name,
  rows: rows.map((cells, index) => ({ number: index + 1, cells })),
  ...extra,
});

test.describe('lineup import parsing', () => {
  test('reads quoted CSV, semicolons and tabs and keeps original row numbers', () => {
    expect(parseDelimitedText('﻿Name,Bio\r\n"Nova, Lights","Line one\nLine ""two"""\r\n\r\nAda,x')).toEqual([
      { number: 1, cells: ['Name', 'Bio'] },
      { number: 2, cells: ['Nova, Lights', 'Line one\nLine "two"'] },
      { number: 4, cells: ['Ada', 'x'] },
    ]);
    expect(parseDelimitedText('Name;Billing\nAda;Headliner').map(row => row.cells)).toEqual([['Name', 'Billing'], ['Ada', 'Headliner']]);
    expect(parseDelimitedText('Start\tEnd\n19:00\t20:00').map(row => row.cells)).toEqual([['Start', 'End'], ['19:00', '20:00']]);
  });

  test('decodes UTF-8, UTF-16 and Windows-1252 CSV bytes', () => {
    expect(decodeText(new TextEncoder().encode('Beyoncé'))).toBe('Beyoncé');
    expect(decodeText(new Uint8Array([0x42, 0x65, 0x79, 0x6f, 0x6e, 0x63, 0xe9]))).toBe('Beyoncé');
    expect(decodeText(new Uint8Array([0xff, 0xfe, 0x41, 0x00, 0xe9, 0x00]))).toBe('Aé');
  });

  test('understands spreadsheet dates and times', () => {
    expect(parseDateCell('2030-06-14', true)).toBe('2030-06-14');
    expect(parseDateCell('14/06/2030', true)).toBe('2030-06-14');
    expect(parseDateCell('06/14/2030', false)).toBe('2030-06-14');
    expect(parseDateCell(47648, true)).toBe('2030-06-14');
    expect(parseDateCell(46186, true, true)).toBe('2030-06-14');
    expect(parseDateCell('14 June 2030', true)).toBe('2030-06-14');
    expect(parseDateCell('31/02/2030', true)).toBeNull();
    expect(parseTimeCell('19:30')).toBe(1170);
    expect(parseTimeCell('7:30 PM')).toBe(1170);
    expect(parseTimeCell('12am')).toBe(0);
    expect(parseTimeCell(0.8125)).toBe(1170);
    expect(parseTimeCell(19)).toBe(1140);
    expect(parseTimeCell('2030-06-14 19:30')).toBe(1170);
    expect(parseTimeCell('14/06/2030 19:30')).toBe(1170);
    expect(parseTimeCell('25:00')).toBeNull();
    expect(parseTimeCell('soon')).toBeNull();
  });

  test('builds a plan with overnight slots, new people, new stages and row errors', () => {
    const plan = buildImportPlan([sheet('Schedule', [
      ['Date', 'Start', 'End', 'Stage', 'Title', 'People'],
      ['2030-06-14', '23:00', '00:30', 'Main Stage', '', 'Nova; Ada'],
      ['2030-06-14', '19:00', '', 'main stage', 'Doors', ''],
      ['2030-06-14', '19:00', '19:30', 'Tent', 'Doors', ''],
      ['2030-06-14', '19:00', '19:30', 'Tent', 'Doors', ''],
      ['2030-06-14', 'later', '20:00', '', 'Broken', ''],
      ['2030-06-14', '20:00', '21:00', '', '', ''],
      ['2030-06-14', '20:00', '21:00', 'S'.repeat(101), 'Long stage', ''],
    ]), sheet('People', [
      ['Name', 'Billing', 'Links', 'Hidden'],
      ['Nova', 'Headliner', 'www.nova.example.com http://bad.example.com nope', 'yes'],
      ['nova', '', '', ''],
      ['', 'Featured', '', ''],
    ])], context({
      stageNames: new Set(['tent']),
      profiles: new Map([['ada', { tagline: null, bioText: '', links: [] }]]),
    }));

    expect(plan.slots.map(slot => slot.status)).toEqual(['new', 'error', 'new', 'duplicate', 'error', 'error', 'error']);
    expect(plan.slots[0]).toMatchObject({ row: 2, startsAt: '2030-06-14 23:00:00', endsAt: '2030-06-15 00:30:00', newPeople: [] });
    expect(plan.slots.map(slot => slot.error)).toEqual([undefined, 'missing_end', undefined, undefined, 'bad_time', 'empty_slot', 'too_long']);
    expect(plan.newStages).toEqual(['Main Stage']);
    expect(plan.people.map(person => person.status)).toEqual(['new', 'duplicate', 'error']);
    expect(plan.people[0]).toMatchObject({
      billingTier: 'HEADLINER',
      isHidden: true,
      links: ['https://www.nova.example.com', 'https://bad.example.com'],
    });
    expect(peopleToAdd(plan)).toBe(1);

    const { payload, rows } = importPayload(plan);
    expect(payload.people.map(person => person.name)).toEqual(['Nova']);
    expect(payload.slots).toHaveLength(2);
    expect(rows.slots.map(slot => slot.row)).toEqual([2, 4]);
  });

  test('matches names the same way the server does', () => {
    expect(nameKey('  Jay-Z ')).toBe('jay-z');
    expect(nameKey('Jay  Z')).toBe('jay z');
    const plan = buildImportPlan([sheet('People', [['Name'], ['Jay Z'], ['AC/DC'], ['AC DC']])], context({
      profiles: new Map([['jay-z', { tagline: null, bioText: '', links: [] }]]),
    }));
    expect(plan.people.map(person => person.status)).toEqual(['new', 'new', 'new']);
  });

  test('finds headers below title rows and reads singular and range columns', () => {
    const plan = buildImportPlan([sheet('Running order', [
      ['Lakeside Sound 2030'],
      [],
      ['Date', 'Time', 'Stage', 'Artist'],
      ['14/06/2030', '19:00 - 20:15', 'Main', 'Nova'],
      ['14/06/2030', '21:00 to 22:00', 'Main', 'Ada'],
    ].filter(cells => cells.length > 0))], context());

    expect(plan.slots.map(slot => [slot.startsAt, slot.endsAt, slot.people])).toEqual([
      ['2030-06-14 19:00:00', '2030-06-14 20:15:00', ['Nova']],
      ['2030-06-14 21:00:00', '2030-06-14 22:00:00', ['Ada']],
    ]);
  });

  test('a people sheet with a From column is not mistaken for a schedule', () => {
    const plan = buildImportPlan([sheet('People', [['Name', 'Bio', 'From'], ['Ada', 'Engineer', 'London']])], context());
    expect(plan.slots).toHaveLength(0);
    expect(plan.people.map(person => person.name)).toEqual(['Ada']);
  });

  test('durations accept minutes and h:mm, and 1904 workbooks shift dates', () => {
    const plan = buildImportPlan([sheet('Schedule', [
      ['Date', 'Start', 'Duration (min)', 'Title'],
      [46186, 0.8125, 0.0625, 'Talk'],
      ['2030-06-14', '23:30', '45', 'Late'],
    ], { date1904: true })], context());

    expect(plan.slots.map(slot => [slot.startsAt, slot.endsAt])).toEqual([
      ['2030-06-14 19:30:00', '2030-06-14 21:00:00'],
      ['2030-06-14 23:30:00', '2030-06-15 00:15:00'],
    ]);
  });

  test('flags ambiguous dates so the organizer can choose the order', () => {
    const rows: SpreadsheetCell[][] = [['Date', 'Start', 'End', 'Title'], ['06/07/2030', '19:00', '20:00', 'Talk']];
    expect(buildImportPlan([sheet('S', rows)], context()).hasAmbiguousDates).toBe(true);
    expect(buildImportPlan([sheet('S', rows)], context({ dayFirst: true })).slots[0].startsAt).toBe('2030-07-06 19:00:00');
    expect(buildImportPlan([sheet('S', rows)], context({ dayFirst: false })).slots[0].startsAt).toBe('2030-06-07 19:00:00');
    expect(buildImportPlan([sheet('S', [['Date', 'Start', 'End', 'Title'], ['14/06/2030', '19:00', '20:00', 'Talk']])], context()).hasAmbiguousDates).toBe(false);
  });

  test('people already on the lineup are only updated when something differs', () => {
    const plan = buildImportPlan([sheet('People', [
      ['Name', 'Tagline', 'Bio', 'Billing', 'Links'],
      ['Nova', 'Techno', 'Loud & late', 'HEADLINER', 'https://nova.example.com'],
      ['Ada', 'New tagline', '', '', ''],
    ])], context({
      lineupNames: new Set(['nova', 'ada']),
      profiles: new Map([
        ['nova', { tagline: 'Techno', bioText: 'loud & late', links: ['https://nova.example.com'], billingTier: 'HEADLINER', roleLabel: null, isHidden: false }],
        ['ada', { tagline: 'Old tagline', bioText: '', links: [], billingTier: 'STANDARD', roleLabel: null, isHidden: false }],
      ]),
    }));

    expect(plan.people.map(person => person.status)).toEqual(['unchanged', 'update']);
    expect(importPayload(plan).payload.people.map(person => person.name)).toEqual(['Ada']);
  });

  test('marks rows that already exist on the schedule', () => {
    const existing = slotKey('2030-06-14 19:00:00', '2030-06-14 19:30:00', 'Tent', 'Doors', []);
    const plan = buildImportPlan([sheet('Schedule', [['Start', 'Duration (min)', 'Stage', 'Title'], ['2030-06-14 19:00', '30', 'tent', 'doors']])],
      context({ existingSlotKeys: new Set([existing]) }));

    expect(plan.slots[0].status).toBe('duplicate');
  });

  test('turns plain text into safe paragraphs and keeps https links only', () => {
    expect(plainTextToHtml('One <b>\n\nTwo\nlines')).toBe('<p>One &lt;b&gt;</p><p>Two<br>lines</p>');
    expect(splitLinks('https://a.example.com, javascript:alert(1); ftp://x')).toEqual(['https://a.example.com']);
  });
});
