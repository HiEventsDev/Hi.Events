import { test, expect } from '../../fixtures';
import { createCompletedOrder, createLiveEventWithFreeTicket, createReservedOrder } from '../../api/factory';
import { completePublicOrder } from '../../api/public-client';
import { AudiencePage } from '../../pages/audience.page';
import { CheckoutPage } from '../../pages/checkout.page';
import { OrderPage } from '../../pages/order.page';
import { OrderSummaryPage } from '../../pages/order-summary.page';
import { EventSettingsPage } from '../../pages/event-settings.page';
import { BASE_URL } from '../../utils/env';
import { uniqueEmail } from '../../utils/unique';
import { askForMobileAtCheckout, buildAudience, createOptedInOrder, UK_MOBILE, waitForContact } from './audience.shared';
import { expectNoSidewaysScroll, PHONE_VIEWPORT } from './contacts.helpers';

const FRENCH_MOBILE = '+33612345678';
const GUERNSEY_MOBILE = '+447781123456';

test.describe('audience mobile numbers', () => {
  test('an organizer asks for a mobile number at checkout, the text box only shows for a number in a country we text, the buyer agrees, and staff can stop them later', async ({ freshAccount, adminApi, browser }) => {
    const { api, organizerId } = freshAccount;
    const accountId = (await api.getAccount()).id;
    const event = await createLiveEventWithFreeTicket(api, organizerId);
    await buildAudience(api, organizerId);
    const buyer = { firstName: 'Mona', lastName: 'Mobile', email: uniqueEmail('mobile-buyer') };

    const page = await freshAccount.newAuthedPage();
    const settings = new EventSettingsPage(page);
    await settings.goto(event.eventId, 'homepage-settings');
    const checkoutSettings = settings.section('homepage-settings');
    const textOptIn = checkoutSettings.getByRole('switch', { name: 'Show text message opt-in checkbox' });
    await expect(checkoutSettings.getByTestId('phone-collection-select')).toBeVisible();
    await adminApi.setAccountFeatureFlag(accountId, 'crm_sms', true);
    await page.reload();
    await expect(textOptIn).toBeDisabled();
    await expect(checkoutSettings.getByText("Ask for the buyer's mobile number above to use this.")).toBeVisible();
    await checkoutSettings.getByTestId('phone-collection-select').click();
    await page.getByTestId('phone-collection-select-option-REQUIRED').click();
    await textOptIn.check({ force: true });
    await checkoutSettings.getByRole('button', { name: 'Save' }).click();
    await expect(page.getByText('Successfully Updated Homepage Settings')).toBeVisible();

    const visitor = await browser.newPage();
    try {
      const checkout = new CheckoutPage(visitor);
      await checkout.gotoPublicEvent(event.eventId, event.slug);
      await checkout.setFirstProductQuantity(1);
      await checkout.continueToCheckout();
      await checkout.fillOrderDetails(buyer);
      await checkout.fillFirstAttendee(buyer);

      const complete = visitor.getByRole('button', { name: 'Complete Order' });
      const mobile = visitor.getByRole('textbox', { name: 'Mobile number' });
      await complete.click();
      await expect(visitor.getByText('Mobile number is required')).toBeVisible();
      await mobile.fill('12345');
      await complete.click();
      await expect(visitor.getByText('Enter a valid mobile number')).toBeVisible();
      const textOptInBox = visitor.getByTestId('checkout-sms-opt-in');
      await expect(textOptInBox).toHaveCount(0);
      await mobile.fill(FRENCH_MOBILE);
      await expect(visitor.getByRole('button', { name: 'Country code' })).toContainText('FR +33');
      await expect(textOptInBox).toHaveCount(0);
      await mobile.fill(UK_MOBILE);
      await expect(visitor.getByRole('button', { name: 'Country code' })).toContainText('GB +44');
      await textOptInBox.check();
      await expect(visitor.getByText('You can opt out at any time.')).toBeVisible();
      await mobile.fill(GUERNSEY_MOBILE);
      await expect(visitor.getByRole('button', { name: 'Country code' })).toContainText('GG +44');
      await expect(textOptInBox).toHaveCount(0);
      await expect(visitor.getByText('You can opt out at any time.')).toHaveCount(0);
      await mobile.fill(UK_MOBILE);
      await expect(textOptInBox).not.toBeChecked();
      await textOptInBox.check();
      await checkout.completeFreeOrder();
      await expect(visitor.getByText(`You're going to ${event.title}`)).toBeVisible();
    } finally {
      await visitor.close();
    }

    await waitForContact(api, organizerId, buyer.email);
    const audience = new AudiencePage(page, organizerId);
    await audience.gotoContactsMatching(buyer.email);
    await audience.contactRow('Mona Mobile').click();
    const texts = audience.drawerSection('Text message status');
    await expect(texts).toContainText('+44 7400 123456');
    await expect(texts).toContainText('Opted in to marketing text messages on this number.');

    await audience.openDrawerAction('Opt out of text messages');
    const optOut = page.getByRole('dialog', { name: 'Opt this contact out of text messages?' });
    await expect(optOut).toContainText("They're only opted in again if they tick the text message box on a future order.");
    await expect(optOut).not.toContainText('START');
    await audience.confirm('Opt out');
    await expect(audience.drawerSection('Consent history')).toContainText('Opted out of text messages');
    await expect(texts).toContainText('Asked not to receive marketing text messages on this number.');
    await expect(audience.marketingStatus()).not.toContainText('Unsubscribed');
    await audience.moreActions().click();
    await expect(page.getByRole('menuitem', { name: 'Delete contact' })).toBeVisible();
    await expect(page.getByRole('menuitem', { name: 'Opt out of text messages' })).toHaveCount(0);
  });

  test('an optional mobile number can be skipped (no text box without a number), and a buyer who gives one without ticking the box is not opted in to texts', async ({ freshAccount, adminApi, browser }) => {
    const { api, organizerId } = freshAccount;
    await adminApi.setAccountFeatureFlag((await api.getAccount()).id, 'crm_sms', true);
    const event = await createLiveEventWithFreeTicket(api, organizerId);
    await askForMobileAtCheckout(api, event.eventId, 'OPTIONAL');
    await buildAudience(api, organizerId);
    const skipper = { firstName: 'Skye', lastName: 'Skipper', email: uniqueEmail('no-number') };
    const careful = { firstName: 'Cara', lastName: 'Careful', email: uniqueEmail('number-no-texts') };

    const visitor = await browser.newPage();
    try {
      const checkout = new CheckoutPage(visitor);
      await checkout.gotoPublicEvent(event.eventId, event.slug);
      await checkout.setFirstProductQuantity(1);
      await checkout.continueToCheckout();
      await checkout.fillOrderDetails(skipper);
      await checkout.fillFirstAttendee(skipper);
      await expect(visitor.getByRole('textbox', { name: 'Mobile number' })).toHaveValue('');
      await expect(visitor.getByTestId('checkout-sms-opt-in')).toHaveCount(0);
      await checkout.completeFreeOrder();
      await expect(visitor.getByText(`You're going to ${event.title}`)).toBeVisible();

      await checkout.gotoPublicEvent(event.eventId, event.slug);
      await checkout.setFirstProductQuantity(1);
      await checkout.continueToCheckout();
      await checkout.fillOrderDetails(careful);
      await checkout.fillFirstAttendee(careful);
      await visitor.getByRole('textbox', { name: 'Mobile number' }).fill(UK_MOBILE);
      await expect(visitor.getByTestId('checkout-sms-opt-in')).not.toBeChecked();
      await checkout.completeFreeOrder();
      await expect(visitor.getByText(`You're going to ${event.title}`)).toBeVisible();
      await expect(visitor.getByText('+44 7400 123456')).toBeVisible();
    } finally {
      await visitor.close();
    }

    await waitForContact(api, organizerId, careful.email);
    await waitForContact(api, organizerId, skipper.email);
    const page = await freshAccount.newAuthedPage();
    const audience = new AudiencePage(page, organizerId);
    await audience.gotoContactsMatching(careful.email);
    await audience.contactRow('Cara Careful').click();
    const texts = audience.drawerSection('Text message status');
    await expect(texts).toContainText('+44 7400 123456');
    await expect(texts).toContainText("Hasn't opted in to marketing text messages on this number.");
    await audience.moreActions().click();
    await expect(page.getByRole('menuitem', { name: 'Opt out of text messages' })).toHaveCount(0);
    await page.keyboard.press('Escape');

    await audience.gotoContactsMatching(skipper.email);
    await audience.contactRow('Skye Skipper').click();
    await expect(audience.marketingStatus()).toBeVisible();
    await expect(audience.drawer()).not.toContainText('+44');
  });

  test('checkout leaves the mobile number out when the event does not ask, and the text box out when it is switched off', async ({ freshAccount, adminApi, browser }) => {
    const { api, organizerId } = freshAccount;
    await adminApi.setAccountFeatureFlag((await api.getAccount()).id, 'crm_sms', true);
    const notAsking = await createLiveEventWithFreeTicket(api, organizerId);
    const numberOnly = await createLiveEventWithFreeTicket(api, organizerId);
    await api.updateEventSettings(numberOnly.eventId, { phone_collection: 'REQUIRED', show_sms_opt_in: false });
    const buyer = { firstName: 'Nora', lastName: 'Number', email: uniqueEmail('number-only') };

    const visitor = await browser.newPage();
    try {
      const checkout = new CheckoutPage(visitor);
      await checkout.gotoPublicEvent(notAsking.eventId, notAsking.slug);
      await checkout.setFirstProductQuantity(1);
      await checkout.continueToCheckout();
      await expect(visitor.getByRole('button', { name: 'Complete Order' })).toBeVisible();
      await expect(visitor.getByRole('textbox', { name: 'Mobile number' })).toHaveCount(0);
      await expect(visitor.getByTestId('checkout-sms-opt-in')).toHaveCount(0);

      await checkout.gotoPublicEvent(numberOnly.eventId, numberOnly.slug);
      await checkout.setFirstProductQuantity(1);
      await checkout.continueToCheckout();
      await checkout.fillOrderDetails(buyer);
      await checkout.fillFirstAttendee(buyer);
      await expect(visitor.getByTestId('checkout-sms-opt-in')).toHaveCount(0);
      await visitor.getByRole('button', { name: 'Complete Order' }).click();
      await expect(visitor.getByText('Mobile number is required')).toBeVisible();
      await visitor.getByRole('textbox', { name: 'Mobile number' }).fill(UK_MOBILE);
      await checkout.completeFreeOrder();
      await expect(visitor.getByText(`You're going to ${numberOnly.title}`)).toBeVisible();
    } finally {
      await visitor.close();
    }
  });

  test('organizer defaults carry the mobile number setting into new events, and the buyer and the organizer can each correct the number afterwards', async ({ freshAccount, adminApi, publicApi, browser }) => {
    const { api, organizerId } = freshAccount;
    await adminApi.setAccountFeatureFlag((await api.getAccount()).id, 'crm_sms', true);
    await api.updateOrganizerSettings(organizerId, { default_phone_collection: 'REQUIRED', default_show_sms_opt_in: true } as never);

    const page = await freshAccount.newAuthedPage();
    await page.goto(`/manage/organizer/${organizerId}/settings/event-defaults`);
    await expect(page.getByRole('switch', { name: 'Show text message opt-in checkbox by default' })).toBeChecked();
    await expect(page.getByText('New events that ask for a mobile number will let buyers agree to receive text messages. This can be changed per event.')).toBeVisible();

    const event = await createLiveEventWithFreeTicket(api, organizerId);
    const settings = new EventSettingsPage(page);
    await settings.goto(event.eventId, 'homepage-settings');
    const checkoutSettings = settings.section('homepage-settings');
    await expect(checkoutSettings.getByTestId('phone-collection-select')).toContainText('Required');
    await expect(checkoutSettings.getByRole('switch', { name: 'Show text message opt-in checkbox' })).toBeChecked();

    const buyer = uniqueEmail('corrected');
    await expect(createCompletedOrder(publicApi, event, { buyerEmail: uniqueEmail('no-phone') })).rejects.toThrow(/422/);
    const { orderShortId, sessionId } = await createReservedOrder(publicApi, event);
    await completePublicOrder(publicApi, event.eventId, orderShortId, sessionId, {
      order: { first_name: 'Corey', last_name: 'Corrected', email: buyer, phone: UK_MOBILE, sms_opted_in: true } as never,
      attendees: [{ product_id: event.productId, product_price_id: event.priceId, first_name: 'Corey', last_name: 'Corrected', email: buyer }],
    });

    const buyerContext = await browser.newContext({ ignoreHTTPSErrors: true, baseURL: BASE_URL });
    try {
      const buyerPage = await buyerContext.newPage();
      const summary = new OrderSummaryPage(buyerPage);
      await summary.goto(event.eventId, orderShortId);
      await expect(buyerPage.getByText('+44 7400 123456')).toBeVisible();
      await buyerPage.getByTestId('order-edit-button').click();
      const edit = buyerPage.getByRole('dialog', { name: 'Edit Order Details' });
      const mobile = edit.getByRole('textbox', { name: 'Mobile number' });
      await mobile.fill('');
      await edit.getByRole('button', { name: 'Save' }).click();
      await expect(edit.getByText('Mobile number is required')).toBeVisible();
      await mobile.fill('+447400123999');
      await edit.getByRole('button', { name: 'Save' }).click();
      await expect(edit).toBeHidden();
      await expect(buyerPage.getByText('+44 7400 123999')).toBeVisible();
    } finally {
      await buyerContext.close();
    }

    const orders = new OrderPage(page);
    await orders.goto(event.eventId);
    await orders.chooseRowAction('Corey Corrected', 'Manage order');
    const details = orders.detailsDrawer().filter({ hasText: 'Mobile number' });
    await expect(details.getByRole('link', { name: '+44 7400 123999' })).toHaveAttribute('href', 'tel:+447400123999');
    await details.getByRole('button', { name: 'Edit', exact: true }).click();
    const manage = page.getByRole('dialog').filter({ has: page.getByRole('button', { name: 'Save Changes' }) });
    await manage.getByRole('textbox', { name: 'Mobile number' }).fill('+447400123888');
    await manage.getByRole('button', { name: 'Save Changes' }).click();
    await expect(page.getByRole('link', { name: '+44 7400 123888' })).toHaveAttribute('href', 'tel:+447400123888');

    await buildAudience(api, organizerId);
    await waitForContact(api, organizerId, buyer);
    const audience = new AudiencePage(page, organizerId);
    await audience.gotoContactsMatching(buyer);
    await audience.contactRow('Corey Corrected').click();
    await expect(audience.drawerSection('Text message status')).toContainText('+44 7400 123888');
  });

  test('on a phone the buyer gives a required mobile number and agrees to texts without the page scrolling sideways', async ({ freshAccount, adminApi, browser }) => {
    const { api, organizerId } = freshAccount;
    await adminApi.setAccountFeatureFlag((await api.getAccount()).id, 'crm_sms', true);
    const event = await createLiveEventWithFreeTicket(api, organizerId);
    await askForMobileAtCheckout(api, event.eventId, 'REQUIRED');
    await buildAudience(api, organizerId);
    const buyer = { firstName: 'Paz', lastName: 'Pocket', email: uniqueEmail('phone-buyer') };

    const context = await browser.newContext({ ignoreHTTPSErrors: true, baseURL: BASE_URL, viewport: PHONE_VIEWPORT, hasTouch: true });
    try {
      const visitor = await context.newPage();
      const checkout = new CheckoutPage(visitor);
      await checkout.gotoPublicEvent(event.eventId, event.slug);
      await checkout.setFirstProductQuantity(1);
      await checkout.continueToCheckout();
      await checkout.fillOrderDetails(buyer);
      await checkout.fillFirstAttendee(buyer);
      const mobile = visitor.getByRole('textbox', { name: 'Mobile number' });
      await mobile.fill(UK_MOBILE);
      await expect(visitor.getByRole('button', { name: 'Country code' })).toContainText('GB +44');
      await expect(visitor.getByRole('button', { name: 'Country code' })).toBeInViewport();
      await visitor.getByTestId('checkout-sms-opt-in').check();
      await expectNoSidewaysScroll(visitor);
      await checkout.completeFreeOrder();
      await expect(visitor.getByText(`You're going to ${event.title}`)).toBeVisible();
      await expect(visitor.getByText('+44 7400 123456')).toBeVisible();
      await expectNoSidewaysScroll(visitor);
    } finally {
      await context.close();
    }

    await waitForContact(api, organizerId, buyer.email);
    const page = await freshAccount.newAuthedPage();
    await page.setViewportSize(PHONE_VIEWPORT);
    const audience = new AudiencePage(page, organizerId);
    await audience.gotoContactsMatching(buyer.email);
    await audience.contactRow('Paz Pocket').click();
    await expect(audience.drawerSection('Text message status')).toContainText('Opted in to marketing text messages on this number.');
    await expectNoSidewaysScroll(page);
  });

  test('the contact list can show the mobile number and text status columns, and remembers the choice in this browser', async ({ freshAccount, publicApi }) => {
    const { api, organizerId } = freshAccount;
    const event = await createLiveEventWithFreeTicket(api, organizerId);
    await askForMobileAtCheckout(api, event.eventId);
    await createOptedInOrder(publicApi, event, { email: uniqueEmail('column'), firstName: 'Cole', lastName: 'Column', phone: UK_MOBILE, agreesToTexts: true });
    await buildAudience(api, organizerId);

    const page = await freshAccount.newAuthedPage();
    const audience = new AudiencePage(page, organizerId);
    await audience.gotoContacts();
    await expect(audience.contactRow('Cole Column')).not.toContainText('+44 7400 123456');

    await page.getByRole('button', { name: 'Columns' }).click();
    await page.getByRole('checkbox', { name: 'Mobile number' }).check();
    await page.getByRole('checkbox', { name: 'Check-ins' }).uncheck();
    await page.keyboard.press('Escape');
    await expect(audience.contactRow('Cole Column')).toContainText('+44 7400 123456');
    await expect(page.getByRole('columnheader', { name: 'Check-ins' })).toHaveCount(0);

    await page.reload();
    await expect(audience.contactRow('Cole Column')).toContainText('+44 7400 123456');
    await expect(page.getByRole('columnheader', { name: 'Check-ins' })).toHaveCount(0);
  });
});
