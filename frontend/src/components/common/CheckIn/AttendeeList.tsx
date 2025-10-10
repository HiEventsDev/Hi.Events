import {Button, Loader} from "@mantine/core";
import {IconTicket} from "@tabler/icons-react";
import {t} from "@lingui/macro";
import {Attendee} from "../../../types.ts";
import classes from "../../layouts/CheckIn/CheckIn.module.scss";

interface AttendeeListProps {
    attendees: Attendee[] | undefined;
    products: { id: number; title: string; }[] | undefined;
    isLoading: boolean;
    isCheckInPending: boolean;
    isDeletePending: boolean;
    allowOrdersAwaitingOfflinePaymentToCheckIn: boolean;
    onCheckInToggle: (attendee: Attendee) => void;
    onClickSound?: () => void;
}

export const AttendeeList = ({
                                 attendees,
                                 products,
                                 isLoading,
                                 isCheckInPending,
                                 isDeletePending,
                                 allowOrdersAwaitingOfflinePaymentToCheckIn,
                                 onCheckInToggle,
                                 onClickSound
                             }: AttendeeListProps) => {
    const checkInButtonText = (attendee: Attendee) => {
        if (!allowOrdersAwaitingOfflinePaymentToCheckIn && attendee.status === 'AWAITING_PAYMENT') {
            return t`Cannot Check In`;
        }

        if (attendee.check_in) {
            return t`Check Out`;
        }

        return t`Check In`;
    };

    const getButtonColor = (attendee: Attendee) => {
        if (attendee.check_in) {
            return 'red';
        }
        if (attendee.status === 'AWAITING_PAYMENT' && !allowOrdersAwaitingOfflinePaymentToCheckIn) {
            return 'gray';
        }
        return 'teal';
    };

    if (isLoading || !attendees || !products) {
        return (
            <div className={classes.loading}>
                <Loader size={40}/>
            </div>
        );
    }

    if (attendees.length === 0) {
        return (
            <div className={classes.noResults}>
                No attendees to show.
            </div>
        );
    }

    return (
        <div className={classes.attendees}>
            {attendees.map(attendee => {
                const isAttendeeAwaitingPayment = attendee.status === 'AWAITING_PAYMENT';

                return (
                    <div className={classes.attendee} key={attendee.public_id}>
                        <div className={classes.details}>
                            <div>
                                <b>{attendee.first_name} {attendee.last_name}</b>
                            </div>
                            <div style={{fontSize: '0.8em', color: '#555'}}>
                                {attendee.email}
                            </div>
                            {isAttendeeAwaitingPayment && (
                                <div className={classes.awaitingPayment}>
                                    {t`Awaiting payment`}
                                </div>
                            )}
                            <div>
                                <span>{attendee.public_id}</span>
                            </div>
                            <div className={classes.product}>
                                <IconTicket
                                    size={15}/> {products.find(product => product.id === attendee.product_id)?.title}
                            </div>
                        </div>
                        <div className={classes.actions}>
                            <Button
                                onClick={() => {
                                    onClickSound?.();
                                    onCheckInToggle(attendee);
                                }}
                                disabled={isCheckInPending || isDeletePending}
                                loading={isCheckInPending || isDeletePending}
                                color={getButtonColor(attendee)}
                            >
                                {checkInButtonText(attendee)}
                            </Button>
                        </div>
                    </div>
                );
            })}
        </div>
    );
};
