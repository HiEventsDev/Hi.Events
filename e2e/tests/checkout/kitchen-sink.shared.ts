import { expect, type APIRequestContext, type Frame, type Page } from '@playwright/test';
import { CheckoutPage, setWidgetQuantity, type BuyerDetails, type CheckoutSurface } from '../../pages/checkout.page';
import type { ApiClient } from '../../api/api-client';
import type { Occurrence } from '../../api/types';
import { createDraftEvent, OFFLINE_PAYMENT_INSTRUCTIONS } from '../../api/factory';
import { deliverPaymentIntentSucceededWebhook, parsePaymentReturnUrl } from '../../api/stripe';
import type { MailpitClient } from '../../utils/mailpit';
import { uniqueCode, uniqueEmail, uniqueName, uniqueShort } from '../../utils/unique';

const GA_DESCRIPTION = 'Access tickets for the main event floor.';
const EXTRAS_DESCRIPTION = 'Merchandise and add-ons.';
const PRE_CHECKOUT_MESSAGE = 'Welcome! Please review ticket options carefully.';
const POST_CHECKOUT_MESSAGE = 'Thanks for coming — see you at the doors!';

export interface KitchenSinkScenario {
  eventId: number;
  slug: string;
  title: string;
  promoCode: string;
  gaCategoryName: string;
  extrasCategoryName: string;
}

export interface RecurringKitchenSinkScenario extends KitchenSinkScenario {
  occurrences: Occurrence[];
  standardPriceId: number;
}

const futureStartDate = (): string => {
  const date = new Date();
  date.setDate(date.getDate() + 30);
  date.setHours(21, 0, 0, 0);
  return date.toISOString();
};

async function createRecurringKitchenSinkEvent(
  api: ApiClient,
  organizerId: number,
  title: string,
): Promise<{ eventId: number; slug: string; occurrences: Occurrence[] }> {
  const event = await api.createEvent({
    title,
    type: 'RECURRING',
    organizer_id: organizerId,
    start_date: futureStartDate(),
    category: 'MUSIC',
    currency: 'USD',
    timezone: 'UTC',
  });
  await api.generateOccurrences(event.id, {
    frequency: 'weekly',
    range: { type: 'count', count: 3 },
    days_of_week: ['friday'],
    times_of_day: ['19:00'],
    duration_minutes: 120,
  });
  const occurrences = await api.listOccurrences(event.id);
  return { eventId: event.id, slug: event.slug, occurrences };
}

