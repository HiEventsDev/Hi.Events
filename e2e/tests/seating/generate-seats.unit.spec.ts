import {readFileSync} from 'node:fs';
import {register} from 'node:module';
import {fileURLToPath} from 'node:url';
import {expect, test} from '@playwright/test';
import {generateSeats} from '../../../frontend/src/ee/seating/components/lib/generateSeats';
import type {GeneratedSeat, SeatedElement, SeatMapLayout} from '../../../frontend/src/ee/seating/components/lib/types';

interface NumberingCase {
  name: string;
  element: SeatedElement;
  expected: Pick<GeneratedSeat, 'uid' | 'n' | 'label'>[];
}

const fixture = (name: string): string =>
  readFileSync(fileURLToPath(new URL(`../../../backend/tests/Fixtures/seating/${name}.json`, import.meta.url)), 'utf8');

const linguiMacroStub = `data:text/javascript,${encodeURIComponent(
  'export const t = (strings, ...values) => strings.reduce((text, part, i) => text + part + (i < values.length ? values[i] : ""), "");',
)}`;

register(`data:text/javascript,${encodeURIComponent(
  `export async function resolve(specifier, context, next) {
    return specifier === '@lingui/macro' ? {url: ${JSON.stringify(linguiMacroStub)}, shortCircuit: true} : next(specifier, context);
  }`,
)}`);

const seatsOf = (layout: SeatMapLayout): GeneratedSeat[] =>
  layout.areas.flatMap(area => area.elements.flatMap(element => ('seats' in element ? element.seats : [])));

test.describe('seat numbering matches the backend fixture', {tag: '@smoke'}, () => {
  const cases: NumberingCase[] = JSON.parse(fixture('numbering-cases'));

  for (const numberingCase of cases) {
    test(numberingCase.name, () => {
      expect(generateSeats(numberingCase.element).map(({uid, n, label}) => ({uid, n, label}))).toEqual(numberingCase.expected);
    });
  }
});

test.describe('templates still generate the stored fixtures', {tag: '@smoke'}, () => {
  test('every template matches its fixture seat for seat', async () => {
    const {createSeatMapFromTemplate, SEAT_MAP_TEMPLATE_NAMES} = await import('../../../frontend/src/ee/seating/components/lib/templates');

    for (const name of SEAT_MAP_TEMPLATE_NAMES) {
      const generated = seatsOf(createSeatMapFromTemplate(name));
      const stored = seatsOf(JSON.parse(fixture(name)));

      expect(generated.map(({uid, n, label, band}) => ({uid, n, label, band})), name)
        .toEqual(stored.map(({uid, n, label, band}) => ({uid, n, label, band})));
      stored.forEach((seat, position) => {
        expect(generated[position].x, `${name} ${seat.uid} x`).toBeCloseTo(seat.x, 2);
        expect(generated[position].y, `${name} ${seat.uid} y`).toBeCloseTo(seat.y, 2);
      });
    }
  });
});

test.describe('moving elements matches regenerating them', {tag: '@smoke'}, () => {
  test('translated seats land where a fresh generation puts them', async () => {
    const {createSeatMapFromTemplate, SEAT_MAP_TEMPLATE_NAMES} = await import('../../../frontend/src/ee/seating/components/lib/templates');
    const {moveElements, regenerate} = await import('../../../frontend/src/ee/seating/components/SeatMapDesigner/ops/elements');

    for (const name of SEAT_MAP_TEMPLATE_NAMES) {
      const layout = createSeatMapFromTemplate(name);
      for (const area of layout.areas) {
        const moved = moveElements(layout, area.id, area.elements.map(element => element.id), 37, -21);
        const movedArea = moved.areas.find(candidate => candidate.id === area.id)!;

        for (const element of movedArea.elements) {
          if (!('seats' in element)) {
            continue;
          }
          const regenerated = regenerate(element).seats;
          expect(element.seats.map(({uid, label}) => ({uid, label})), `${name} ${element.id}`)
            .toEqual(regenerated.map(({uid, label}) => ({uid, label})));
          element.seats.forEach((seat, position) => {
            expect(seat.x, `${name} ${seat.uid} x`).toBeCloseTo(regenerated[position].x, 1);
            expect(seat.y, `${name} ${seat.uid} y`).toBeCloseTo(regenerated[position].y, 1);
          });
        }
      }
    }
  });
});
