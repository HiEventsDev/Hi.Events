import {useState} from "react";
import {Button, TextInput} from "@mantine/core";
import {t, Trans} from "@lingui/macro";
import {IconCheck, IconPrinter, IconSend, IconUserCheck} from "@tabler/icons-react";
import {AxiosError} from "axios";
import {BoxOfficeSession, BoxOfficeTenderType, Order} from "../../../../../types.ts";
import {formatCurrency} from "../../../../../utilites/currency.ts";
import {getBoxOfficeTenderLabel} from "../../../utilites/boxOfficeTender.ts";
import {useCheckInBoxOfficeOrder} from "../../../mutations/useCheckInBoxOfficeOrder.ts";
import {useResendBoxOfficeOrder} from "../../../mutations/useResendBoxOfficeOrder.ts";
import {useCancelBoxOfficeOrder} from "../../../mutations/useCancelBoxOfficeOrder.ts";
import {confirmationDialog} from "../../../../../utilites/confirmationDialog.tsx";
import {showError} from "../../../../../utilites/notifications.tsx";
import classes from "./tabs/SellTab.module.scss";

interface OrderActionsProps {
    order: Order;
    boxOfficeShortId: string;
    eventId: number;
    session: BoxOfficeSession;
    onOrderChanged?: (order: Order) => void;
}


export const OrderActions = ({order, boxOfficeShortId, eventId, session, onOrderChanged}: OrderActionsProps) => {
    const checkIn = useCheckInBoxOfficeOrder();
    const resend = useResendBoxOfficeOrder();
    const cancel = useCancelBoxOfficeOrder();
    const [checkInResult, setCheckInResult] = useState<{ ok: number; total: number; error?: string } | null>(null);
    const [email, setEmail] = useState('');
    const [sentTo, setSentTo] = useState<string | null>(null);
    const attendees = order.attendees ?? [];
    const alreadyCheckedIn = attendees.filter(a => a.is_checked_in).length;
    const isCancelled = order.status === 'CANCELLED';
    const isCard = order.box_office_tender === BoxOfficeTenderType.CARD;
    const checkedInCount = checkInResult?.ok ?? alreadyCheckedIn;
    const attendeeCount = attendees.length;
    const seatList = attendees.map(a => a.seat_label).filter(Boolean).join(', ');
    const allCheckedIn = attendeeCount > 0 && checkedInCount === attendeeCount;
    const checkedInLabel = allCheckedIn
        ? t`Checked in (${checkedInCount}/${attendeeCount})`
        : null;

    const handleCheckIn = () => {
        if (!session.check_in_list_short_id) return;
        checkIn.mutate({
            checkInListShortId: session.check_in_list_short_id,
            attendeePublicIds: attendees.filter(a => !a.is_checked_in).map(a => a.public_id),
            boxOfficeShortId,
        }, {
            onSuccess: (response) => {
                const errors = response.errors ? Object.values(response.errors) : [];
                setCheckInResult({
                    ok: (response.data?.length ?? 0) + alreadyCheckedIn,
                    total: attendees.length,
                    error: errors.length ? String(errors[0]) : undefined,
                });
            },
            onError: (error) => setCheckInResult({
                ok: alreadyCheckedIn,
                total: attendees.length,
                error: error instanceof AxiosError ? error.response?.data?.message : t`Unable to check in`,
            }),
        });
    };

    const handleSend = (address?: string) => {
        resend.mutate({boxOfficeShortId, orderShortId: order.short_id, email: address}, {
            onSuccess: ({data}) => {
                setSentTo(data.email ?? address ?? null);
                onOrderChanged?.(data);
            },
            onError: (error) => showError(error instanceof AxiosError ? error.response?.data?.message : t`Unable to send tickets`),
        });
    };

    const handleVoid = () => {
        confirmationDialog(t`Void this sale? The tickets will be cancelled.`, () => {
            cancel.mutate({boxOfficeShortId, orderShortId: order.short_id}, {
                onSuccess: ({data}) => onOrderChanged?.(data),
                onError: (error) => showError(error instanceof AxiosError ? error.response?.data?.message : t`Unable to void sale`),
            });
        });
    };

    return (
        <>
            {seatList && !isCancelled && (
                <div className={classes.inlineStatus}>
                    <Trans>Seats: {seatList}</Trans>
                </div>
            )}
            {attendees.length > 0 && (
                <div className={classes.actionRow}>
                    <Button
                        variant="light"
                        leftSection={<IconUserCheck size={16}/>}
                        loading={checkIn.isPending}
                        disabled={!session.check_in_available || isCancelled || allCheckedIn}
                        onClick={handleCheckIn}
                        data-testid="box-office-check-in-now-button"
                    >
                        {checkedInLabel ?? t`Check in now`}
                    </Button>
                    <Button
                        variant="light"
                        leftSection={<IconPrinter size={16}/>}
                        disabled={isCancelled}
                        onClick={() => window.open(`/order/${eventId}/${order.short_id}/print`, '_blank')}
                    >
                        {t`Tickets`}
                    </Button>
                </div>
            )}
            {!session.check_in_available && session.check_in_unavailable_reason && (
                <div className={`${classes.inlineStatus} ${classes.inlineErr}`}>{session.check_in_unavailable_reason}</div>
            )}
            {checkInResult?.error && (
                <div className={`${classes.inlineStatus} ${classes.inlineErr}`}>{checkInResult.error}</div>
            )}
            {attendees.length === 0 && (
                <div className={classes.inlineStatus}>{t`No tickets in this order`}</div>
            )}

            {!isCancelled && (order.email || sentTo) ? (
                <div className={classes.inlineStatus}>
                    <span className={classes.inlineOk}><Trans>Tickets sent to {sentTo ?? order.email}</Trans></span>
                    {' · '}
                    <button type="button" className={classes.rowLink} onClick={() => handleSend()} disabled={resend.isPending}>
                        {t`Resend`}
                    </button>
                </div>
            ) : !isCancelled && (
                <div className={classes.emailRow}>
                    <TextInput
                        style={{flex: 1}}
                        placeholder={t`Email to send tickets`}
                        type="email"
                        inputMode="email"
                        value={email}
                        onChange={(e) => setEmail(e.currentTarget.value)}
                        data-testid="box-office-send-tickets-email"
                    />
                    <Button
                        variant="light"
                        leftSection={<IconSend size={16}/>}
                        loading={resend.isPending}
                        disabled={!email}
                        onClick={() => handleSend(email)}
                        data-testid="box-office-send-tickets-button"
                    >
                        {t`Send`}
                    </Button>
                </div>
            )}

            {isCancelled ? (
                <div className={`${classes.inlineStatus} ${classes.inlineErr}`}>{t`This sale was voided`}</div>
            ) : (
                <button
                    type="button"
                    className={classes.voidLink}
                    onClick={handleVoid}
                    disabled={cancel.isPending || isCard}
                    data-testid="box-office-void-button"
                >
                    {isCard ? t`Card sales are refunded by an organizer from Orders` : t`Void this sale`}
                </button>
            )}
        </>
    );
};

