import {useState} from "react";
import {Button, Loader, TextInput} from "@mantine/core";
import {useDebouncedValue} from "@mantine/hooks";
import {t, Trans} from "@lingui/macro";
import {IconSearch, IconX} from "@tabler/icons-react";
import {BoxOfficePublic, BoxOfficeSession, BoxOfficeTenderType, Order} from "../../../../../../types.ts";
import {useGetBoxOfficeOrdersPublic} from "../../../../queries/useGetBoxOfficeOrdersPublic.ts";
import {useAbandonBoxOfficeOrder} from "../../../../mutations/useAbandonBoxOfficeOrder.ts";
import {confirmationDialog} from "../../../../../../utilites/confirmationDialog.tsx";
import {showError} from "../../../../../../utilites/notifications.tsx";
import {firstApiError} from "../../../../../../utilites/apiErrors.ts";
import {formatCurrency} from "../../../../../../utilites/currency.ts";
import {formatDateWithLocale} from "../../../../../../utilites/dates.ts";
import {getBoxOfficeTenderLabel} from "../../../../utilites/boxOfficeTender.ts";
import {Sheet} from "../Sheet.tsx";
import {OrderActions} from "../SaleComplete.tsx";
import classes from "./OrdersTab.module.scss";

interface OrdersTabProps {
    boxOffice: BoxOfficePublic;
    boxOfficeShortId: string;
    session: BoxOfficeSession;
}

const tenderLabel = (tender?: BoxOfficeTenderType | null): string =>
    getBoxOfficeTenderLabel(tender) ?? t`Unpaid`;

const buyerLabel = (order: Order): string =>
    [order.first_name, order.last_name].filter(Boolean).join(' ') || t`Box office sale`;

export const OrdersTab = ({boxOffice, boxOfficeShortId, session}: OrdersTabProps) => {
    const [search, setSearch] = useState('');
    const [debouncedSearch] = useDebouncedValue(search, 250);
    const [selected, setSelected] = useState<Order | null>(null);
    const ordersQuery = useGetBoxOfficeOrdersPublic(boxOfficeShortId, {pageNumber: 1, perPage: 50, query: debouncedSearch});
    const orders = ordersQuery.data?.data;
    const timezone = boxOffice.event.timezone;
    const abandonOrder = useAbandonBoxOfficeOrder();

    const abandonSelected = (order: Order) => {
        confirmationDialog(t`Abandon this sale? A card payment waiting on the reader is cancelled, and its tickets and seats are released.`, () => {
            abandonOrder.mutate({boxOfficeShortId, orderShortId: order.short_id}, {
                onSuccess: ({data}) => setSelected(data),
                onError: (error) => showError(firstApiError(error, t`Unable to abandon this sale`)),
            });
        });
    };

    return (
        <div className={classes.wrap}>
            <div className={classes.searchBar}>
                <TextInput
                    value={search}
                    onChange={(e) => setSearch(e.currentTarget.value)}
                    placeholder={t`Search by name, email or order number`}
                    leftSection={<IconSearch size={16}/>}
                    size="md"
                    aria-label={t`Search orders`}
                    data-testid="box-office-orders-search"
                />
            </div>
            <div className={classes.list}>
                {ordersQuery.isLoading && <Loader size="sm" style={{margin: '40px auto'}}/>}
                {orders && orders.length === 0 && (
                    <div className={classes.empty}>{t`No sales yet from this box office`}</div>
                )}
                {orders?.map(order => (
                    <button key={order.short_id} type="button" className={classes.row} onClick={() => setSelected(order)}
                            data-testid={`box-office-order-${order.public_id}`}>
                        <div className={classes.rowMain}>
                            <div className={classes.rowTitle}>{buyerLabel(order)}</div>
                            <div className={classes.rowMeta}>
                                <span>{order.public_id}</span>
                                <span>·</span>
                                <span>{formatDateWithLocale(order.created_at as string, 'timeOnly', timezone)}</span>
                                {order.box_office_operator_name && (
                                    <>
                                        <span>·</span>
                                        <span>{order.box_office_operator_name}</span>
                                    </>
                                )}
                                <span className={classes.badge}
                                      data-tone={order.status === 'CANCELLED' ? 'cancelled' : order.status === 'RESERVED' ? 'pending' : undefined}>
                                    {order.status === 'CANCELLED' ? t`Voided` : order.status === 'RESERVED' ? t`In progress` : tenderLabel(order.box_office_tender)}
                                </span>
                            </div>
                        </div>
                        <div className={classes.rowTotal}>
                            {formatCurrency(order.total_gross, order.currency)}
                            <div className={classes.rowMeta} style={{justifyContent: 'flex-end'}}>
                                {(order.attendees?.length ?? 0) === 1
                                    ? t`1 ticket`
                                    : <Trans>{order.attendees?.length ?? 0} tickets</Trans>}
                            </div>
                        </div>
                    </button>
                ))}
            </div>

            {selected && (
                <Sheet opened onClose={() => setSelected(null)} title={selected.public_id}>
                    <div className={classes.detail}>
                        <div className={classes.detailLine}>
                            <span>{buyerLabel(selected)}</span>
                            <span>{selected.email ?? t`No email`}</span>
                        </div>
                        {selected.order_items?.map(item => (
                            <div key={item.id} className={classes.detailLine}>
                                <span>{item.quantity}× {item.item_name}</span>
                                <span>{formatCurrency(item.total_gross ?? item.price * item.quantity, selected.currency)}</span>
                            </div>
                        ))}
                        <div className={classes.detailLine}>
                            <b>{t`Total`}</b>
                            <b>{formatCurrency(selected.total_gross, selected.currency)} · {tenderLabel(selected.box_office_tender)}</b>
                        </div>
                        {selected.status === 'COMPLETED' || selected.status === 'CANCELLED' ? (
                            <OrderActions
                                order={selected}
                                boxOfficeShortId={boxOfficeShortId}
                                eventId={boxOffice.event.id}
                                session={session}
                                onOrderChanged={setSelected}
                            />
                        ) : selected.status === 'RESERVED' ? (
                            <>
                                <div className={classes.empty}>{t`This sale is still in progress`}</div>
                                <Button variant="light" color="red" leftSection={<IconX size={16}/>} loading={abandonOrder.isPending}
                                        onClick={() => abandonSelected(selected)} data-testid="box-office-abandon-sale-button">
                                    {t`Abandon sale`}
                                </Button>
                            </>
                        ) : (
                            <div className={classes.empty}>{t`This sale was not completed`}</div>
                        )}
                    </div>
                </Sheet>
            )}
        </div>
    );
};
