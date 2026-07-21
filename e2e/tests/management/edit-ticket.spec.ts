import { test, expect } from '../../fixtures';
import { ProductEditPage } from '../../pages/product-edit.page';
import { createDraftEventWithTicket } from '../../api/factory';
import { uniqueName } from '../../utils/unique';

test.describe('editing a ticket', () => {
  test('an organizer renames a ticket and sees the new name in the list', { tag: '@smoke' }, async ({ authedPage, api, account }) => {
    const event = await createDraftEventWithTicket(api, account.organizerId, { productTitle: 'General Admission' });
    const newName = uniqueName('VIP Ticket');

    const products = new ProductEditPage(authedPage);
    await products.goto(event.eventId);
    await products.renameFirstProduct('General Admission', newName);

    await expect(authedPage.getByRole('heading', { name: newName })).toBeVisible();
    await expect(authedPage.getByRole('heading', { name: 'General Admission' })).toBeHidden();
  });
});
