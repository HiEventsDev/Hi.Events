import { test, expect } from '../../fixtures';
import { createDraftEvent } from '../../api/factory';
import { EventSettingsPage } from '../../pages/event-settings.page';
import { invitedMember } from '../../utils/team';
import { uniqueName } from '../../utils/unique';

test.describe('offline payment instructions access', () => {
  test('an organizer without payment access sees the instructions read-only and can still save payment settings', async ({ freshAccount, mailpit, publicApi, browser }) => {
    const event = await createDraftEvent(freshAccount.api, freshAccount.organizerId);
    const instructions = `<p>${uniqueName('Transfer to IBAN IE00 OWNER')}</p>`;
    await freshAccount.api.updateEventSettings(event.eventId, {
      payment_providers: ['STRIPE', 'OFFLINE'],
      offline_payment_instructions: instructions,
    });

    const { page } = await invitedMember(freshAccount, mailpit, publicApi, browser, { role: 'ORGANIZER' });
    const settings = new EventSettingsPage(page);
    await settings.goto(event.eventId);

    const payment = settings.section('payment-settings');
    await expect(payment.getByText(instructions.replace(/<\/?p>/g, ''))).toBeVisible();
    await expect(payment.getByText('Only members who can manage payments can change these instructions.')).toBeVisible();
    await expect(settings.offlineInstructionsEditor).toHaveCount(0);

    await payment.getByLabel('Require Billing Address').uncheck({ force: true });
    await settings.saveSection('payment-settings');
    await expect(page.getByText('Successfully Updated Payment & Invoicing Settings')).toBeVisible();

    await expect.poll(async () => (await freshAccount.api.getEventSettings(event.eventId)).require_billing_address).toBe(false);
    const saved = await freshAccount.api.getEventSettings(event.eventId);
    expect(saved.offline_payment_instructions).toBe(instructions);
    expect(saved.payment_providers).toEqual(['STRIPE', 'OFFLINE']);
  });
});
