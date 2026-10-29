import { expect, test } from '@playwright/test';
import { checkContrast, getDerivedColors } from '../../../frontend/src/utilites/themeUtils';

test.describe('event theme low-emphasis text', () => {
  for (const mode of ['light', 'dark'] as const) {
    test(`tertiary text on the ${mode} surface passes AA`, () => {
      const { textTertiary, surface } = getDerivedColors(mode);

      expect(checkContrast(textTertiary, surface).ratio).toBeGreaterThanOrEqual(4.5);
    });

    test(`secondary text on the ${mode} surface passes AA`, () => {
      const { textSecondary, surface } = getDerivedColors(mode);

      expect(checkContrast(textSecondary, surface).ratio).toBeGreaterThanOrEqual(4.5);
    });
  }
});