export async function arrangeKitchenSinkEvent(api: ApiClient, organizerId: number): Promise<KitchenSinkScenario>;
export async function arrangeKitchenSinkEvent(
  api: ApiClient,
  organizerId: number,
  opts: { eventType: 'RECURRING' },
): Promise<RecurringKitchenSinkScenario>;
export async function arrangeKitchenSinkEvent(
  api: ApiClient,
  organizerId: number,
  opts: { eventType?: 'SINGLE' | 'RECURRING' } = {},
): Promise<KitchenSinkScenario | RecurringKitchenSinkScenario> {
  const { id: accountId } = await api.getAccount();
  const tax = await api.createTaxOrFee(accountId, {
    name: uniqueName('Sales Tax'),
    calculation_type: 'PERCENTAGE',
    type: 'TAX',
    rate: 10,
    is_active: true,
    is_default: false,
  });
  const fee = await api.createTaxOrFee(accountId, {
    name: uniqueName('Booking Fee'),
    calculation_type: 'FIXED',
    type: 'FEE',
    rate: 2.5,
    is_active: true,
    is_default: false,
  });

  const title = uniqueName('Kitchen Sink');
  const isRecurring = opts.eventType === 'RECURRING';
  const { eventId, slug, occurrences } = isRecurring
    ? await createRecurringKitchenSinkEvent(api, organizerId, title)
    : { ...(await createDraftEvent(api, organizerId, { title })), occurrences: [] as Occurrence[] };

  const gaCategory = await api.createProductCategory(eventId, {
    name: uniqueShort('General Admission'),
    description: GA_DESCRIPTION,
    is_hidden: false,
  });
  const extrasCategory = await api.createProductCategory(eventId, {
    name: uniqueShort('Extras'),
    description: EXTRAS_DESCRIPTION,
    is_hidden: false,
  });

  const standard = await api.createProduct(eventId, {
    title: 'Standard Ticket',
    product_type: 'TICKET',
    type: 'PAID',
    product_category_id: gaCategory.id,
    prices: [{ price: 25 }],
    tax_and_fee_ids: [tax.id, fee.id],
  });
  await api.createProduct(eventId, {
    title: 'Seated Ticket',
    product_type: 'TICKET',
    type: 'TIERED',
    product_category_id: gaCategory.id,
    prices: [
      { price: 15, label: 'Balcony' },
      { price: 40, label: 'Front Row' },
    ],
  });
  await api.createProduct(eventId, {
    title: 'Supporter Donation',
    product_type: 'TICKET',
    type: 'DONATION',
    product_category_id: gaCategory.id,
    prices: [{ price: 5 }],
  });
  const secretVip = await api.createProduct(eventId, {
    title: 'Secret VIP',
    product_type: 'TICKET',
    type: 'PAID',
    product_category_id: gaCategory.id,
    prices: [{ price: 50 }],
    is_hidden_without_promo_code: true,
  });
  await api.createProduct(eventId, {
    title: 'Staff Comp',
    product_type: 'TICKET',
    type: 'FREE',
    product_category_id: gaCategory.id,
    prices: [{ price: 0 }],
    is_hidden: true,
  });
  await api.createProduct(eventId, {
    title: 'Event T-Shirt',
    product_type: 'GENERAL',
    type: 'PAID',
    product_category_id: extrasCategory.id,
    prices: [{ price: 10 }],
    tax_and_fee_ids: [tax.id, fee.id],
  });

  const promoCode = uniqueCode();
  await api.createPromoCode(eventId, {
    code: promoCode,
    discount_type: 'FIXED',
    discount: 10,
    applicable_product_ids: [secretVip.id],
  });

  await api.createQuestion(eventId, {
    title: 'T-shirt size',
    type: 'RADIO',
    belongs_to: 'PRODUCT',
    product_ids: [standard.id],
    options: ['Small', 'Medium', 'Large'],
    required: true,
    is_hidden: false,
  });
  await api.createQuestion(eventId, {
    title: 'How did you hear about us?',
    type: 'SINGLE_LINE_TEXT',
    belongs_to: 'ORDER',
    product_ids: [],
    required: true,
    is_hidden: false,
  });
  await api.createQuestion(eventId, {
    title: 'Anything else we should know?',
    type: 'SINGLE_LINE_TEXT',
    belongs_to: 'ORDER',
    product_ids: [],
    required: false,
    is_hidden: false,
  });

  await api.updateEventSettings(eventId, {
    payment_providers: ['STRIPE', 'OFFLINE'],
    offline_payment_instructions: OFFLINE_PAYMENT_INSTRUCTIONS,
    pre_checkout_message: PRE_CHECKOUT_MESSAGE,
    post_checkout_message: POST_CHECKOUT_MESSAGE,
  });
  await api.publishEvent(eventId);

  const scenario: KitchenSinkScenario = {
    eventId,
    slug,
    title,
    promoCode,
    gaCategoryName: gaCategory.name,
    extrasCategoryName: extrasCategory.name,
  };

  if (!isRecurring) {
    return scenario;
  }

  const standardProduct = await api.getProduct(eventId, standard.id);
  const standardPriceId = standardProduct.prices?.[0]?.id;
  if (!standardPriceId) {
    throw new Error(`Product ${standard.id} has no prices in GET response`);
  }
  return { ...scenario, occurrences, standardPriceId };
}

export interface KitchenSinkTotals {
  standardBase: string;
  standardInclusive: string;
  subtotal: string;
  fees: string;
  taxes: string;
  total: string;
}

