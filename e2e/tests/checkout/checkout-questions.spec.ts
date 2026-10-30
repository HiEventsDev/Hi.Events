import { test, expect } from '../../fixtures';
import { CheckoutPage } from '../../pages/checkout.page';
import { createEventWithQuestions, createLiveEventWithFreeTicket } from '../../api/factory';
import { uniqueEmail } from '../../utils/unique';

test.describe('checkout questions', () => {
  test('required order and attendee questions block completion until answered', async ({ page, api, account }) => {
    const event = await createEventWithQuestions(api, account.organizerId);
    const buyer = { firstName: 'Curious', lastName: 'Buyer', email: uniqueEmail('questions') };

    const checkout = new CheckoutPage(page);
    await checkout.gotoPublicEvent(event.eventId, event.slug);
    await checkout.setFirstProductQuantity(1);
    await checkout.continueToCheckout();
    await checkout.fillOrderDetails(buyer);
    await checkout.fillFirstAttendee(buyer);

    await expect(page.getByLabel(/^How did you hear about us/)).toBeVisible();
    await expect(page.getByRole('radio', { name: 'Medium' })).toBeVisible();

    await page.getByRole('button', { name: 'Complete Order' }).click();

    await expect(page.getByText('This field is required.')).toHaveCount(2);

    await checkout.answerTextQuestion('How did you hear about us?', 'From a friend');
    await checkout.chooseRadioOption('Medium');
    await checkout.completeFreeOrder();

    await expect(page.getByText(`You're going to ${event.title}`)).toBeVisible();
  });

  test('phone and multi-select questions can be answered at checkout', async ({ page, api, account }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    await api.createQuestion(event.eventId, {
      title: 'Mobile number',
      type: 'PHONE',
      belongs_to: 'ORDER',
      product_ids: [],
      required: true,
      is_hidden: false,
    });
    await api.createQuestion(event.eventId, {
      title: 'Workshops',
      type: 'MULTI_SELECT_DROPDOWN',
      belongs_to: 'PRODUCT',
      product_ids: [event.productId],
      options: ['Postgres', 'Kubernetes', 'Observability'],
      required: true,
      is_hidden: false,
    });
    const buyer = { firstName: 'Phone', lastName: 'Buyer', email: uniqueEmail('phone-questions') };

    const checkout = new CheckoutPage(page);
    await checkout.gotoPublicEvent(event.eventId, event.slug);
    await checkout.setFirstProductQuantity(1);
    await checkout.continueToCheckout();
    await checkout.fillOrderDetails(buyer);
    await checkout.fillFirstAttendee(buyer);

    await checkout.answerTextQuestion('Mobile number', '+353 87 123 4567');
    await checkout.chooseMultiSelectOptions('Workshops', ['Postgres', 'Observability']);
    await checkout.completeFreeOrder();

    await expect(page.getByText(`You're going to ${event.title}`)).toBeVisible();
  });

  test('follow-up questions appear for matching answers and an ineligible answer blocks the order', async ({ page, api, account }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    const dietary = await api.createQuestion(event.eventId, {
      title: 'Dietary needs',
      type: 'RADIO',
      belongs_to: 'ORDER',
      product_ids: [],
      options: ['None', 'Other'],
      required: false,
      is_hidden: false,
    });
    await api.createQuestion(event.eventId, {
      title: 'Please specify',
      type: 'SINGLE_LINE_TEXT',
      belongs_to: 'ORDER',
      product_ids: [],
      required: true,
      is_hidden: false,
      condition_question_id: dietary.id,
      condition_values: ['Other'],
    });
    await api.createQuestion(event.eventId, {
      title: 'Are you 18 or older?',
      type: 'RADIO',
      belongs_to: 'ORDER',
      product_ids: [],
      options: ['Yes', 'No'],
      required: true,
      is_hidden: false,
      disqualifying_options: ['No'],
      disqualification_message: 'You must be 18 or older to attend.',
    });
    const buyer = { firstName: 'Follow', lastName: 'Up', email: uniqueEmail('follow-up') };

    const checkout = new CheckoutPage(page);
    await checkout.gotoPublicEvent(event.eventId, event.slug);
    await checkout.setFirstProductQuantity(1);
    await checkout.continueToCheckout();
    await checkout.fillOrderDetails(buyer);
    await checkout.fillFirstAttendee(buyer);

    await expect(page.getByLabel(/^Please specify/)).toHaveCount(0);
    await checkout.chooseRadioOption('Other');
    await checkout.answerTextQuestion('Please specify', 'Nut allergy');

    await page.getByRole('radio', { name: 'No', exact: true }).check();
    await expect(page.getByTestId('question-ineligible-notice')).toContainText('You must be 18 or older to attend.');
    await expect(page.getByRole('textbox', { name: 'First Name' }).last()).toBeDisabled();
    await expect(page.getByRole('button', { name: 'Complete Order' })).toBeDisabled();
    await expect(page.getByTestId('checkout-ineligible-hint')).toContainText('You must be 18 or older to attend.');

    await page.getByRole('radio', { name: 'Yes', exact: true }).check();
    await checkout.completeFreeOrder();

    await expect(page.getByText(`You're going to ${event.title}`)).toBeVisible();
  });
});
