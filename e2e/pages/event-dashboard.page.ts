import type { Locator, Page } from '@playwright/test';

export type EventStatKey = 'attendees' | 'products_sold' | 'refunded' | 'gross_sales' | 'page_views' | 'orders';

export class EventDashboardPage {
  constructor(private readonly page: Page) {}

  async goto(eventId: number): Promise<void> {
    await this.page.goto(`/manage/event/${eventId}/dashboard`);
    await this.page.waitForLoadState('networkidle');
  }

  async gotoOccurrence(eventId: number, occurrenceId: number): Promise<void> {
    await this.page.goto(`/manage/event/${eventId}/occurrences/${occurrenceId}`);
    await this.page.waitForLoadState('networkidle');
  }

  stat(key: EventStatKey): Locator {
    return this.page.getByTestId(`event-stat-${key}-value`);
  }
}
