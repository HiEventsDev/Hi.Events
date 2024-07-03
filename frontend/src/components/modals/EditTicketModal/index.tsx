import {Button} from "@mantine/core";
import {GenericModalProps, IdParam, Ticket, TicketType} from "../../../types.ts";
import {useForm} from "@mantine/form";
import {useParams} from "react-router-dom";
import {useEffect} from "react";
import {TicketForm} from "../../forms/TicketForm";
import {Modal} from "../../common/Modal";
import {useUpdateTicket} from "../../../mutations/useUpdateTicket.ts";
import {showSuccess} from "../../../utilites/notifications.tsx";
import {useFormErrorResponseHandler} from "../../../hooks/useFormErrorResponseHandler.tsx";
import {t} from "@lingui/macro";
import {useGetTicket} from "../../../queries/useGetTicket.ts";
import {LoadingMask} from "../../common/LoadingMask";
import {utcToTz} from "../../../utilites/dates.ts";
import {useGetEvent} from "../../../queries/useGetEvent.ts";

export const EditTicketModal = ({onClose, ticketId}: GenericModalProps & { ticketId: IdParam }) => {
    const {eventId} = useParams();
    const {data: ticket} = useGetTicket(eventId, ticketId);
    const {data: event} = useGetEvent(eventId);
    const errorHandler = useFormErrorResponseHandler();
    const form = useForm<Ticket>({
        initialValues: {
            title: '',
            description: '',
            max_per_order: 100,
            min_per_order: 1,
            sale_start_date: undefined,
            sale_end_date: undefined,
            hide_before_sale_start_date: undefined,
            hide_after_sale_end_date: undefined,
            show_quantity_remaining: undefined,
            hide_when_sold_out: undefined,
            is_hidden_without_promo_code: undefined,
            type: TicketType.Paid,
            tax_and_fee_ids: [],
            prices: []
        },
    });

    const mutation = useUpdateTicket();

    useEffect(() => {
        if (!ticket || !event) {
            return;
        }

        form.setValues({
            id: ticket.id,
            title: ticket.title,
            description: ticket.description,
            max_per_order: ticket.max_per_order ?? 0,
            min_per_order: ticket.min_per_order ?? 0,
            sale_start_date: utcToTz(ticket.sale_start_date, event.timezone),
            sale_end_date: utcToTz(ticket.sale_end_date, event.timezone),
            hide_before_sale_start_date: ticket.hide_before_sale_start_date,
            hide_after_sale_end_date: ticket.hide_after_sale_end_date,
            show_quantity_remaining: ticket.show_quantity_remaining,
            hide_when_sold_out: ticket.hide_when_sold_out,
            is_hidden_without_promo_code: ticket.is_hidden_without_promo_code,
            type: ticket.type,
            tax_and_fee_ids: ticket.taxes_and_fees?.map(t => String(t.id)) ?? [],
            is_hidden: ticket.is_hidden,
            prices: ticket.prices?.map(p => ({
                price: p.price ?? 0,
                label: p.label,
                sale_start_date: utcToTz(p.sale_start_date, event.timezone),
                sale_end_date: utcToTz(p.sale_end_date, event.timezone),
                initial_quantity_available: p.initial_quantity_available ?? undefined,
                id: p.id,
                is_hidden: p.is_hidden,
            })) ?? [],
        });
    }, [ticket, event]);

    const handleEditTicket = (ticket: Ticket) => {
        mutation.mutate({
            ticketData: ticket,
            eventId: eventId,
            ticketId: ticketId
        }, {
            onSuccess: () => {
                showSuccess(t`Successfully updated ticket ` + ticket.title);
                form.reset();
                onClose();
            },
            onError: (error) => errorHandler(form, error)
        })
    }

    return (
        <Modal
            onClose={onClose}
            heading={t`Edit Ticket`}
            opened
        >
            <form onSubmit={form.onSubmit(handleEditTicket)}>
                <TicketForm ticket={ticket} form={form}/>
                <LoadingMask/>

                <Button type="submit" fullWidth mt="xl" disabled={mutation.isLoading}>
                    {mutation.isLoading ? t`Working...` : t`Edit Ticket`}
                </Button>
            </form>
        </Modal>
    )
};
