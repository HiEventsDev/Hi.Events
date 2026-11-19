import { test, expect } from '../../fixtures';
import { AudiencePage } from '../../pages/audience.page';
import { uniqueShort } from '../../utils/unique';
import { buildAudience } from './audience.shared';
import { withMessagingApi } from './messaging.shared';

test.describe('audience campaign templates', () => {
  test('a template is written, edited, used to start a campaign and deleted without touching that campaign', async ({ freshAccount }) => {
    const { api, organizerId } = freshAccount;
    await buildAudience(api, organizerId);
    const name = uniqueShort('Monthly roundup');
    const subject = uniqueShort('This month');

    const page = await freshAccount.newAuthedPage();
    await page.goto(`/manage/organizer/${organizerId}/audience/templates`);
    await expect(page.getByText('No templates yet.')).toBeVisible();
    await page.getByTestId('template-create-button').click();
    const editor = page.getByRole('dialog', { name: 'New template' });
    await page.getByTestId('template-save').click();
    await expect(editor.getByText('Give this template a name')).toBeVisible();
    await expect(editor.getByText('Write a message')).toBeVisible();

    await page.getByTestId('template-name').fill(name);
    await editor.getByRole('textbox', { name: 'Subject line' }).fill(subject);
    await editor.locator('[contenteditable="true"]').fill('Here is everything coming up.');
    await page.getByTestId('template-save').click();
    await expect(page.getByText('Template created')).toBeVisible();
    const row = page.getByTestId('template-row').filter({ hasText: name });
    await expect(row).toContainText(subject);
    await expect(row).toContainText('Not used yet');

    await page.getByTestId('template-create-button').click();
    await page.getByTestId('template-name').fill(name.toUpperCase());
    await editor.locator('[contenteditable="true"]').fill('A second template with the same name.');
    await page.getByTestId('template-save').click();
    await expect(editor.getByText(/You already have a template called ".+"\. Choose another name\./)).toBeVisible();
    await expect(page.getByTestId('template-row')).toHaveCount(1);
    await editor.getByRole('button', { name: 'Cancel' }).click();

    await row.getByRole('button', { name: 'Template actions' }).click();
    await page.getByRole('menuitem', { name: 'Edit' }).click();
    const edit = page.getByRole('dialog', { name: 'Edit template' });
    await edit.getByRole('textbox', { name: 'Preview text' }).fill('Dates for your diary.');
    await page.getByTestId('template-save').click();
    await expect(page.getByText('Template saved')).toBeVisible();

    await expect(row).toContainText('Edited');
    await row.getByTestId('template-use').click();
    await expect(page).toHaveURL(/\/audience\/campaigns\/new\?template=\d+/);
    await expect(page.getByTestId('campaign-subject')).toHaveValue(subject);
    await expect(page.getByTestId('campaign-preheader')).toHaveValue('Dates for your diary.');
    await expect(page.getByTestId('campaign-body')).toContainText('Here is everything coming up.');
    await page.getByRole('button', { name: 'Save draft' }).click();
    await page.waitForURL(/\/audience\/campaigns\/\d+$/);
    const campaignUrl = page.url();

    await page.goto(`/manage/organizer/${organizerId}/audience/templates`);
    await expect(row).not.toContainText('Not used yet');
    await expect(row).toContainText('Last used');
    await row.getByRole('button', { name: 'Template actions' }).click();
    await page.getByRole('menuitem', { name: 'Delete' }).click();
    await expect(page.getByRole('dialog', { name: `Delete the template "${name}"?` })).toContainText("Campaigns you already made from it aren't affected.");
    await new AudiencePage(page, organizerId).confirm('Delete template');
    await expect(page.getByText('Template deleted')).toBeVisible();
    await expect(page.getByTestId('template-row')).toHaveCount(0);

    await page.goto(campaignUrl);
    await expect(page.getByTestId('campaign-subject')).toHaveValue(subject);
  });

  test('a new campaign starts from a ready-made starter or one of your own templates, found by search and previewed first', async ({ freshAccount, playwright }) => {
    const { api, organizerId } = freshAccount;
    await buildAudience(api, organizerId);
    const name = uniqueShort('Season opener');
    const subject = uniqueShort('The season starts here');
    await withMessagingApi(playwright, freshAccount, (messaging) =>
      messaging.createTemplate({ name, subject, body: '<p>Our first night back is nearly here.</p>' }));

    const page = await freshAccount.newAuthedPage();
    const audience = new AudiencePage(page, organizerId);
    await audience.gotoCampaigns();
    await audience.campaignCreateButton().click();
    const picker = page.getByRole('dialog', { name: 'New campaign' });
    for (const starter of ['Event announcement', 'Last chance', 'Thank you for coming', 'Newsletter']) {
      await expect(picker.getByText(starter, { exact: true })).toBeVisible();
    }
    await expect(picker.getByText('An empty message, ready for your words.')).toBeVisible();

    await picker.getByRole('textbox', { name: 'Search templates' }).fill('no template is called this');
    await expect(picker.getByText('Nothing matches your search.')).toBeVisible();
    await picker.getByRole('textbox', { name: 'Search templates' }).fill(name);
    await expect(picker.getByText('Newsletter', { exact: true })).toHaveCount(0);
    await picker.getByText(name, { exact: true }).click();
    await expect(picker.getByRole('strong').filter({ hasText: subject })).toBeVisible();
    await expect(picker.getByTitle('Template preview').contentFrame().getByText('Our first night back is nearly here.')).toBeVisible();
    await page.getByTestId('campaign-picker-start').click();
    await expect(page).toHaveURL(/\/audience\/campaigns\/new\?template=\d+/);
    await expect(page.getByTestId('campaign-subject')).toHaveValue(subject);
    await expect(page.getByTestId('campaign-body')).toContainText('Our first night back is nearly here.');

    await audience.gotoCampaigns();
    await audience.campaignCreateButton().click();
    await page.getByTestId('campaign-picker-starter-newsletter').click();
    await expect(picker.getByTitle('Template preview')).toBeVisible();
    await page.getByTestId('campaign-picker-start').click();
    await expect(page).toHaveURL(/\/audience\/campaigns\/new\?starter=newsletter/);
    await expect(page.getByTestId('campaign-subject')).toHaveValue('News from {{ organizer_name }}');
    await expect(page.getByTestId('campaign-body')).toContainText('Hi');
  });
});
