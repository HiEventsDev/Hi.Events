import {Modal} from "../../common/Modal";
import {GenericModalProps} from "../../../types.ts";
import {Button} from "../../common/Button";
import {useNavigate, useParams} from "react-router-dom";
import {useFormErrorResponseHandler} from "../../../hooks/useFormErrorResponseHandler.tsx";
import {useForm} from "@mantine/form";
import {LoadingOverlay, NumberInput, Select, Switch, TextInput} from "@mantine/core";
import {useGetEvent} from "../../../queries/useGetEvent.ts";
import {CreateAttendeeRequest} from "../../../api/attendee.client.ts";
import {useCreateAttendee} from "../../../mutations/useCreateAttendee.ts";
import {showSuccess} from "../../../utilites/notifications.tsx";
import {t, Trans} from "@lingui/macro";
import {useEffect} from "react";
import {InputGroup} from "../../common/InputGroup";
import {
    getClientLocale,
    getLocaleName,
    localeToFlagEmojiMap,
    localeToNameMap,
    SupportedLocales
} from "../../../locales.ts";

export const CreateAttendeeModal = ({onClose}: GenericModalProps) => {
    const {eventId} = useParams();
    const errorHandler = useFormErrorResponseHandler();
    const {data: event, isFetched: isEventFetched} = useGetEvent(eventId);
    const mutation = useCreateAttendee();
    const navigate = useNavigate();

    const form = useForm<CreateAttendeeRequest>({
        initialValues: {
            ticket_id: undefined,
            email: '',
            first_name: '',
            last_name: '',
            amount_paid: 0.00,
            send_confirmation_email: true,
            taxes_and_fees: [],
            locale: getClientLocale() as SupportedLocales,
        },
    });

    useEffect(() => {
        if (event?.tickets) {
            form.setFieldValue(
                'ticket_price_id',
                String(event?.tickets?.find(ticket => ticket.id == form.values.ticket_id)?.prices?.[0]?.id)
            );

            const taxesAndFees = event?.tickets
                ?.find(ticket => ticket.id == form.values.ticket_id)
                ?.taxes_and_fees;

            if (taxesAndFees?.length === 0) {
                form.setFieldValue('taxes_and_fees', []);
            }

            taxesAndFees?.forEach((tax, index) => {
                    form.setFieldValue(
                        `taxes_and_fees.${index}`,
                        {
                            tax_or_fee_id: tax.id,
                            amount: 0.00,
                            name: tax.name,
                        },
                    );
                }
            );
        }
    }, [form.values.ticket_id]);

    const handleSubmit = (values: CreateAttendeeRequest) => {
        mutation.mutate({
            eventId: eventId,
            attendeeData: values,
        }, {
            onSuccess: () => {
                showSuccess(t`Successfully created attendee`);
                onClose();
            },
            onError: (error) => errorHandler(form, error),
        })
    };

    if (!event?.tickets) {
        return (
            <LoadingOverlay visible/>
        )
    }

    if (isEventFetched && event.tickets.length === 0) {
        return (
            <Modal opened onClose={onClose} heading={t`Manually Add Attendee`}>
                <p>{t`You must create a ticket before you can manually add an attendee.`}</p>
                <Button
                    fullWidth
                    variant={'light'}
                    onClick={() => {
                        navigate(`/manage/event/${eventId}/tickets`)
                    }}
                >
                    {t`Manage tickets`}
                </Button>
            </Modal>
        )
    }

    return (
        <Modal opened onClose={onClose} heading={t`Manually Add Attendee`}>
            <form onSubmit={form.onSubmit(handleSubmit)}>
                <InputGroup>
                    <TextInput
                        {...form.getInputProps('first_name')}
                        label={t`First name`}
                        placeholder={t`Patrick`}
                        required
                    />

                    <TextInput
                        {...form.getInputProps('last_name')}
                        label={t`Last name`}
                        placeholder={t`Johnson`}
                        required
                    />
                </InputGroup>
                <TextInput
                    {...form.getInputProps('email')}
                    label={t`Email address`}
                    placeholder={t`patrick@acme.com`}
                    required
                />

                <Select
                    required
                    data={Object.keys(localeToNameMap).map(locale => ({
                        value: locale,
                        label: localeToFlagEmojiMap[locale as SupportedLocales] + ' ' + getLocaleName(locale as SupportedLocales),
                    }))}
                    {...form.getInputProps('locale')}
                    label={t`Language`}
                    placeholder={t`English`}
                    description={t`The language the attendee will receive emails in.`}
                />

                <Select
                    label={t`Ticket`}
                    mt={20}
                    description={t`Manually adding an attendee will adjust ticket quantity.`}
                    placeholder={t`Select Ticket`}
                    {...form.getInputProps('ticket_id')}
                    data={event.tickets.map(ticket => {
                        return {
                            value: String(ticket.id),
                            label: ticket.title,
                        };
                    })}
                />

                {event.tickets.find(ticket => ticket.id == form.values.ticket_id)?.type === 'TIERED' && (
                    <Select
                        label={t`Ticket Tier`}
                        mt={20}
                        placeholder={t`Select Ticket Tier`}
                        {...form.getInputProps('ticket_price_id')}
                        data={event?.tickets?.find(ticket => ticket.id == form.values.ticket_id)?.prices?.map(price => {
                            return {
                                value: String(price.id),
                                label: String(price.label),
                            };
                        })}
                    />
                )}

                <NumberInput
                    required
                    mt={20}
                    fixedDecimalScale
                    {...form.getInputProps('amount_paid')}
                    label={<Trans>Amount paid ({event?.currency})</Trans>}
                    placeholder="0.00"
                    decimalScale={2}
                    step={1}
                    min={0}
                    description={t`Enter an amount excluding taxes and fees.`}
                />

                {form.values.taxes_and_fees?.map((tax, index) => {
                        return (
                            <NumberInput
                                required
                                mt={20}
                                fixedDecimalScale
                                {...form.getInputProps(`taxes_and_fees.${index}.amount`)}
                                label={tax.name + ' ' + t`paid` + ' (' + event?.currency + ')'}
                                placeholder="0.00"
                                decimalScale={2}
                                step={1}
                                min={0}
                            />
                        )
                    }
                )}

                <Switch
                    mt={20}
                    label={t`Send order confirmation and ticket email`}
                    {...form.getInputProps('send_confirmation_email', {type: 'checkbox'})}
                />
                <Button type="submit" fullWidth mt="xl" disabled={mutation.isLoading}>
                    {mutation.isLoading ? t`Working` + '...' : t`Create Attendee`}
                </Button>
            </form>
        </Modal>
    );
}
