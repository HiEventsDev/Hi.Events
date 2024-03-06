import {t} from "@lingui/macro";
import {Anchor, Button, Group, Input, Spoiler, TextInput, Tooltip} from "@mantine/core";
import {useNavigate, useParams} from "react-router-dom";
import {useMutation, useQueryClient} from "@tanstack/react-query";
import {notifications} from "@mantine/notifications";
import {
    orderClientPublic,
    TicketFormPayload,
    TicketFormValue,
    TicketPriceQuantityFormValue
} from "../../../../api/order.client.ts";
import {useForm} from "@mantine/form";
import {range, useInputState} from "@mantine/hooks";
import {useGetEventPublic} from "../../../../queries/useGetEventPublic.ts";
import React, {useEffect, useMemo, useRef} from "react";
import {useGetPromoCodePublic} from "../../../../queries/useGetPromoCodePublic.ts";
import {showError, showInfo, showSuccess} from "../../../../utilites/notifications.tsx";
import {Event, Ticket} from "../../../../types.ts";
import {prettyDate, relativeDate} from "../../../../utilites/dates.ts";
import {IconInfoCircle} from "@tabler/icons-react";
import {addQueryStringToUrl, isObjectEmpty} from "../../../../utilites/helpers.ts";
import {TieredPricing} from "./Prices/Tiered";
import classNames from 'classnames';

const TicketAvailabilityMessage = ({ticket, event}: { ticket: Ticket, event: Event }) => {
    if (ticket.is_sold_out) {
        return t`Sold out`;
    }
    if (ticket.is_after_sale_end_date) {
        return t`Sales ended`;
    }
    if (ticket.is_before_sale_start_date) {
        return (
            <span>
                {t`Sales start`}{' '}
                <Tooltip label={prettyDate(String(ticket.sale_start_date), event.timezone)}>
                    <span>{relativeDate(String(ticket.sale_start_date))}{' '}<IconInfoCircle size={12}/></span>
                </Tooltip>
            </span>
        );
    }

    return t`Not available`;
}

interface SelectTicketsProps {
    colors?: {
        primary?: string;
        primaryText?: string;
        secondary?: string;
        secondaryText?: string;
        background?: string;
    },
    padding?: string;
    continueButtonText?: string;
    isInPreviewMode?: boolean;
}

