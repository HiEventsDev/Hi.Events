import {expect, type Page} from '@playwright/test';

export class SubscribersPage {
  constructor(readonly page: Page) {}

  async goto(organizerId: number): Promise<void> {
    await this.page.goto(`/manage/organizer/${organizerId}/subscribers`);
    await expect(this.page.getByRole('heading', {name: 'Subscribers', exact: true})).toBeVisible();
  }

  row(email: string) {
    return this.page.getByRole('row').filter({hasText: email});
  }

  async search(email: string): Promise<void> {
    await this.page.getByRole('textbox', {name: 'Search subscribers by email'}).fill(email);
  }

  async unsubscribeSelected(count: number): Promise<void> {
    await this.page.getByRole('button', {name: `Unsubscribe selected (${count})`}).click();
    await this.page.getByRole('dialog').getByRole('button', {name: 'Confirm', exact: true}).click();
  }
}
