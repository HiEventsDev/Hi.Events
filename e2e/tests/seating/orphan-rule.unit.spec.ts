import {readFileSync} from 'node:fs';
import {fileURLToPath} from 'node:url';
import {expect, test} from '@playwright/test';
import {findOrphanSeats} from '../../../frontend/src/ee/seating/components/lib/orphanRule';
import {indexLayout} from '../../../frontend/src/ee/seating/components/lib/layoutIndex';
import type {SeatMapLayout} from '../../../frontend/src/ee/seating/components/lib/types';

interface OrphanRuleCase {
  name: string;
  segment: string[];
  taken: string[];
  selected: string[];
  orphans: string[];
}

const fixture = (name: string): string =>
  readFileSync(fileURLToPath(new URL(`../../../backend/tests/Fixtures/seating/${name}.json`, import.meta.url)), 'utf8');

const cases: OrphanRuleCase[] = JSON.parse(fixture('orphan-rule-cases'));

test.describe('orphan seat rule matches the backend fixture', { tag: '@smoke' }, () => {
  for (const orphanCase of cases) {
    test(orphanCase.name, () => {
      expect(findOrphanSeats([orphanCase.segment], orphanCase.taken, orphanCase.selected)).toEqual(orphanCase.orphans);
    });
  }

  test('orphans are collected across segments', () => {
    expect(findOrphanSeats(
      [['a1', 'a2', 'a3', 'a4', 'a5'], ['b1', 'b2', 'b3', 'b4', 'b5'], ['c1', 'c2', 'c3']],
      ['c2'],
      ['a2', 'a3', 'a4', 'b1', 'b2', 'b3'],
    )).toEqual(['a1', 'a5']);
  });

  test('segments are derived the same way as the backend', () => {
    const layout: SeatMapLayout = JSON.parse(fixture('theatre'));

    expect(indexLayout(layout).segments).toEqual(JSON.parse(fixture('theatre-segments')));
  });
});
