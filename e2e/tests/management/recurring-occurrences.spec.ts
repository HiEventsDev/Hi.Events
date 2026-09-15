import { test, expect } from '../../fixtures';
import { OccurrencePage } from '../../pages/occurrence.page';
import { createRecurringLiveEvent } from '../../api/factory';
import { uniqueName } from '../../utils/unique';

const WEEKDAY_LABELS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

test.describe('recurring occurrences', () => {
  test('an organizer generates weekly occurrences from the schedule modal', async ({ authedPage, api, account }) => {
    const startDate = new Date();
    startDate.setDate(startDate.getDate() + 30);
    const event = await api.createEvent({
      title: uniqueName('E2E Recurring'),
      type: 'RECURRING',
      organizer_id: account.organizerId,
      start_date: startDate.toISOString(),
      category: 'MUSIC',
      currency: 'USD',
      timezone: 'UTC',
    });
    const targetDay = new Date();
    targetDay.setDate(targetDay.getDate() + 2);
    const weekday = WEEKDAY_LABELS[targetDay.getDay()];

    const occurrences = new OccurrencePage(authedPage);
    await occurrences.goto(event.id);
    await occurrences.openScheduleSetup();
    await occurrences.pickWeekday(weekday);
    await occurrences.chooseFixedNumberOfDates(4);
    await occurrences.submitSchedule();

    await expect(authedPage.getByText('Showing 1–4 of 4')).toBeVisible();
    await expect(occurrences.occurrenceRows()).toHaveCount(4);
  });

  test('an organizer cancels and reactivates an occurrence', async ({ authedPage, api, account }) => {
    const event = await createRecurringLiveEvent(api, account.organizerId, { count: 3 });

    const occurrences = new OccurrencePage(authedPage);
    await occurrences.goto(event.eventId);
    await expect(occurrences.statusBadges('ACTIVE')).toHaveCount(3);

    await occurrences.chooseRowAction(occurrences.occurrenceRows().first(), 'Cancel');
    await occurrences.confirmModalAction('Cancel Date');
    await expect(occurrences.statusBadges('CANCELLED')).toHaveCount(1);

    await occurrences.chooseRowAction(occurrences.rowWithStatus('CANCELLED'), 'Reopen for new sales');
    await occurrences.confirmModalAction('Confirm');
    await expect(occurrences.statusBadges('CANCELLED')).toHaveCount(0);
    await expect(occurrences.statusBadges('ACTIVE')).toHaveCount(3);
  });

  test('an organizer overrides a product price for one occurrence', async ({ authedPage, api, account }) => {
    const event = await createRecurringLiveEvent(api, account.organizerId, { count: 3, price: 25 });

    const occurrences = new OccurrencePage(authedPage);
    await occurrences.goto(event.eventId);
    await occurrences.chooseRowAction(occurrences.occurrenceRows().first(), 'Edit');
    await occurrences.openProductsTab();
    await expect(occurrences.productCard(event.productTitle)).toBeVisible();
    await expect(occurrences.productCard(event.productTitle).getByText('25.00')).toBeVisible();

    await occurrences.overrideInput().fill('30');
    await occurrences.saveProductSettings();
    await expect(authedPage.getByText('Product settings saved successfully')).toBeVisible();
    await occurrences.closeModal();
    await expect(occurrences.dialog()).toBeHidden();

    await occurrences.chooseRowAction(occurrences.occurrenceRows().first(), 'Edit');
    await occurrences.openProductsTab();
    await expect(occurrences.overrideInput()).toHaveValue('30.00');
  });

  test('an organizer limits a product quantity for one occurrence', async ({ authedPage, api, account }) => {
    const event = await createRecurringLiveEvent(api, account.organizerId, { count: 3 });

    const occurrences = new OccurrencePage(authedPage);
    await occurrences.goto(event.eventId);
    await occurrences.chooseRowAction(occurrences.occurrenceRows().first(), 'Products');
    await expect(occurrences.productCard(event.productTitle)).toBeVisible();
    await expect(occurrences.quantityOverrideInput()).toHaveAttribute('placeholder', 'Unlimited');

    await occurrences.quantityOverrideInput().fill('3');
    await occurrences.saveProductSettings();
    await expect(authedPage.getByText('Product settings saved successfully')).toBeVisible();
    await occurrences.closeModal();
    await expect(occurrences.dialog()).toBeHidden();

    await occurrences.chooseRowAction(occurrences.occurrenceRows().first(), 'Products');
    await expect(occurrences.quantityOverrideInput()).toHaveValue('3');
    await expect(occurrences.overrideInput()).toHaveValue('');
  });

  test('adding a single date after closing a date opened on its Products tab shows the empty details form', async ({ authedPage, api, account }) => {
    const event = await createRecurringLiveEvent(api, account.organizerId, { count: 2 });

    const occurrences = new OccurrencePage(authedPage);
    await occurrences.goto(event.eventId);
    await occurrences.chooseRowAction(occurrences.occurrenceRows().first(), 'Products');
    await expect(occurrences.productCard(event.productTitle)).toBeVisible();
    await occurrences.closeModal();
    await expect(occurrences.dialog()).toBeHidden();

    await occurrences.addSingleDate();
    await expect(occurrences.dialog().getByRole('heading', { name: 'Add Date' })).toBeVisible();
    await expect(occurrences.dialog().getByRole('tab', { name: 'Products' })).toHaveCount(0);
    await expect(occurrences.dialog().getByLabel('Start Date')).toHaveValue('');
    await expect(occurrences.dialog().getByLabel('Label')).toBeVisible();
  });

  test('an organizer hides a product for one occurrence', async ({ authedPage, api, account }) => {
    const event = await createRecurringLiveEvent(api, account.organizerId, { count: 3 });
    const categories = await api.listProductCategories(event.eventId);
    await api.createProduct(event.eventId, {
      title: 'VIP Ticket',
      product_type: 'TICKET',
      type: 'FREE',
      product_category_id: categories[0].id,
      prices: [{ price: 0 }],
    });

    const occurrences = new OccurrencePage(authedPage);
    await occurrences.goto(event.eventId);
    await occurrences.chooseRowAction(occurrences.occurrenceRows().first(), 'Edit');
    await occurrences.openProductsTab();
    await expect(occurrences.productSwitch('VIP Ticket')).toBeChecked();

    await occurrences.productSwitch('VIP Ticket').uncheck({ force: true });
    await occurrences.saveProductSettings();
    await expect(authedPage.getByText('Product settings saved successfully')).toBeVisible();
    await occurrences.closeModal();
    await expect(occurrences.dialog()).toBeHidden();

    await occurrences.chooseRowAction(occurrences.occurrenceRows().first(), 'Edit');
    await occurrences.openProductsTab();
    await expect(occurrences.productSwitch(event.productTitle)).toBeChecked();
    await expect(occurrences.productSwitch('VIP Ticket')).not.toBeChecked();
  });

  test('an organizer can read how capacity works while editing a date', async ({ authedPage, api, account }) => {
    const event = await createRecurringLiveEvent(api, account.organizerId, { count: 2 });

    const occurrences = new OccurrencePage(authedPage);
    await occurrences.goto(event.eventId);
    await occurrences.chooseRowAction(occurrences.occurrenceRows().first(), 'Edit');
    await occurrences.openCapacityHelp();

    await expect(authedPage.getByText("Set it in each ticket's Quantity field on Tickets & Products, when you create or edit the ticket.")).toBeVisible();
    await expect(authedPage.getByRole('dialog').last().getByRole('link', { name: 'Tickets & Products' })).toHaveAttribute('href', `/manage/event/${event.eventId}/products`);
    await expect(occurrences.capacityHelpScenarios()).toHaveCount(4);
    await expect(occurrences.capacityHelpScenarios().nth(1)).toContainText('Workshop with early bird');
    await expect(occurrences.capacityHelpScenarios().nth(1)).toContainText('Sellable per date20');
    await expect(authedPage.getByText('Only tickets count toward capacity. Products such as merchandise or parking never do.')).toBeVisible();
  });
});
