import { test, expect } from '../../fixtures';
import { CapacityPage } from '../../pages/capacity.page';
import { createCompletedOrder, createDraftEventWithTicket, createLiveEventWithFreeTicket } from '../../api/factory';
import { uniqueShort } from '../../utils/unique';

test.describe('capacity assignments', () => {
  test('an organizer creates a capacity assignment and sees it in the list', async ({ authedPage, api, account }) => {
    const event = await createDraftEventWithTicket(api, account.organizerId);
    const name = uniqueShort('Capacity');

    const capacity = new CapacityPage(authedPage);
    await capacity.goto(event.eventId);
    await capacity.createAssignment({ name, capacity: 50, productTitle: event.productTitle });

    const card = capacity.cardByName(name);
    await expect(card).toBeVisible();
    await expect(card.getByText('0/50')).toBeVisible();
    await expect(card.getByText(/^ACTIVE$/)).toBeVisible();
    await expect(card.getByText('Applies to 1 product')).toBeVisible();
  });

  test('a completed order consumes shared capacity', async ({ authedPage, api, account, publicApi }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    const name = uniqueShort('Capacity');
    await api.createCapacityAssignment(event.eventId, {
      name,
      capacity: 50,
      status: 'ACTIVE',
      product_ids: [event.productId],
    });
    await createCompletedOrder(publicApi, event);

    const capacity = new CapacityPage(authedPage);
    await capacity.goto(event.eventId);

    const card = capacity.cardByName(name);
    await expect(card).toBeVisible();
    await expect(card.getByText('1/50')).toBeVisible();
  });

  test('an organizer edits a capacity assignment', async ({ authedPage, api, account }) => {
    const event = await createDraftEventWithTicket(api, account.organizerId);
    const name = uniqueShort('Capacity');
    await api.createCapacityAssignment(event.eventId, {
      name,
      capacity: 50,
      status: 'ACTIVE',
      product_ids: [event.productId],
    });

    const capacity = new CapacityPage(authedPage);
    await capacity.goto(event.eventId);
    await capacity.openCardAction(name, 'Edit Capacity');

    await expect(authedPage.getByRole('heading', { name: 'Edit Capacity Assignment' })).toBeVisible();
    await expect(capacity.capacityInput()).toHaveValue('50');
    await capacity.submitEdit(75);

    await expect(capacity.cardByName(name).getByText('0/75')).toBeVisible();
  });

  test('an organizer deletes a capacity assignment', async ({ authedPage, api, account }) => {
    const event = await createDraftEventWithTicket(api, account.organizerId);
    const name = uniqueShort('Capacity');
    await api.createCapacityAssignment(event.eventId, {
      name,
      capacity: 50,
      status: 'ACTIVE',
      product_ids: [event.productId],
    });

    const capacity = new CapacityPage(authedPage);
    await capacity.goto(event.eventId);
    await capacity.openCardAction(name, 'Delete Capacity');
    await capacity.confirmAction();

    await expect(authedPage.getByRole('heading', { name: 'No Capacity Assignments' })).toBeVisible();
    await expect(capacity.cardByName(name)).toHaveCount(0);
  });
});