export interface KitchenSinkCheckoutDeps {
  api: ApiClient;
  publicApi: APIRequestContext;
  mailpit: MailpitClient;
}

export interface KitchenSinkCheckoutOptions {
  paymentMode: 'offline' | 'stripe';
  attendeeCollection: 'PER_ATTENDEE' | 'PER_ORDER';
  totals: KitchenSinkTotals;
  surfaces?: {
    select: CheckoutSurface;
    resolveCheckout: () => Promise<CheckoutSurface>;
  };
  occurrence?: {
    select: (page: Page) => Promise<void>;
    expectSummaryDetails: (page: Page) => Promise<void>;
  };
  emailBodyContains?: string[];
}

async function fillContactBlock(root: CheckoutSurface, index: number, details: BuyerDetails): Promise<void> {
  await root.getByLabel(/^First Name/).nth(index).fill(details.firstName);
  await root.getByLabel(/^Last Name/).nth(index).fill(details.lastName);
  await root.getByLabel(/^Email Address/).nth(index).fill(details.email);
  await root.getByLabel(/^Confirm Email Address/).nth(index).fill(details.email);
}

export async function runKitchenSinkCheckout(
  page: Page,
  scenario: KitchenSinkScenario,
  deps: KitchenSinkCheckoutDeps,
  opts: KitchenSinkCheckoutOptions,
): Promise<void> {
  const { paymentMode, totals } = opts;
  if (opts.surfaces && paymentMode === 'stripe') {
    throw new Error('Stripe payment is not supported when running on embedded surfaces');
  }
  const buyer: BuyerDetails = { firstName: 'Kitchen', lastName: 'Sink', email: uniqueEmail('kitchensink') };
  const guests: BuyerDetails[] = opts.attendeeCollection === 'PER_ATTENDEE'
    ? [
        { firstName: 'Guest', lastName: 'One', email: uniqueEmail('guest1') },
        { firstName: 'Guest', lastName: 'Two', email: uniqueEmail('guest2') },
        { firstName: 'Guest', lastName: 'Three', email: uniqueEmail('guest3') },
        { firstName: 'Guest', lastName: 'Four', email: uniqueEmail('guest4') },
      ]
    : [];

  const selectRoot: CheckoutSurface = opts.surfaces?.select ?? page;
  const selection = new CheckoutPage(page, selectRoot);
  const productRow = (title: string) => selectRoot.locator('.hi-product-row').filter({ hasText: title });

  if (!opts.surfaces) {
    await selection.gotoPublicEvent(scenario.eventId, scenario.slug);
  }
  if (opts.occurrence) {
    await opts.occurrence.select(page);
  }

  await expect(selectRoot.getByRole('heading', { name: scenario.gaCategoryName })).toBeVisible();
  await expect(selectRoot.getByText(GA_DESCRIPTION)).toBeVisible();
  await expect(selectRoot.getByRole('heading', { name: scenario.extrasCategoryName })).toBeVisible();
  await expect(selectRoot.getByText(EXTRAS_DESCRIPTION)).toBeVisible();
  await expect(selectRoot.getByRole('heading', { name: 'Tickets', exact: true })).toBeVisible();
  await expect(selectRoot.getByText('There are no tickets available for this event')).toBeVisible();

  await expect(productRow('Standard Ticket').getByText(totals.standardInclusive).first()).toBeVisible();
  const seatedRow = productRow('Seated Ticket');
  const balconyTier = seatedRow.locator('.hi-price-tier-row').filter({ hasText: 'Balcony' });
  const frontRowTier = seatedRow.locator('.hi-price-tier-row').filter({ hasText: 'Front Row' });
  await expect(balconyTier.getByText('$15.00')).toBeVisible();
  await expect(frontRowTier.getByText('$40.00')).toBeVisible();
  const donationRow = productRow('Supporter Donation');
  await expect(donationRow.getByLabel(/^Amount/)).toBeVisible();
  await expect(productRow('Event T-Shirt').getByText('$13.75').first()).toBeVisible();
  await expect(selectRoot.getByText('Secret VIP')).toHaveCount(0);
  await expect(selectRoot.getByText('Staff Comp')).toHaveCount(0);

  await selection.applyPromoCode(scenario.promoCode);
  const vipRow = productRow('Secret VIP');
  await expect(vipRow.getByText('$40.00')).toBeVisible();
  await expect(vipRow.getByText('$50.00')).toBeVisible();
  await expect(productRow('Standard Ticket').getByText(totals.standardInclusive).first()).toBeVisible();
  await expect(balconyTier.getByText('$15.00')).toBeVisible();
  await expect(frontRowTier.getByText('$40.00')).toBeVisible();
  await expect(productRow('Event T-Shirt').getByText('$13.75').first()).toBeVisible();
  await expect(selectRoot.getByText('Staff Comp')).toHaveCount(0);

  await selection.setQuantityForProduct('Standard Ticket', 1);
  await setWidgetQuantity(frontRowTier, 1);
  await donationRow.getByLabel(/^Amount/).fill('12.50');
  await setWidgetQuantity(donationRow, 1);
  await selection.setQuantityForProduct('Event T-Shirt', 1);
  await selection.setQuantityForProduct('Secret VIP', 1);

  await selectRoot.getByTestId('checkout-continue-button').click();
  const checkoutRoot: CheckoutSurface = opts.surfaces ? await opts.surfaces.resolveCheckout() : page;
  await checkoutRoot.waitForURL(/\/checkout\/\d+\/[^/]+\/details/);
  const checkout = new CheckoutPage(page, checkoutRoot);

  const summaryLineItem = (name: string) => checkoutRoot.getByTitle(name).locator('../..');
  const summaryRow = (label: string) =>
    checkoutRoot.locator('[class*="totalsRow"]').filter({ has: checkoutRoot.getByText(label, { exact: true }) });
  const reloadCheckout = async () => {
    if (checkoutRoot === page) {
      await page.reload();
    } else {
      await (checkoutRoot as Frame).goto(checkoutRoot.url());
    }
    await checkoutRoot.waitForLoadState('networkidle');
  };

  const expectSummaryLineItems = async () => {
    await expect(summaryLineItem('Standard Ticket').getByText(totals.standardBase)).toBeVisible();
    await expect(summaryLineItem('Seated Ticket - Front Row').getByText('$40.00')).toBeVisible();
    await expect(summaryLineItem('Supporter Donation').getByText('$12.50')).toBeVisible();
    await expect(summaryLineItem('Event T-Shirt').getByText('$10.00')).toBeVisible();
    await expect(summaryLineItem('Secret VIP').getByText('$40.00')).toBeVisible();
    await expect(summaryLineItem('Secret VIP').getByText('$50.00')).toBeVisible();
  };
  const expectSummaryTotals = async () => {
    await expect(summaryRow('Subtotal')).toContainText(totals.subtotal);
    await expect(summaryRow('Fees')).toContainText(totals.fees);
    await expect(summaryRow('Taxes')).toContainText(totals.taxes);
    await expect(summaryRow('Total')).toContainText(totals.total);
  };
  const expectCompletedSummary = async () => {
    await expect(checkoutRoot.getByText(`You're going to ${scenario.title}`)).toBeVisible();
    await expect(checkoutRoot.getByRole('heading', { name: 'Additional Information' })).toBeVisible();
    await expect(checkoutRoot.getByText(POST_CHECKOUT_MESSAGE)).toBeVisible();
  };

  await expect(checkoutRoot.getByLabel(/^First Name/)).toHaveCount(1 + guests.length);
  const attendeeHeadingCount = opts.attendeeCollection === 'PER_ATTENDEE' ? 4 : 1;
  await expect(checkoutRoot.getByRole('heading', { name: 'Attendee 1' })).toHaveCount(attendeeHeadingCount);
  await expect(checkoutRoot.getByRole('heading', { name: 'Event T-Shirt' })).toHaveCount(0);
  await expect(checkoutRoot.getByText(PRE_CHECKOUT_MESSAGE)).toBeVisible();

  await expect(checkoutRoot.getByLabel(/^How did you hear about us/)).toBeVisible();
  await expect(checkoutRoot.getByLabel(/^Anything else we should know/)).toBeVisible();
  await expect(checkoutRoot.getByRole('radio', { name: 'Medium' })).toHaveCount(1);
  const standardSection = checkoutRoot.locator('[class*="ticketSection"]').filter({ hasText: 'Standard Ticket' });
  await expect(standardSection.getByRole('radio', { name: 'Medium' })).toBeVisible();

  await expectSummaryLineItems();
  await expectSummaryTotals();
  await expect(checkoutRoot.getByText('-$10.00')).toBeVisible();

  await checkout.fillOrderDetails(buyer);
  for (const [index, guest] of guests.entries()) {
    await fillContactBlock(checkoutRoot, index + 1, guest);
  }

  await checkoutRoot.getByRole('button', { name: 'Continue to Payment' }).click();
  await expect(checkoutRoot.getByText('This field is required.')).toHaveCount(2);

  await checkout.answerTextQuestion('How did you hear about us?', 'Kitchen sink e2e');
  await checkout.chooseRadioOption('Medium');
  await expectSummaryTotals();
  await checkout.continueToPayment();

  await expect(checkoutRoot.getByRole('button', { name: 'Online' })).toBeVisible();
  await expect(checkoutRoot.getByRole('button', { name: 'Offline' })).toBeVisible();
  await expect(checkoutRoot.getByRole('button', { name: `Pay ${totals.total}` })).toBeVisible();

  if (paymentMode === 'offline') {
    await checkout.chooseOfflinePayment();
    await expect(checkoutRoot.getByText('Your order is awaiting payment')).toBeVisible();
    await expect(checkoutRoot.getByRole('heading', { name: 'Payment Instructions' })).toBeVisible();
    await expect(checkoutRoot.getByText(OFFLINE_PAYMENT_INSTRUCTIONS)).toBeVisible();
  } else {
    await checkout.payWithStripeTestCard();
    const { orderShortId, sessionId } = parsePaymentReturnUrl(page.url());
    await deliverPaymentIntentSucceededWebhook(deps.publicApi, { eventId: scenario.eventId, orderShortId, sessionId });
    await page.waitForURL(/\/checkout\/\d+\/[^/]+\/summary/, { timeout: 30_000 });
    await page.reload();
    await page.waitForLoadState('networkidle');
  }

  await checkoutRoot.getByRole('button', { name: /Order Summary/ }).click();
  await expectSummaryLineItems();
  await expectSummaryTotals();
  for (const guest of guests) {
    await expect(checkoutRoot.getByText(`${guest.firstName} ${guest.lastName}`)).toBeVisible();
  }

  if (paymentMode === 'offline') {
    await expect(checkoutRoot.getByRole('heading', { name: 'Additional Information' })).toHaveCount(0);
    const orderShortId = checkoutRoot.url().match(/\/checkout\/\d+\/([^/?]+)\/summary/)![1];
    const orderId = await deps.api.findOrderIdByShortId(scenario.eventId, orderShortId);
    await deps.api.markOrderAsPaid(scenario.eventId, orderId);
    await reloadCheckout();
  }
  await expectCompletedSummary();
  if (opts.occurrence) {
    await opts.occurrence.expectSummaryDetails(page);
  }

  const emailSummary = await deps.mailpit.waitForMessage(buyer.email, { subjectContains: 'Your Order is Confirmed' });
  if (opts.emailBodyContains?.length) {
    const message = await deps.mailpit.getMessage(emailSummary.ID);
    const body = `${message.Text}\n${message.HTML}`;
    for (const expected of opts.emailBodyContains) {
      expect(body).toContain(expected);
    }
  }
}
