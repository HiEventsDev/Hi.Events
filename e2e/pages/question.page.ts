import type { Locator, Page } from '@playwright/test';

export type QuestionScope = 'order' | 'attendee';

export class QuestionPage {
  constructor(private readonly page: Page) {}

  async goto(eventId: number): Promise<void> {
    await this.page.goto(`/manage/event/${eventId}/questions`);
    await this.page.waitForLoadState('networkidle');
  }

  section(scope: QuestionScope): Locator {
    return this.page.getByTestId(`question-section-${scope}`);
  }

  row(scope: QuestionScope, title: string): Locator {
    return this.section(scope).getByTestId('question-row').filter({ hasText: title });
  }

  preview(): Locator {
    return this.page.getByRole('complementary', { name: 'Checkout preview' });
  }

  async startQuestion(scope: QuestionScope, type: string): Promise<void> {
    await this.page.getByTestId(`question-add-${scope}-button`).click();
    await this.page.getByTestId(`question-type-option-${type}`).click();
    await this.drawer().waitFor();
  }

  async startFromTemplate(scope: QuestionScope, templateId: string): Promise<void> {
    await this.page.getByTestId(`question-add-${scope}-button`).click();
    await this.page.getByTestId(`question-template-${templateId}`).click();
    await this.drawer().waitFor();
  }

  async fillTitle(title: string): Promise<void> {
    await this.page.getByTestId('question-title-input').fill(title);
  }

  optionInputs(): Locator {
    return this.page.getByTestId('question-option-input');
  }

  async submit(heading = 'Create Question'): Promise<void> {
    await this.page.getByTestId('question-submit-button').click();
    await this.drawer(heading).waitFor({ state: 'hidden' });
  }

  async blockRegistrationFor(answers: string[], message: string): Promise<void> {
    const drawer = this.page.getByRole('dialog');
    await drawer.getByRole('switch', { name: /^Block registration for some answers/ }).check();
    for (const answer of answers) {
      await drawer.getByRole('checkbox', { name: answer, exact: true }).check();
    }
    await this.page.getByTestId('question-ineligible-message-input').fill(message);
  }

  async startFollowUp(scope: QuestionScope, parentTitle: string): Promise<void> {
    await this.row(scope, parentTitle).first().getByTestId('question-row-menu').click();
    await this.page.getByTestId('question-add-follow-up-menu-item').click();
    await this.drawer('Add follow-up question').waitFor();
  }

  async showWhenAnswerIs(answer: string): Promise<void> {
    await this.drawer('Add follow-up question').getByText(answer, { exact: true }).click();
  }

  async addOrderQuestion(title: string): Promise<void> {
    await this.startQuestion('order', 'SINGLE_LINE_TEXT');
    await this.fillTitle(title);
    await this.submit();
  }

  private drawer(heading = 'Create Question'): Locator {
    return this.page.getByRole('dialog', { name: heading });
  }
}
