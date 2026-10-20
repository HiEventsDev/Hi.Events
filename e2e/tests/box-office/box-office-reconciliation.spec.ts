import { test, expect } from '../../fixtures';
import { BoxOfficeManagePage } from '../../pages/box-office-manage.page';
import { createDoorSale, createLiveEventWithPaidTicket, defaultBoxOffice } from '../../api/factory';

test.describe('box office reconciliation', () => {
  test('the sales summary totals door sales by payment type and operator', { tag: '@smoke' }, async ({ authedPage, api, publicApi, account }) => {
    const event = await createLiveEventWithPaidTicket(api, account.organizerId, 25);
    const boxOffice = await defaultBoxOffice(api, event.eventId);
    await createDoorSale(publicApi, boxOffice, event, { operatorName: 'Robin', tender: 'CASH', quantity: 2 });
    await createDoorSale(publicApi, boxOffice, event, { operatorName: 'Robin', tender: 'OTHER' });
    await createDoorSale(publicApi, boxOffice, event, { operatorName: 'Alex', tender: 'COMP' });

    const boxOffices = new BoxOfficeManagePage(authedPage);
    await boxOffices.goto(event.eventId);
    await boxOffices.openSalesSummary(boxOffice.name);

    await expect(boxOffices.statsOrders()).toHaveText('3');
    await expect(boxOffices.statsGross()).toHaveText('$75.00');
    await expect(boxOffices.statsRefunded()).toHaveText('$0.00');

    await expect(boxOffices.statsRow('Cash')).toContainText('$50.00');
    await expect(boxOffices.statsRow('Paid elsewhere')).toContainText('$25.00');
    await expect(boxOffices.statsRow('Comp')).toContainText('$0.00');
    await expect(boxOffices.statsRow('Robin')).toContainText('$75.00');
    await expect(boxOffices.statsRow('Alex')).toContainText('$0.00');
  });
});
