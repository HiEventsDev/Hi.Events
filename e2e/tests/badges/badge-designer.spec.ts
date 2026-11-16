import { test, expect } from '../../fixtures';
import { BadgeDesignerPage } from '../../pages/badges.page';
import { createLiveEventWithFreeTicket } from '../../api/factory';

test.describe('badge designer', () => {
  test('an organizer picks a template, labels a ticket and saves the design', { tag: '@smoke' }, async ({ authedPage, api, account }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    await api.enableBadges(event.eventId);
    await api.createQuestion(event.eventId, {
      title: 'Company',
      type: 'SINGLE_LINE_TEXT',
      belongs_to: 'PRODUCT',
      product_ids: [event.productId],
      required: false,
      is_hidden: false,
    });

    const designer = new BadgeDesignerPage(authedPage);
    await designer.goto(event.eventId);

    await expect(designer.lineSource(0)).toHaveValue('First name');
    await expect(designer.lineSource(2)).toHaveValue('Company');
    await expect(designer.preview).toContainText('Northwind Labs');

    await designer.templateOption('bold').click();
    await designer.ribbonLabelInput(event.productTitle).fill('SPEAKER');
    await expect(designer.preview).toContainText('SPEAKER');
    await designer.saveButton.click();
    await expect(designer.savedStatus).toBeVisible();

    await authedPage.reload();
    await authedPage.waitForLoadState('networkidle');
    await expect(designer.templateOption('bold')).toHaveAttribute('aria-checked', 'true');

    const design = await api.getBadgeDesign(event.eventId);
    expect(design.template).toBe('BOLD');
    expect(design.ribbons).toEqual([expect.objectContaining({ product_id: event.productId, label: 'SPEAKER' })]);
  });

  test('collecting company and job title adds checkout questions and badge lines', async ({ authedPage, api, account }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    await api.enableBadges(event.eventId);

    const designer = new BadgeDesignerPage(authedPage);
    await designer.goto(event.eventId);
    await designer.addCompanyQuestionsButton.click();

    await expect(designer.lineSource(2)).toHaveValue('Company');
    await expect(designer.lineSource(3)).toHaveValue('Job title');
    await expect(designer.preview).toContainText('Head of Partnerships');
    await expect(designer.addCompanyQuestionsButton).toBeHidden();

    await designer.saveButton.click();
    await expect(designer.savedStatus).toBeVisible();

    const design = await api.getBadgeDesign(event.eventId);
    expect(design.lines.filter((line) => line.source === 'QUESTION')).toHaveLength(2);
  });

  test('a custom text line can be added and shows on the preview', async ({ authedPage, api, account }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    await api.enableBadges(event.eventId);

    const designer = new BadgeDesignerPage(authedPage);
    await designer.goto(event.eventId);
    await designer.addLineButton.click();
    await authedPage.getByTestId('badge-line-2-text').fill('Ask me about volunteering');

    await expect(designer.preview).toContainText('Ask me about volunteering');
  });

  test('an organizer switches a two-sided design to fold-over', async ({ authedPage, api, account }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    await api.enableBadges(event.eventId);

    const designer = new BadgeDesignerPage(authedPage);
    await designer.goto(event.eventId);
    await designer.chooseBackMode('Same on both sides');
    await designer.chooseBackPrinting('Fold-over');

    await expect(designer.paperSummary).toHaveText('2 fold-over badges per sheet (2 × 1). Cut along the guides, then fold each one in half.');
    await designer.saveButton.click();
    await expect(designer.savedStatus).toBeVisible();

    const design = await api.getBadgeDesign(event.eventId);
    expect(design.back_mode).toBe('SAME');
    expect(design.fold_over).toBe(true);
  });
});
