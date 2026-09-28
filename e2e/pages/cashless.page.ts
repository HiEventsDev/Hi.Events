import type { Locator, Page } from '@playwright/test';

export class CashlessSalesPointPage {
  constructor(private readonly page: Page) {}

  async goto(eventId: number): Promise<void> {
    await this.page.goto(`/manage/event/${eventId}/cashless/sales-points`);
    await this.page.waitForLoadState('networkidle');
  }

  async create(name: string, productTitle: string): Promise<void> {
    await this.page.getByTestId('cashless-sales-point-create-button').click();
    await this.page.getByLabel(/^Name/).fill(name);
    await this.page.getByRole('combobox', { name: 'What can be sold here?' }).click();
    await this.page.getByRole('option', { name: productTitle }).click();
    await this.page.keyboard.press('Escape');
    await this.page.getByTestId('cashless-sales-point-submit-button').click();
  }

  row(name: string): Locator {
    return this.page.getByRole('row').filter({ hasText: name });
  }
}

export class CashlessWalletsPage {
  constructor(private readonly page: Page) {}

  async goto(eventId: number): Promise<void> {
    await this.page.goto(`/manage/event/${eventId}/cashless`);
    await this.page.waitForLoadState('networkidle');
  }

  row(ticketPublicId: string): Locator {
    return this.page.getByRole('row').filter({ hasText: ticketPublicId });
  }

  async topUpByTicketId(ticketPublicId: string, amount: number): Promise<void> {
    await this.page.getByTestId('cashless-ticket-topup-button').click();
    await this.page.getByTestId('cashless-ticket-lookup-input').fill(ticketPublicId);
    await this.page.getByTestId('cashless-ticket-lookup-submit-button').click();
    await this.page.getByLabel('Amount to add').fill(String(amount));
    await this.page.getByTestId('cashless-topup-submit-button').click();
  }
}

export class CashlessPosPage {
  constructor(private readonly page: Page) {}

  async goto(salesPointShortId: string): Promise<void> {
    await this.page.goto(`/cashless/pos/${salesPointShortId}`);
    await this.page.waitForLoadState('networkidle');
  }

  async unlock(pin: string): Promise<void> {
    await this.page.getByTestId('cashless-pos-pin-input').fill(pin);
    await this.page.getByTestId('cashless-pos-pin-submit-button').click();
  }

  async lookUpTicket(ticketPublicId: string, inputTestId = 'cashless-pos-ticket-input'): Promise<void> {
    await this.page.getByTestId(inputTestId).fill(ticketPublicId);
    await this.page.getByRole('button', { name: 'Find' }).click();
  }

  async addProduct(priceId: number): Promise<void> {
    await this.page.getByTestId(`cashless-pos-product-${priceId}`).click();
  }

  chargeButton(): Locator {
    return this.page.getByTestId('cashless-pos-charge-button');
  }
}

export class CashlessWalletPublicPage {
  constructor(private readonly page: Page) {}

  async goto(eventId: number, ticketReference: string): Promise<void> {
    await this.page.goto(`/cashless/${eventId}/${ticketReference}`);
    await this.page.waitForLoadState('networkidle');
  }

  balance(): Locator {
    return this.page.getByText('Available to spend');
  }
}

export class CashlessTopupEntryPage {
  constructor(private readonly page: Page) {}

  async gotoFromEventPage(eventId: number, slug: string): Promise<void> {
    await this.page.goto(`/event/${eventId}/${slug}`);
    await this.page.waitForLoadState('networkidle');
    await this.page.getByTestId('event-cashless-topup-link').click();
    await this.page.waitForURL(new RegExp(`/cashless/${eventId}$`));
  }

  async submitTicketId(ticketId: string): Promise<void> {
    await this.page.getByTestId('cashless-entry-ticket-input').fill(ticketId);
    await this.page.getByTestId('cashless-entry-continue-button').click();
  }
}

export class CashlessOverviewPage {
  constructor(private readonly page: Page) {}

  async goto(eventId: number): Promise<void> {
    await this.page.goto(`/manage/event/${eventId}/cashless/overview`);
    await this.page.waitForLoadState('networkidle');
  }

  kpiValue(name: 'outstanding' | 'topped-up' | 'spent' | 'refunded' | 'closed' | 'purchases'): Locator {
    return this.page.getByTestId(`cashless-overview-${name}-value`);
  }

  async openCloseModal(): Promise<void> {
    await this.page.getByTestId('cashless-close-button').click();
  }

  closeSubmitButton(): Locator {
    return this.page.getByTestId('cashless-close-submit-button');
  }

  async confirmAndClose(): Promise<void> {
    await this.page.getByTestId('cashless-close-confirm-checkbox').check();
    await this.closeSubmitButton().click();
  }
}
