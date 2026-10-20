import { test, expect } from '../../fixtures';
import { CheckoutPage } from '../../pages/checkout.page';
import { createEventWithQuestions } from '../../api/factory';
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
});