export const SelectTickets = (props: SelectTicketsProps) => {
    const {eventId} = useParams();
    const promoRef = useRef<HTMLInputElement>(null);
    const [promoCode, setPromoCode] = useInputState<string | null>(null);
    const [showPromoCodeInput, setShowPromoCodeInput] = useInputState<boolean>(false);
    const [isPromoCodeValid, setIsPromoCodeValid] = useInputState<boolean>(false);
    const {data: {valid: promoValid} = {}} = useGetPromoCodePublic(eventId, promoCode);
    const queryClient = useQueryClient();
    const navigate = useNavigate();
    const eventQuery = useGetEventPublic(eventId, true, isPromoCodeValid, promoCode);
    const {data: event, data: {tickets} = {}} = eventQuery;
    let ticketIndex = 0;

    const form = useForm<TicketFormPayload>({
        initialValues: {
            tickets: undefined,
            promo_code: null,
        }
    });

    const selectedTicketQuantitySum = useMemo(() => {
            let total = 0;
            form.values.tickets?.forEach(({quantities}) => {
                quantities?.forEach(({quantity}) => {
                    total += Number(quantity);
                });
            });

            return total;
        }
        , [form.values]);

    //todo - replace with hook
    const mutation = useMutation(
        (orderData: TicketFormPayload) => orderClientPublic.create(Number(eventId), orderData),
        {
            onSuccess: (data) => queryClient.invalidateQueries()
                .then(() => navigate('/checkout/' + eventId + '/' + data.data.short_id + '/details')),
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

    const populateFormValue = () => {
        const ticketValues: Array<TicketFormValue> = [];
        tickets?.forEach(ticket => {
            const quantitiesValues: Array<TicketPriceQuantityFormValue> = [];

            ticket.prices?.forEach(priceQuantity => {
                quantitiesValues.push({
                    quantity: 0,
                    price_id: Number(priceQuantity.id),
                    price: ticket.type === 'DONATION' ? Number(priceQuantity.price) : undefined,
                })
            });

            ticketValues.push({
                ticket_id: Number(ticket.id),
                quantities: quantitiesValues,
            })
        });

        form.setValues({
            tickets: ticketValues,
        })
    }

    useEffect(() => {
        if (promoValid !== undefined) {
            setIsPromoCodeValid(promoValid);
            if (promoValid === true) {
                addQueryStringToUrl('promo_code', String(promoCode));
                form.setFieldValue('promo_code', promoCode);
                showSuccess(t`Promo ${promoCode} code applied`);
            }
            if (!promoValid) {
                showError(t`That promo code is invalid`);
            }
        }
    }, [promoValid]);

    useEffect(populateFormValue, [tickets]);

    useEffect(() => {
        const searchParams = new URLSearchParams(window.location.search);
        const promoCode = searchParams.get('promo_code');
        if (promoCode) {
            setPromoCode(promoCode);
            setShowPromoCodeInput(false);
        }
    }, [])

    if (!eventQuery.isFetched) {
        return <></>;
    }

    const handleTicketSelection = (values: TicketFormPayload) => {
        if (values && selectedTicketQuantitySum > 0) {
            mutation.mutate(values);
        } else {
            showInfo(t`Please select at least one ticket`);
        }
    };

    const handleApplyPromoCode = () => {
        const promoCode = promoRef.current?.value;
        if (promoCode && promoCode.length >= 3) {
            setPromoCode(promoCode);
        } else {
            showError(t`Sorry, this promo code is invalid'`);
        }
    }

    const handleApplyPromoCodeKeyPress = (event: KeyboardEvent) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            handleApplyPromoCode();
        }
    }

    return (
        <div className={'hi-ticket-widget-container'} style={{
            '--widget-background-color': props.colors?.background,
            '--widget-primary-color': props.colors?.primary,
            '--widget-primary-text-color': props.colors?.primaryText,
            '--widget-secondary-color': props.colors?.secondary,
            '--widget-secondary-text-color': props.colors?.secondaryText,
            '--widget-padding': props?.padding,
        } as React.CSSProperties}>
            {tickets?.length === 0 && (
                <div className={classNames(['hi-no-tickets'])}>
                    <h2 className={classNames(['hi-no-tickets-heading'])}>{t`No tickets available`}</h2>
                    <p className={classNames(['hi-no-tickets-message'])}>
                        {t`There are no tickets available for this event`}
                    </p>
                </div>
            )}

            {(event && tickets && tickets.length > 0) && (
                <form onSubmit={form.onSubmit(handleTicketSelection)}>
                    <Input type={'hidden'} {...form.getInputProps('promo_code')} />
                    <div className={'hi-ticket-rows'}>
                        {(event && tickets) && tickets.map((ticket) => {
                            const quantityRange = range(ticket.min_per_order || 1, ticket.max_per_order || 25)
                                .map((n) => n.toString());
                            quantityRange.unshift("0");

                            const index = ticketIndex++

                            return (
                                <div key={ticket.id} className={'hi-ticket-row'}>
                                    <div className={'hi-title-row'}>
                                        <div className={'hi-ticket-title'}>
                                            <h3>{ticket.title}</h3>
                                        </div>
                                        <div className={'hi-ticket-availability'}>
                                            {(!ticket.is_available && event) && (
                                                <TicketAvailabilityMessage ticket={ticket} event={event}/>
                                            )}
                                        </div>
                                    </div>
                                    <div className={'hi-price-tiers-rows'}>
                                        <TieredPricing
                                            ticketIndex={index}
                                            event={event}
                                            ticket={ticket}
                                            form={form}
                                        />
                                    </div>

                                    {ticket.max_per_order && form.values.tickets && isObjectEmpty(form.errors) && (form.values.tickets[index].quantities.reduce((acc, {quantity}) => acc + Number(quantity), 0) > ticket.max_per_order) && (
                                        <div className={'hi-ticket-quantity-error'}>
                                            {t`The maximum numbers number of tickets for Generals is ${ticket.max_per_order}`}
                                        </div>
                                    )}

                                    {form.errors[`tickets.${index}`] && (
                                        <div className={'hi-ticket-quantity-error'}>
                                            {form.errors[`tickets.${index}`]}
                                        </div>
                                    )}

                                    {ticket.description && (
                                        <div
                                            className={'hi-ticket-description-row'}>
                                            <Spoiler maxHeight={87} showLabel={t`Show more`} hideLabel={t`Hide`}>
                                                <div dangerouslySetInnerHTML={{
                                                    __html: ticket.description
                                                }}/>
                                            </Spoiler>
                                        </div>
                                    )}
                                </div>
                            )
                        })}
                    </div>

                    <div className={'hi-footer-row'}>
                        {event?.settings?.ticket_page_message && (
                            <div dangerouslySetInnerHTML={{
                                __html: event.settings.ticket_page_message.replace(/\n/g, '<br/>')
                            }} className={'hi-ticket-page-message'}/>
                        )}
                        <Button disabled={props.isInPreviewMode} fullWidth className={'hi-continue-button'}
                                type={"submit"}
                                loading={mutation.isLoading}>
                            {props.continueButtonText || event?.settings?.continue_button_text || t`Continue`}
                        </Button>
                    </div>
                </form>
            )}
            <div className={'hi-promo-code-row'}>
                {(!showPromoCodeInput && !promoCode) && (
                    <Anchor className={'hi-have-a-promo-code-link'}
                            onClick={() => setShowPromoCodeInput(true)}>
                        {t`Have a promo code?`}
                    </Anchor>
                )}
                {promoValid && (
                    <div className={'hi-promo-code-applied'}>
                        <b>{promoCode}</b> {t`applied`}
                    </div>
                )}
                {(!promoValid && showPromoCodeInput) && (
                    <Group className={'hi-promo-code-input-wrapper'} wrap={'nowrap'} gap={'20px'}>
                        {/* eslint-disable-next-line @typescript-eslint/ban-ts-comment */}
                        {/*@ts-ignore*/}
                        <TextInput classNames={{input: 'hi-promo-code-input'}} onKeyPress={handleApplyPromoCodeKeyPress} mb={0} ref={promoRef}/>
                        <Button className={'hi-apply-promo-code-button'} variant={'outline'}
                                onClick={handleApplyPromoCode}>
                            {t`Apply Promo Code`}
                        </Button>
                    </Group>
                )}
            </div>

        </div>
    );
}