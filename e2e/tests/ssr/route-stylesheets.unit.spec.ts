import {expect, test} from '@playwright/test';
import {routeStylesheets} from '../../../frontend/src/ssr/routeStylesheets.js';

const manifest = {
  'index.html': {file: 'assets/index.js', isEntry: true, css: ['assets/index.css']},
  'src/components/layouts/PublicEvent/index.tsx': {
    file: 'assets/public-event.js',
    isDynamicEntry: true,
    css: ['assets/public-event.css'],
    imports: ['index.html', '_frame.js'],
  },
  '_frame.js': {file: 'assets/frame.js', css: ['assets/frame.css'], imports: ['_shared.js']},
  '_shared.js': {file: 'assets/shared.js', css: ['assets/frame.css', 'assets/shared.css'], imports: ['_frame.js']},
};

test('a route links its own stylesheets and those of everything it imports, once each', () => {
  expect(routeStylesheets(manifest, ['src/components/layouts/PublicEvent/index.tsx'])).toEqual([
    'assets/public-event.css',
    'assets/frame.css',
    'assets/shared.css',
  ]);
});

test('the entry stylesheet the page template already links is left out', () => {
  expect(routeStylesheets(manifest, ['index.html'])).toEqual([]);
});

test('an unknown route or a missing manifest links nothing', () => {
  expect(routeStylesheets(manifest, ['src/unknown.tsx'])).toEqual([]);
  expect(routeStylesheets({}, ['src/components/layouts/PublicEvent/index.tsx'])).toEqual([]);
});
