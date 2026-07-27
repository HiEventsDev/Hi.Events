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
});
