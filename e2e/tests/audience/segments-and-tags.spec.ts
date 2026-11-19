import type { APIRequestContext } from '@playwright/test';
import { test, expect, type FreshAccount } from '../../fixtures';
import { createCompletedOrder, createLiveEventWithFreeTicket, type SeededEvent } from '../../api/factory';
import { AudiencePage } from '../../pages/audience.page';
import { uniqueEmail, uniqueShort } from '../../utils/unique';
import { buildAudience } from './audience.shared';
import {
  chooseRule,
  chooseRuleOption,
  closeDialog,
  openManageTags,
  readyMadeSegment,
  ruleValue,
  segmentRow,
  tagRow,
  withContactsApi,
} from './contacts.helpers';

const arrangeRegularAndNewcomer = async (freshAccount: FreshAccount, publicApi: APIRequestContext, family: string): Promise<{ second: SeededEvent }> => {
  const { api, organizerId } = freshAccount;
  const first = await createLiveEventWithFreeTicket(api, organizerId);
  const second = await createLiveEventWithFreeTicket(api, organizerId);
  const regular = uniqueEmail('regular');
  await createCompletedOrder(publicApi, first, { buyerEmail: regular, buyerFirstName: 'Reg', buyerLastName: family });
  await createCompletedOrder(publicApi, second, { buyerEmail: regular, buyerFirstName: 'Reg', buyerLastName: family });
  await createCompletedOrder(publicApi, first, { buyerEmail: uniqueEmail('newcomer'), buyerFirstName: 'Noa', buyerLastName: family });
  await buildAudience(api, organizerId);
  return { second };
};

