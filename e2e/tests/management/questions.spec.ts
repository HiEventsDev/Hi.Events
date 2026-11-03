import { test, expect } from '../../fixtures';
import { QuestionPage } from '../../pages/question.page';
import { createDraftEvent, createDraftEventWithTicket } from '../../api/factory';
import { uniqueShort } from '../../utils/unique';

test.describe('questions', () => {
  test('an organizer adds an order question and sees it in the list', { tag: '@smoke' }, async ({ authedPage, api, account }) => {
    const event = await createDraftEvent(api, account.organizerId);
    const title = uniqueShort('Arrival time');

    const questions = new QuestionPage(authedPage);
    await questions.goto(event.eventId);
    await questions.addOrderQuestion(title);

    await expect(questions.row('order', title)).toBeVisible();
  });

  test('an organizer creates a choice question by typing its options', async ({ authedPage, api, account }) => {
    const event = await createDraftEvent(api, account.organizerId);

    const questions = new QuestionPage(authedPage);
    await questions.goto(event.eventId);
    await questions.startQuestion('order', 'CHECKBOX');
    await questions.fillTitle('Workshops');
    await questions.optionInputs().nth(0).fill('Postgres');
    await questions.optionInputs().nth(1).fill('Kubernetes');
    await questions.submit();

    await expect(questions.row('order', 'Workshops')).toContainText('2 options');
  });

  test('an attendee question from a template asks every ticket and shows in the checkout preview', async ({ authedPage, api, account }) => {
    const event = await createDraftEventWithTicket(api, account.organizerId, { productTitle: 'Conference pass' });

    const questions = new QuestionPage(authedPage);
    await questions.goto(event.eventId);
    await questions.startFromTemplate('attendee', 'dietary');

    await questions.optionInputs().last().click();
    await authedPage.keyboard.press('End');
    await authedPage.keyboard.press('Enter');
    await authedPage.keyboard.type('Pescatarian');
    await expect(questions.optionInputs()).toHaveCount(8);

    await questions.submit();

    const row = questions.row('attendee', 'Dietary requirements');
    await expect(row).toContainText('8 options');
    await expect(row).toContainText('All tickets');
    await expect(questions.preview().getByRole('radio', { name: 'Pescatarian' })).toBeVisible();
  });

  test('an organizer adds a follow-up question that only shows for a chosen answer', async ({ authedPage, api, account }) => {
    const event = await createDraftEvent(api, account.organizerId);
    await api.createQuestion(event.eventId, {
      title: 'Speaking at the event?',
      type: 'RADIO',
      belongs_to: 'ORDER',
      product_ids: [],
      options: ['Yes', 'No'],
      required: false,
      is_hidden: false,
    });

    const questions = new QuestionPage(authedPage);
    await questions.goto(event.eventId);
    await questions.startFollowUp('order', 'Speaking at the event?');
    await questions.fillTitle('Talk title');
    await questions.showWhenAnswerIs('Yes');
    await questions.submit('Add follow-up question');

    await expect(questions.row('order', 'Talk title')).toContainText('When: Yes');

    const preview = questions.preview();
    await expect(preview.getByText('Talk title')).toHaveCount(0);
    await preview.getByRole('radio', { name: 'Yes' }).check();
    await expect(preview.getByLabel('Talk title')).toBeVisible();
  });

  test('an organizer blocks registration for an ineligible answer', async ({ authedPage, api, account }) => {
    const event = await createDraftEvent(api, account.organizerId);

    const questions = new QuestionPage(authedPage);
    await questions.goto(event.eventId);
    await questions.startQuestion('order', 'RADIO');
    await questions.fillTitle('Are you a healthcare professional?');
    await questions.optionInputs().nth(0).fill('Yes');
    await questions.optionInputs().nth(1).fill('No');
    await questions.blockRegistrationFor(['No'], 'This event is open to healthcare professionals only.');
    await questions.submit();

    const row = questions.row('order', 'Are you a healthcare professional?');
    await expect(row).toContainText('Ineligible: No');
    await expect(row).toContainText('Required');

    const preview = questions.preview();
    await preview.getByRole('radio', { name: 'No', exact: true }).check();
    await expect(preview.getByText('This event is open to healthcare professionals only.')).toBeVisible();
  });
});
