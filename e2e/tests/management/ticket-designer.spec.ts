import { test, expect } from '../../fixtures';
import { DesignerPage } from '../../pages/designer.page';
import { createDraftEventWithTicket } from '../../api/factory';
import { uniqueName } from '../../utils/unique';

test.describe('ticket designer', () => {
  test('an organizer previews and saves a ticket footer', async ({ authedPage, api, account }) => {
    const event = await createDraftEventWithTicket(api, account.organizerId);
    const footer = uniqueName('Doors open at 7pm');
    const designer = new DesignerPage(authedPage);
    await designer.gotoTicketDesigner(event.eventId);

    await expect(designer.savedStatus).toBeVisible();
    await expect(designer.saveButton).toBeDisabled();

    await designer.footerTextInput.fill(footer);
    await expect(designer.unsavedStatus).toBeVisible();
    await expect(authedPage.getByRole('article').getByText(footer, { exact: true })).toBeVisible();

    await designer.saveButton.click();
    await expect(authedPage.getByText('Ticket design saved successfully')).toBeVisible();
    await expect(designer.savedStatus).toBeVisible();

    await expect
      .poll(async () => {
        const settings = (await api.getEventSettings(event.eventId)).ticket_design_settings as { footer_text?: string } | undefined;
        return settings?.footer_text;
      })
      .toBe(footer);

    await authedPage.reload();
    await authedPage.waitForLoadState('networkidle');
    await expect(designer.footerTextInput).toHaveValue(footer);
    await expect(designer.saveButton).toBeDisabled();
  });
});