test.describe('audience segments and tags', () => {
  test('a segment is created from a rule, edited, chosen from the contact list alongside the ready-made ones, and deleted', async ({ freshAccount, publicApi }) => {
    const family = uniqueShort('Segmented');
    await arrangeRegularAndNewcomer(freshAccount, publicApi, family);
    const name = uniqueShort('Regulars');

    const page = await freshAccount.newAuthedPage();
    const audience = new AudiencePage(page, freshAccount.organizerId);
    await audience.gotoSegments();
    await expect(readyMadeSegment(page, 'Repeat buyers')).toContainText('1');
    await expect(readyMadeSegment(page, 'Repeat buyers')).toContainText('Booked two or more of your events');
    await expect(page.getByText('No segments of your own yet.')).toBeVisible();

    await audience.segmentCreateButton().click();
    const modal = page.getByRole('dialog', { name: 'New segment' });
    await page.getByTestId('segment-name').fill(name);
    await expect(page.getByTestId('segment-live-count')).toHaveText('Add a rule to see who matches');
    await expect(page.getByTestId('segment-save')).toBeDisabled();
    await chooseRule(page, modal, 'Number of events booked');
    await expect(page.getByTestId('segment-live-count')).toHaveText('Finish the rules to see who matches');
    await expect(page.getByTestId('segment-save')).toBeDisabled();
    await ruleValue(modal).fill('2');
    await expect(page.getByTestId('segment-live-count')).toContainText('1 person matches');
    await page.getByTestId('segment-save').click();
    await expect(modal).toBeHidden();

    const row = segmentRow(page, name);
    await expect(row).toContainText('1 person');
    await expect(row).toContainText('1 rule');

    await row.getByRole('button', { name: 'Segment actions' }).click();
    await page.getByRole('menuitem', { name: 'Edit' }).click();
    const edit = page.getByRole('dialog', { name: 'Edit segment' });
    await expect(page.getByTestId('segment-name')).toHaveValue(name);
    await expect(ruleValue(edit)).toHaveValue('2');
    await ruleValue(edit).fill('1');
    await expect(page.getByTestId('segment-live-count')).toContainText('2 people match');
    await page.getByTestId('segment-save').click();
    await expect(row).toContainText('2 people');

    await audience.gotoContacts();
    await expect(audience.contactTotal()).toHaveText('2 contacts');
    await audience.segmentSelect().click();
    await page.getByRole('option', { name: 'Repeat buyers' }).click();
    await expect(page).toHaveURL(/segment=preset(:|%3A)repeat_buyers/);
    await expect(audience.contactTotal()).toHaveText('1 contact');
    await expect(audience.contactRow(`Reg ${family}`)).toBeVisible();
    await expect(page.getByText('Booked two or more of your events')).toBeVisible();
    await audience.segmentSelect().click();
    await page.getByRole('option', { name }).click();
    await expect(audience.contactTotal()).toHaveText('2 contacts');

    await audience.gotoSegments();
    await row.getByRole('button', { name: 'Segment actions' }).click();
    await page.getByRole('menuitem', { name: 'Delete' }).click();
    await expect(page.getByRole('dialog', { name: `Delete the segment "${name}"?` })).toContainText("The people in it aren't affected.");
    await audience.confirm('Delete segment');
    await expect(page.getByRole('link', { name, exact: true })).toHaveCount(0);
    await expect(page.getByText('No segments of your own yet.')).toBeVisible();
  });

  test('a segment combines a tag rule with an event rule, matching all or any of them', async ({ freshAccount, publicApi, playwright }) => {
    const family = uniqueShort('Combined');
    const { second } = await arrangeRegularAndNewcomer(freshAccount, publicApi, family);
    const { organizerId } = freshAccount;
    const tag = uniqueShort('VIP');
    await withContactsApi(playwright, freshAccount, (contactsApi) =>
      contactsApi.importContacts(`Email,First Name,Last Name\n${uniqueEmail('outsider')},Otto,${family}\n`, { tag }));
    const name = uniqueShort('VIPs or second night');

    const page = await freshAccount.newAuthedPage();
    const audience = new AudiencePage(page, organizerId);
    await audience.gotoSegments();
    await audience.segmentCreateButton().click();
    const modal = page.getByRole('dialog', { name: 'New segment' });
    await page.getByTestId('segment-name').fill(name);

    await chooseRule(page, modal, 'Tag');
    await chooseRuleOption(page, modal, tag);
    await expect(page.getByTestId('segment-live-count')).toContainText('1 person matches');

    await modal.getByRole('button', { name: 'Add a rule' }).click();
    await chooseRule(page, modal, 'Had a ticket for');
    await expect(page.getByTestId('segment-live-count')).toHaveText('Finish the rules to see who matches');
    await modal.getByLabel('Options').last().fill(second.title);
    await page.getByRole('option', { name: new RegExp(second.title) }).click();
    await page.keyboard.press('Escape');
    await expect(page.getByTestId('segment-live-count')).toContainText('0 people match');

    await modal.getByText('any rule', { exact: true }).click();
    await expect(page.getByTestId('segment-live-count')).toContainText('2 people match');
    await page.getByTestId('segment-save').click();
    await expect(modal).toBeHidden();

    const row = segmentRow(page, name);
    await expect(row).toContainText('2 people');
    await expect(row).toContainText('2 rules');
    await row.getByRole('link', { name, exact: true }).click();
    await expect(audience.contactTotal()).toHaveText('2 contacts');
    await expect(audience.contactRow(`Reg ${family}`)).toBeVisible();
    await expect(audience.contactRow(`Otto ${family}`)).toBeVisible();
  });

  test('a segment a draft campaign sends to warns before it is deleted, and a tag its rules use cannot be deleted', async ({ freshAccount, playwright }) => {
    const { api, organizerId } = freshAccount;
    await buildAudience(api, organizerId);
    const tag = uniqueShort('Members');
    const segmentName = uniqueShort('Members only');
    const campaignName = uniqueShort('Members news');
    const member = uniqueEmail('member');
    await withContactsApi(playwright, freshAccount, async (contactsApi) => {
      await contactsApi.importContacts(`Email,First Name,Last Name\n${member},Mel,Member\n`, { tag });
      const tagId = await contactsApi.findTagId(tag);
      const segment = await contactsApi.createSegment(segmentName, { match: 'all', rules: [{ field: 'tag', operator: 'in', value: [tagId] }] });
      await contactsApi.createDraftCampaign(campaignName, segment.id);
    });

    const page = await freshAccount.newAuthedPage();
    const audience = new AudiencePage(page, organizerId);
    await audience.gotoContacts();
    const manage = await openManageTags(page, audience);
    await tagRow(manage, tag).getByRole('button', { name: 'Delete tag' }).click();
    await audience.confirm('Delete tag');
    await expect(page.getByText(`This tag is still used in segment or campaign rules: "${segmentName}". Remove it from those rules first.`)).toBeVisible();
    await expect(tagRow(manage, tag)).toContainText('1 contact');
    await closeDialog(manage);
    await expect(audience.contactRow(member)).toContainText(tag);

    await audience.gotoSegments();
    const row = segmentRow(page, segmentName);
    await expect(row).toContainText('1 person');
    await row.getByRole('button', { name: 'Segment actions' }).click();
    await page.getByRole('menuitem', { name: 'Delete' }).click();
    const confirm = page.getByRole('dialog', { name: `Delete the segment "${segmentName}"?` });
    await expect(confirm).toContainText(`It's still in use: ${campaignName} (draft campaign).`);
    await confirm.getByRole('button', { name: 'Cancel', exact: true }).click();
    await expect(page.getByRole('link', { name: segmentName, exact: true })).toBeVisible();
  });

  test('a tag is created with a colour, removed from selected contacts, renamed for everyone and then deleted', async ({ freshAccount, publicApi }) => {
    const family = uniqueShort('Tagged');
    await arrangeRegularAndNewcomer(freshAccount, publicApi, family);
    const tag = uniqueShort('Crew');
    const renamed = uniqueShort('Staff');

    const page = await freshAccount.newAuthedPage();
    const audience = new AudiencePage(page, freshAccount.organizerId);
    await audience.gotoContactsMatching(family);

    const manage = await openManageTags(page, audience);
    await expect(manage).toContainText('No tags yet. Add your first one above.');
    await manage.getByRole('button', { name: 'Add tag' }).click();
    await expect(manage).toContainText('Give this tag a name');
    await manage.getByRole('button', { name: 'Tag colour: No colour' }).click();
    await page.getByRole('group', { name: 'Tag colour' }).getByRole('button', { name: 'Blue' }).click();
    await manage.getByRole('textbox', { name: 'New tag name' }).fill(tag);
    await manage.getByRole('button', { name: 'Add tag' }).click();
    await expect(tagRow(manage, tag)).toContainText('0 contacts');
    await expect(tagRow(manage, tag).getByRole('button', { name: 'Tag colour: Blue' })).toBeVisible();
    await expect(manage.getByRole('textbox', { name: 'New tag name' })).toHaveValue('');
    await closeDialog(manage);

    await audience.selectPageCheckbox().check();
    await audience.bulkTag('Add tag', tag);
    await expect(audience.contactRow(`Noa ${family}`)).toContainText(tag);
    await expect(audience.contactRow(`Reg ${family}`)).toContainText(tag);

    await audience.rowCheckbox(`Noa ${family}`).check();
    await audience.bulkTag('Remove tag', tag);
    await expect(audience.contactRow(`Noa ${family}`)).not.toContainText(tag);
    await expect(audience.contactRow(`Reg ${family}`)).toContainText(tag);

    await openManageTags(page, audience);
    await expect(tagRow(manage, tag)).toContainText('1 contact');
    await expect(manage.getByRole('button', { name: 'Save', exact: true })).toHaveCount(0);
    await tagRow(manage, tag).getByRole('textbox', { name: 'Tag name', exact: true }).fill(renamed);
    await manage.getByRole('button', { name: 'Save', exact: true }).click();
    await expect(manage.getByRole('button', { name: 'Save', exact: true })).toHaveCount(0);
    await closeDialog(manage);
    await expect(audience.contactRow(`Reg ${family}`)).toContainText(renamed);

    await openManageTags(page, audience);
    await manage.getByRole('button', { name: 'Delete tag' }).click();
    await expect(page.getByRole('dialog', { name: `Delete the tag "${renamed}"?` })).toContainText('It will be removed from every contact that has it.');
    await audience.confirm('Delete tag');
    await expect(manage).toContainText('No tags yet.');
    await closeDialog(manage);
    await expect(audience.contactRow(`Reg ${family}`)).not.toContainText(renamed);
  });
});
