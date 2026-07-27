import type { Page } from '@playwright/test';

export class QuestionPage {
  constructor(private readonly page: Page) {}

  async goto(eventId: number): Promise<void> {
    await this.page.goto(`/manage/event/${eventId}/questions`);
    await this.page.waitForLoadState('networkidle');
  }

  async addOrderQuestion(title: string): Promise<void> {
    await this.page.getByTestId('question-add-button').click();
    await this.page.getByRole('heading', { name: 'Create Question' }).waitFor();
    await this.page.getByLabel(/^Question Title/).fill(title);
    await this.page.getByTestId('question-submit-button').click();
  }
}
