import {Button} from "@mantine/core";
import {GenericModalProps, TaxAndFee, Ticket, TicketType} from "../../../types.ts";
import {useForm} from "@mantine/form";
import {useMutation, useQueryClient} from "@tanstack/react-query";
import {notifications} from "@mantine/notifications";
import {ticketClient} from "../../../api/ticket.client.ts";
import {useParams} from "react-router-dom";
import {Modal} from "../../common/Modal";
import {TicketForm} from "../../forms/TicketForm";
import {GET_TICKETS_QUERY_KEY} from "../../../queries/useGetTickets.ts";
import {useEffect} from "react";
import {useGetTaxesAndFees} from "../../../queries/useGetTaxesAndFees.ts";
import {t} from "@lingui/macro";

export const CreateTicketModal = ({onClose}: GenericModalProps) => {
    const {eventId} = useParams();
    const queryClient = useQueryClient();
    const {data: taxesAndFees, isFetched: taxesAndFeesLoaded} = useGetTaxesAndFees();
    const form = useForm<Ticket>({
        initialValues: {
            title: '',
            description: '',
            max_per_order: 100,
            min_per_order: 1,
            sale_start_date: '',
            sale_end_date: '',
            hide_before_sale_start_date: false,
            hide_after_sale_end_date: false,
            show_quantity_remaining: false,
            hide_when_sold_out: false,
            is_hidden_without_promo_code: false,
            type: TicketType.Paid,
            tax_and_fee_ids: undefined,
            prices: [{
                price: 0,
                label: undefined,
                sale_end_date: undefined,
                sale_start_date: undefined,
                initial_quantity_available: undefined,
            }],
        },
    });

    const mutation = useMutation(
        (ticketData: Ticket) => ticketClient.create(eventId, ticketData),
        {
            onSuccess: () => {
                notifications.show({
                    message: t`Successfully Created Ticket`,
                    color: 'green',
                });
                queryClient.invalidateQueries({queryKey: [GET_TICKETS_QUERY_KEY]}).then(() => {
                    form.reset();
                    onClose();
                });
            },
            onError: (error: any) => {
                if (error?.response?.data?.errors) {
                    form.setErrors(error.response.data.errors);
                }

                notifications.show({
                    message: t`Unable to create ticket. Please check the your details`,
                    color: 'red',
                });
            },
        }
    );

    useEffect(() => {
        form.setFieldValue('tax_and_fee_ids', taxesAndFees
            ?.data
            ?.filter((item) => item.is_default)
            .map((item: TaxAndFee) => {
                return String(item.id);
            }) || []);
    }, [taxesAndFeesLoaded]);

    return (
        <Modal
            onClose={onClose}
            heading={t`Create Ticket`}
            opened
            size={'lg'}
            withCloseButton
        >
            <form onSubmit={form.onSubmit((values) => mutation.mutate(values as any as Ticket))}>
                <TicketForm form={form}/>
                <Button type="submit" fullWidth disabled={mutation.isLoading}>
                    {mutation.isLoading ? t`Working...` : t`Create Ticket`}
                </Button>
            </form>
        </Modal>
    )
};
