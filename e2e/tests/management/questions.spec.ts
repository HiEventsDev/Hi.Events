import { test, expect } from '../../fixtures';
import { QuestionPage } from '../../pages/question.page';
import { createDraftEvent } from '../../api/factory';
import { uniqueShort } from '../../utils/unique';

test.describe('questions', () => {
  test('an organizer adds an order question and sees it in the list', { tag: '@smoke' }, async ({ authedPage, api, account }) => {
    const event = await createDraftEvent(api, account.organizerId);
    const title = uniqueShort('Arrival time');

    const questions = new QuestionPage(authedPage);
    await questions.goto(event.eventId);
    await questions.addOrderQuestion(title);

    await expect(authedPage.getByRole('paragraph').filter({ hasText: title })).toBeVisible();
  });
});
