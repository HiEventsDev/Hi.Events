import type { BrowserContext, Locator, Page } from '@playwright/test';

declare global {
  interface Window {
    __badgePrintCalls?: number;
    __badgePrintedPageSides?: string[][];
  }
}

export async function stubBrowserPrinting(context: BrowserContext): Promise<void> {
  await context.addInitScript(() => {
    window.__badgePrintCalls = 0;
    window.__badgePrintedPageSides = [];
    window.print = () => {
      window.__badgePrintCalls = (window.__badgePrintCalls ?? 0) + 1;
      const pages = Array.from(document.querySelectorAll<HTMLElement>('[data-testid="badge-print-page"]'));
      window.__badgePrintedPageSides?.push(pages.map((page) => page.dataset.side ?? ''));
      setTimeout(() => window.dispatchEvent(new Event('afterprint')), 50);
    };
  });
}

export const printCalls = (page: Page): Promise<number> => page.evaluate(() => window.__badgePrintCalls ?? 0);

export const lastPrintedPageSides = (page: Page): Promise<string[]> => page.evaluate(
  () => window.__badgePrintedPageSides?.at(-1) ?? [],
);

export class BadgeDesignerPage {
  constructor(private readonly page: Page) {}

  async goto(eventId: number): Promise<void> {
    await this.page.goto(`/manage/event/${eventId}/badges`);
    await this.page.waitForLoadState('networkidle');
  }

  templateOption(template: 'classic' | 'banner' | 'minimal' | 'bold' | 'sidebar' | 'artwork'): Locator {
    return this.page.getByTestId(`badge-template-${template}`);
  }

  get preview(): Locator {
    return this.page.getByTestId('badge-designer-preview').getByTestId('badge');
  }

  lineSource(index: number): Locator {
    return this.page.getByTestId(`badge-line-${index}-source`);
  }

  get addLineButton(): Locator {
    return this.page.getByTestId('badge-add-line-button');
  }

  get addCompanyQuestionsButton(): Locator {
    return this.page.getByTestId('badge-add-company-questions-button');
  }

  ribbonLabelInput(productTitle: string): Locator {
    return this.page.getByLabel(`Badge label for ${productTitle}`);
  }

  get saveButton(): Locator {
    return this.page.getByTestId('designer-save-button');
  }

  get savedStatus(): Locator {
    return this.page.getByText('All changes saved');
  }

  get printBadgesButton(): Locator {
    return this.page.getByTestId('badge-designer-print-button');
  }

  get paperSummary(): Locator {
    return this.page.getByTestId('badge-paper-summary');
  }

  async chooseBackMode(option: string): Promise<void> {
    await this.page.getByTestId('badge-back-mode-select').click();
    await this.page.getByRole('option', { name: option, exact: true }).click();
  }

  async chooseBackPrinting(option: 'Two-sided printer' | 'Fold-over'): Promise<void> {
    await this.page.getByTestId('badge-back-printing-control').getByText(option, { exact: true }).click();
  }

  async chooseLineSource(index: number, option: string): Promise<void> {
    await this.lineSource(index).click();
    await this.page.getByRole('option', { name: option, exact: true }).click();
  }
}

export class PrintBadgesModal {
  constructor(private readonly page: Page) {}

  get dialog(): Locator {
    return this.page.getByRole('dialog', { name: 'Print badges' });
  }

  get summary(): Locator {
    return this.page.getByTestId('print-badges-summary');
  }

  async chooseEveryone(): Promise<void> {
    await this.dialog.getByText('Everyone', { exact: true }).click();
  }

  get openPreviewButton(): Locator {
    return this.page.getByTestId('print-badges-open-preview-button');
  }
}

export class BadgePrintPage {
  constructor(private readonly page: Page) {}

  async goto(eventId: number, query = ''): Promise<void> {
    await this.page.goto(`/manage/event/${eventId}/badges/print${query ? `?${query}` : ''}`);
    await this.page.waitForLoadState('networkidle');
  }

  get badges(): Locator {
    return this.page.getByTestId('badge-print-sheets').getByTestId('badge');
  }

  get summary(): Locator {
    return this.page.getByTestId('badge-print-summary');
  }

  get pages(): Locator {
    return this.page.getByTestId('badge-print-page');
  }

  get backNotice(): Locator {
    return this.page.getByTestId('badge-print-back-notice');
  }

  get printButton(): Locator {
    return this.page.getByTestId('badge-print-button');
  }

  get confirmBar(): Locator {
    return this.page.getByTestId('badge-print-confirm');
  }

  get markPrintedButton(): Locator {
    return this.page.getByTestId('badge-mark-printed-button');
  }
}
