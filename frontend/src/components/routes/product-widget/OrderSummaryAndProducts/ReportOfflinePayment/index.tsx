import {useState} from "react";
import {t} from "@lingui/macro";
import {Button, TextInput} from "@mantine/core";
import {IconCircleCheck, IconSend} from "@tabler/icons-react";
import {Event, Order} from "../../../../../types.ts";
import {Card} from "../../../../common/Card";
import {dateToBrowserTz} from "../../../../../utilites/dates.ts";
import {useReportOfflinePaymentPublic} from "../../../../../mutations/useReportOfflinePaymentPublic.ts";
import {showError} from "../../../../../utilites/notifications.tsx";
import classes from "./ReportOfflinePayment.module.scss";

export const ReportOfflinePayment = ({order, event}: { order: Order; event: Event }) => {
    const [note, setNote] = useState('');
    const [noteError, setNoteError] = useState<string | null>(null);
    const mutation = useReportOfflinePaymentPublic();

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setNoteError(null);
        mutation.mutate({
            eventId: event.id,
            orderShortId: order.short_id,
            note: note.trim() || null,
        }, {
            onError: (error: any) => {
                const fieldError = error?.response?.data?.errors?.note?.[0];
                if (fieldError) {
                    setNoteError(fieldError);
                    return;
                }
                showError(error?.response?.data?.message || t`We couldn't send your message. Please try again.`);
            },
        });
    };

    return (
        <div className={classes.wrapper}>
            <h2>{t`Already paid?`}</h2>
            <Card>
                {order.offline_payment_reported_at ? (
                    <div className={classes.reported}>
                        <IconCircleCheck size={22}/>
                        <div>
                            <div className={classes.reportedTitle}>{t`The organizer knows you've paid`}</div>
                            <div className={classes.reportedText} suppressHydrationWarning>
                                {t`You told them on ${dateToBrowserTz(order.offline_payment_reported_at, event.timezone)}. They'll confirm the order once the payment arrives, and a confirmation email will be sent to ${order.email}.`}
                            </div>
                        </div>
                    </div>
                ) : (
                    <form onSubmit={handleSubmit}>
                        <div className={classes.intro}>
                            {t`Once you've sent the payment, let the organizer know so they can look out for it. Your order stays unpaid until they confirm it.`}
                        </div>
                        <div className={classes.form}>
                            <TextInput
                                label={t`Note for the organizer (optional)`}
                                placeholder={t`e.g. Paid by Acme Ltd, PO 4471`}
                                maxLength={100}
                                value={note}
                                error={noteError}
                                onChange={(e) => setNote(e.currentTarget.value)}
                            />
                            <Button
                                type="submit"
                                loading={mutation.isPending}
                                leftSection={<IconSend size={16}/>}
                                data-testid="report-offline-payment-button"
                            >
                                {t`I've made the payment`}
                            </Button>
                        </div>
                    </form>
                )}
            </Card>
        </div>
    );
};
