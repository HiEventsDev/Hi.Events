import { expect, test } from '../../fixtures';

test.describe('API reference landmarks', () => {
  for (const width of [390, 1440]) {
    test(`at ${width}px the API reference sits inside the one main landmark`, async ({ freshAccount }) => {
      const page = await freshAccount.newAuthedPage();
      await page.setViewportSize({ width, height: 900 });
      await page.goto('/settings/api-keys/reference');

      const reference = page.getByTestId('api-reference');
      await expect(reference.getByRole('heading', { name: /API$/, level: 1 })).toBeVisible({ timeout: 30_000 });

      await expect(page.getByRole('main')).toHaveCount(1);
      await expect(page.getByRole('main')).toHaveAttribute('id', 'app-manage-main');
      await expect(reference.getByRole('region', { name: /^API documentation for / })).toBeVisible();
    });
  }
});
