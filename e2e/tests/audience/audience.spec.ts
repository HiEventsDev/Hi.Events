import { test, expect } from '../../fixtures';
import { createCompletedOrder, createLiveEventWithFreeTicket } from '../../api/factory';
import { AudiencePage } from '../../pages/audience.page';
import { uniqueEmail } from '../../utils/unique';
import { buildAudience, createOptedInOrder, waitForContact } from './audience.shared';
import { cellUnder } from './contacts.helpers';

test.describe('audience', () => {
  test('building the audience turns past orders into one contact per person', { tag: '@smoke' }, async ({ freshAccount, publicApi }) => {
    const { api, organizerId } = freshAccount;
    const first = await createLiveEventWithFreeTicket(api, organizerId);
    const second = await createLiveEventWithFreeTicket(api, organizerId);
    const regular = uniqueEmail('regular');
    await createCompletedOrder(publicApi, first, { buyerEmail: regular, buyerFirstName: 'Riley', buyerLastName: 'Regular' });
    await createCompletedOrder(publicApi, second, { buyerEmail: regular.toUpperCase(), buyerFirstName: 'Riley', buyerLastName: 'Regular' });
    await createCompletedOrder(publicApi, first, { buyerEmail: uniqueEmail('once'), buyerFirstName: 'Oscar', buyerLastName: 'Once' });

    const page = await freshAccount.newAuthedPage();
    const audience = new AudiencePage(page, organizerId);
    await audience.gotoOverview();
    await audience.startButton().click();

    const tiles = page.getByRole('main');
    await expect(tiles.getByText('1 of 2 booked 2+ events')).toBeVisible({ timeout: 30_000 });
    await expect(tiles.getByText('3 tickets in total')).toBeVisible();
    await expect(tiles.getByText('2 new in the last 30 days')).toBeVisible();
    await expect(page.getByRole('heading', { name: 'New and returning buyers' })).toBeVisible();

    await audience.tab('Contacts').click();
    await expect(audience.contactTotal()).toHaveText('2 contacts');

    await audience.search(regular);
    await expect(audience.contactRows()).toHaveCount(1);
    await expect(await cellUnder(page, audience.contactRow('Riley Regular'), 'Events')).toHaveText('2');
    await expect(await cellUnder(page, audience.contactRow('Riley Regular'), 'Orders')).toHaveText('2');

    await audience.contactRow('Riley Regular').click();
    await expect(audience.drawer()).toContainText(regular);
    await expect(audience.drawer().getByText('Placed an order')).toHaveCount(2);
  });

  test('a new order reaches the contact without another sync', async ({ freshAccount, publicApi }) => {
    const { api, organizerId } = freshAccount;
    const event = await createLiveEventWithFreeTicket(api, organizerId);
    await buildAudience(api, organizerId);

    const buyer = uniqueEmail('latecomer');
    await createCompletedOrder(publicApi, event, { buyerEmail: buyer, buyerFirstName: 'Lena', buyerLastName: 'Late' });
    await waitForContact(api, organizerId, buyer);

    const page = await freshAccount.newAuthedPage();
    const audience = new AudiencePage(page, organizerId);
    await audience.gotoContactsMatching(buyer);
    await expect(await cellUnder(page, audience.contactRow('Lena Late'), 'Events')).toHaveText('1');
    await audience.contactRow('Lena Late').click();
    await expect(audience.drawer().getByText('Placed an order')).toHaveCount(1);
  });

  test('two contacts for one person can be merged and separated again', async ({ freshAccount, publicApi }) => {
    const { api, organizerId } = freshAccount;
    const event = await createLiveEventWithFreeTicket(api, organizerId);
    const work = uniqueEmail('work');
    const home = uniqueEmail('home');
    await createCompletedOrder(publicApi, event, { buyerEmail: work, buyerFirstName: 'Morgan', buyerLastName: 'Merge' });
    await createCompletedOrder(publicApi, event, { buyerEmail: home, buyerFirstName: 'Morgan', buyerLastName: 'Merge' });
    await buildAudience(api, organizerId);

    const page = await freshAccount.newAuthedPage();
    const audience = new AudiencePage(page, organizerId);
    await audience.gotoContactsMatching(work);
    await audience.contactRow('Morgan Merge').click();
    await audience.openDrawerAction('Merge with another contact');
    await page.getByTestId('audience-merge-search').fill(home);
    await page.getByRole('dialog').getByRole('button', { name: home }).click();
    await page.getByTestId('audience-merge-confirm').click();

    await expect(page.getByRole('dialog', { name: 'Merge with another contact' })).toBeHidden();
    const addresses = audience.drawerSection('Email addresses');
    await expect(addresses).toContainText(home);
    await expect(audience.primaryMark(work)).toBeVisible();
    await expect(audience.primaryMark(home)).toHaveCount(0);
    await expect(audience.drawer().getByText('Placed an order')).toHaveCount(2);
    await page.keyboard.press('Escape');
    await audience.search('Morgan Merge');
    await expect(audience.contactRows()).toHaveCount(1);
    await expect(await cellUnder(page, audience.contactRow(work), 'Orders')).toHaveText('2');

    await audience.contactRow(work).click();
    await addresses.getByRole('button', { name: 'Separate', exact: true }).click();
    await expect(page.getByRole('dialog', { name: `Separate ${home} into its own contact?` })).toContainText('Orders placed with this address move to the new contact.');
    await audience.confirm('Separate email');
    await expect(addresses).toHaveCount(0);
    await expect(audience.drawer().getByText('Placed an order')).toHaveCount(1);
    await page.keyboard.press('Escape');
    await expect(audience.contactRows()).toHaveCount(2);
    await expect(audience.contactRow(home)).toBeVisible();
    await expect.poll(async () => (await api.findAudienceContacts(organizerId, 'Morgan Merge')).length).toBe(2);
  });

  test('merging can keep the other contact, and warns when that costs the subscription', async ({ freshAccount, publicApi }) => {
    const { api, organizerId } = freshAccount;
    const event = await createLiveEventWithFreeTicket(api, organizerId);
    const subscribed = uniqueEmail('subscribed');
    const silent = uniqueEmail('silent');
    await createOptedInOrder(publicApi, event, { email: subscribed, firstName: 'Kit', lastName: 'Keeper' });
    await createCompletedOrder(publicApi, event, { buyerEmail: silent, buyerFirstName: 'Kit', buyerLastName: 'Keeper' });
    await buildAudience(api, organizerId);

    const page = await freshAccount.newAuthedPage();
    const audience = new AudiencePage(page, organizerId);
    await audience.gotoContactsMatching(subscribed);
    await audience.contactRow(subscribed).click();
    await audience.openDrawerAction('Merge with another contact');
    const merge = page.getByRole('dialog', { name: 'Merge with another contact' });
    await expect(page.getByTestId('audience-merge-confirm')).toBeDisabled();
    await page.getByTestId('audience-merge-search').fill(silent);
    await merge.getByRole('button', { name: silent }).click();
    await expect(merge).not.toContainText('decides marketing consent, and it');

    await merge.getByRole('radio', { name: silent }).check();
    await expect(merge).toContainText(`Only ${silent} decides marketing consent, and it isn't subscribed, so the merged contact won't receive campaigns.`);
    await merge.getByRole('radio', { name: subscribed }).check();
    await expect(merge).not.toContainText('decides marketing consent, and it');
    await page.getByTestId('audience-merge-confirm').click();

    await expect(merge).toBeHidden();
    await expect(audience.marketingStatus()).toContainText('Subscribed');
    await expect(audience.drawerSection('Email addresses')).toContainText(silent);
    await page.keyboard.press('Escape');
    await audience.search('Kit Keeper');
    await expect(audience.contactRows()).toHaveCount(1);
    await expect(audience.contactRow(subscribed)).toContainText('Subscribed');
  });

  test('a deleted contact stays deleted when they order again, and their order is untouched', async ({ freshAccount, publicApi }) => {
    const { api, organizerId } = freshAccount;
    const event = await createLiveEventWithFreeTicket(api, organizerId);
    const buyer = uniqueEmail('forget-me');
    const order = await createCompletedOrder(publicApi, event, { buyerEmail: buyer, buyerFirstName: 'Fran', buyerLastName: 'Forget' });
    await buildAudience(api, organizerId);

    const page = await freshAccount.newAuthedPage();
    const audience = new AudiencePage(page, organizerId);
    await audience.gotoContactsMatching(buyer);
    await audience.contactRow('Fran Forget').click();
    await audience.openDrawerAction('Delete contact');
    await expect(page.getByRole('dialog', { name: 'Delete this contact?' })).toContainText("Their orders and tickets aren't affected, and they won't be added back from future orders.");
    await audience.confirm('Delete contact');
    await expect(audience.contactRows()).toHaveCount(0);
    await expect(page.getByText('Nobody matches this search or filter.')).toBeVisible();

    await createCompletedOrder(publicApi, event, { buyerEmail: buyer, buyerFirstName: 'Fran', buyerLastName: 'Forget' });
    const other = uniqueEmail('after');
    await createCompletedOrder(publicApi, event, { buyerEmail: other, buyerFirstName: 'Ava', buyerLastName: 'After' });
    await waitForContact(api, organizerId, other);

    await page.reload();
    await expect(page.getByText('Nobody matches this search or filter.')).toBeVisible();
    await audience.search('');
    await expect(audience.contactTotal()).toHaveText('1 contact');
    await expect(audience.contactRow('Ava After')).toBeVisible();
    expect(await api.findAudienceContacts(organizerId, buyer)).toHaveLength(0);
    expect(await api.findOrderIdByShortId(event.eventId, order.orderShortId)).toBeGreaterThan(0);
  });

  test('an empty audience says what to do next, and the overview warns about live events that do not ask buyers to opt in', async ({ freshAccount, publicApi }) => {
    const { api, organizerId } = freshAccount;
    await buildAudience(api, organizerId);

    const page = await freshAccount.newAuthedPage();
    const audience = new AudiencePage(page, organizerId);
    await audience.gotoOverview();
    await expect(page.getByText('Nobody in your audience yet')).toBeVisible();
    await expect(page.getByText('Came back')).toHaveCount(0);
    await page.getByRole('link', { name: 'Add or import contacts' }).click();
    await expect(page).toHaveURL(/\/audience\/contacts$/);
    await expect(page.getByText('No contacts yet. They appear here as soon as someone books, or you can add one yourself.')).toBeVisible();

    const asking = await createLiveEventWithFreeTicket(api, organizerId);
    const silent = await createLiveEventWithFreeTicket(api, organizerId);
    await api.updateEventSettings(silent.eventId, { show_marketing_opt_in: false });
    const buyer = uniqueEmail('overview');
    await createOptedInOrder(publicApi, asking, { email: buyer, firstName: 'Olive', lastName: 'Overview' });
    await waitForContact(api, organizerId, buyer);

    await audience.gotoOverview();
    await expect(page.getByText("Live events that aren't asking buyers to opt in: 1 of 2")).toBeVisible();
    await expect(page.getByText('Nobody in your audience yet')).toHaveCount(0);
    await expect(page.getByText('100% of your contacts')).toBeVisible();
    await expect(page.getByText('0 of 1 booked 2+ events')).toBeVisible();
    await expect(page.getByRole('link', { name: asking.title })).toHaveCount(0);
    await page.getByRole('link', { name: silent.title }).click();
    await expect(page).toHaveURL(new RegExp(`/manage/event/${silent.eventId}/settings`));
  });
});
