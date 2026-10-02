import {readFileSync} from 'node:fs';
import {fileURLToPath} from 'node:url';
import {expect, test} from '@playwright/test';
import {excessCompanionSeats} from '../../../frontend/src/ee/seating/components/lib/companionRule';

interface CompanionRuleCase {
  name: string;
  seats: ('standard' | 'wheelchair' | 'companion')[];
  excess: number;
}

const cases: CompanionRuleCase[] = JSON.parse(readFileSync(
  fileURLToPath(new URL('../../../backend/tests/Fixtures/seating/companion-rule-cases.json', import.meta.url)),
  'utf8',
));

test.describe('companion seat rule matches the backend fixture', {tag: '@smoke'}, () => {
  for (const companionCase of cases) {
    test(companionCase.name, () => {
      expect(excessCompanionSeats(companionCase.seats.map(kind => ({acc: kind === 'wheelchair', comp: kind === 'companion'}))))
        .toBe(companionCase.excess);
    });
  }
});