interface SaleCompleteProps extends OrderActionsProps {
    onNewSale: () => void;
}

export const SaleComplete = ({order, onNewSale, onOrderChanged, ...rest}: SaleCompleteProps) => {
    const [currentOrder, setCurrentOrder] = useState(order);
    const changeDue = Number(currentOrder.box_office_change_due ?? 0);

    return (
        <div className={classes.stageCard}>
            <div className={classes.hero}>
                <div className={classes.heroIcon}><IconCheck size={34}/></div>
                <h2 className={classes.heroTitle}>{t`Sale complete`}</h2>
                <p className={classes.heroSub}>
                    {currentOrder.public_id} · {formatCurrency(currentOrder.total_gross, currentOrder.currency)} · {getBoxOfficeTenderLabel(currentOrder.box_office_tender)}
                    {changeDue > 0 && <> · <Trans>change {formatCurrency(changeDue, currentOrder.currency)}</Trans></>}
                </p>
            </div>
            <Button size="lg" fullWidth onClick={onNewSale} data-testid="box-office-new-sale-button">
                {t`New sale`}
            </Button>
            <OrderActions
                order={currentOrder}
                onOrderChanged={(updated) => {
                    setCurrentOrder(updated);
                    onOrderChanged?.(updated);
                }}
                {...rest}
            />
        </div>
    );
};
