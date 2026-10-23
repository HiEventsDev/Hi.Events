import { test, expect } from '../../fixtures';
import { DesignerPage } from '../../pages/designer.page';
import { createDraftEventWithTicket, createLiveEventWithFreeTicket } from '../../api/factory';

test.describe('homepage designer', () => {
  test('an organizer previews and saves a new continue button label', { tag: '@smoke' }, async ({ authedPage, api, account }) => {
    const event = await createDraftEventWithTicket(api, account.organizerId);
    const designer = new DesignerPage(authedPage);
    await designer.gotoHomepageDesigner(event.eventId);

    await expect(designer.savedStatus).toBeVisible();
    await expect(designer.saveButton).toBeDisabled();

    await designer.continueButtonInput.fill('Grab a spot');
    await expect(designer.unsavedStatus).toBeVisible();
    await expect(designer.eventPreview.getByRole('button', { name: 'Grab a spot' })).toBeVisible();

    await designer.saveButton.click();
    await expect(authedPage.getByText('Successfully Updated Homepage Design')).toBeVisible();
    await expect(designer.savedStatus).toBeVisible();

    await expect
      .poll(async () => (await api.getEventSettings(event.eventId)).continue_button_text)
      .toBe('Grab a spot');

    await authedPage.reload();
    await authedPage.waitForLoadState('networkidle');
    await expect(designer.continueButtonInput).toHaveValue('Grab a spot');
  });

  test('an organizer is warned before leaving with unsaved changes and can discard them', async ({ authedPage, api, account }) => {
    const event = await createDraftEventWithTicket(api, account.organizerId);
    const designer = new DesignerPage(authedPage);
    await designer.gotoHomepageDesigner(event.eventId);

    const originalLabel = await designer.continueButtonInput.inputValue();
    await designer.continueButtonInput.fill('Not saved');

    await authedPage.getByRole('link', { name: 'Ticket Designer' }).click();
    const leaveDialog = authedPage.getByRole('dialog', { name: /Leave without saving/ });
    await expect(leaveDialog).toBeVisible();
    await leaveDialog.getByRole('button', { name: 'Cancel' }).click();
    await expect(leaveDialog).toBeHidden();
    await expect(authedPage).toHaveURL(/\/homepage-designer$/);

    await designer.discardButton.click();
    await expect(designer.continueButtonInput).toHaveValue(originalLabel);
    await expect(designer.savedStatus).toBeVisible();

    await authedPage.getByRole('link', { name: 'Ticket Designer' }).click();
    await expect(authedPage).toHaveURL(/\/ticket-designer$/);
  });

  test('an organizer switches the page layout, sees it in the preview and publishes it', async ({ authedPage, page, api, account }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    const designer = new DesignerPage(authedPage);
    await designer.gotoHomepageDesigner(event.eventId);

    await expect(designer.layoutOption('story')).toHaveAttribute('aria-checked', 'true');
    await expect(authedPage.getByText('A focused reading column.', { exact: false })).toBeVisible();

    await designer.layoutOption('invite').click();
    await expect(designer.layoutOption('invite')).toHaveAttribute('aria-checked', 'true');
    await expect(designer.unsavedStatus).toBeVisible();
    await expect(designer.eventPreview.getByRole('heading', { level: 1, name: event.title })).toHaveCSS('text-align', 'center');

    await designer.saveButton.click();
    await expect(authedPage.getByText('Successfully Updated Homepage Design')).toBeVisible();
    await expect
      .poll(async () => ((await api.getEventSettings(event.eventId)).homepage_theme_settings as { layout?: string }).layout)
      .toBe('INVITE');

    await page.goto(`/event/${event.eventId}/${event.slug}`);
    await expect(page.getByRole('heading', { level: 1, name: event.title })).toHaveCSS('text-align', 'center');
  });
});
