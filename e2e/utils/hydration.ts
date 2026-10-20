import type { Page } from '@playwright/test';

export async function waitForHydration(page: Page, selector: string): Promise<void> {
  await page.waitForFunction(
    (target) => {
      const element = document.querySelector(target);
      return element !== null && Object.keys(element).some((key) => key.startsWith('__reactFiber'));
    },
    selector,
  );
}
