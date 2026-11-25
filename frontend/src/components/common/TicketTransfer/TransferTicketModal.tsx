import {t} from "@lingui/macro";
import {Button, List, Text, TextInput} from "@mantine/core";
import {useForm, UseFormReturnType} from "@mantine/form";
import {Modal, ModalFooter} from "../Modal";
import {Attendee} from "../../../types.ts";
import classes from "./TicketTransfer.module.scss";

export interface TransferTicketFormValues {
    recipient_email: string;
}

interface TransferTicketModalProps {
    attendee: Attendee;
    ticketTitle?: string;
    isSubmitting: boolean;
    onClose: () => void;
    onSubmit: (values: TransferTicketFormValues, form: UseFormReturnType<TransferTicketFormValues>) => void;
}

export const TransferTicketModal = ({attendee, ticketTitle, isSubmitting, onClose, onSubmit}: TransferTicketModalProps) => {
    const form = useForm<TransferTicketFormValues>({
        initialValues: {recipient_email: ''},
        validate: {
            recipient_email: (value) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value) ? null : t`Enter a valid email address`,
        },
    });

    return (
        <Modal opened onClose={onClose} heading={t`Transfer this ticket`} size="md">
            <form onSubmit={form.onSubmit((values) => onSubmit(values, form))}>
                <div className={classes.ticketSummary}>
                    <Text fw={600}>{attendee.first_name} {attendee.last_name}</Text>
                    {ticketTitle && <Text size="sm" c="dimmed">{ticketTitle}</Text>}
                </div>

                <TextInput
                    {...form.getInputProps('recipient_email')}
                    type="email"
                    label={t`Who are you giving it to?`}
                    placeholder={t`Their email address`}
                    autoComplete="off"
                    required
                    data-autofocus
                />

                <List size="sm" spacing={6} className={classes.steps}>
                    <List.Item>{t`We email them a link to accept the ticket.`}</List.Item>
                    <List.Item>{t`Your ticket keeps working until they accept, and you can cancel before then.`}</List.Item>
                    <List.Item>{t`Once they accept, the ticket is theirs and your copy stops working.`}</List.Item>
                </List>

                <ModalFooter>
                    <Button variant="default" onClick={onClose}>{t`Cancel`}</Button>
                    <Button type="submit" loading={isSubmitting} data-testid="ticket-transfer-submit-button">
                        {t`Send transfer`}
                    </Button>
                </ModalFooter>
            </form>
        </Modal>
    );
};
