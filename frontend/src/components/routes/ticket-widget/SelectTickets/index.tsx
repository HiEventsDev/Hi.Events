import {t, Trans} from "@lingui/macro";
import {ActionIcon, Anchor, Button, Group, Input, Spoiler, TextInput} from "@mantine/core";
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
import React, {useEffect, useMemo, useRef, useState} from "react";
import {showError, showInfo, showSuccess} from "../../../../utilites/notifications.tsx";
import {addQueryStringToUrl,removeQueryStringFromUrl, isObjectEmpty} from "../../../../utilites/helpers.ts";
import {TieredPricing} from "./Prices/Tiered";
import classNames from 'classnames';
import '../../../../styles/widget/default.scss';
import {TicketAvailabilityMessage} from "../../../common/TicketPriceAvailability";
import {PoweredByFooter} from "../../../common/PoweredByFooter";
import { Event } from "../../../../types.ts";
import { eventsClientPublic } from "../../../../api/event.client.ts";
import { promoCodeClientPublic } from "../../../../api/promo-code.client.ts";
import {IconX} from "@tabler/icons-react"

interface SelectTicketsProps {
    event: Event;
    promoCodeValid?: boolean;
    promoCode?: string;
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
    const queryClient = useQueryClient();
    const navigate = useNavigate();
    
    const promoRef = useRef<HTMLInputElement>(null);
    const [showPromoCodeInput, setShowPromoCodeInput] = useInputState<boolean>(false);
    const [event, setEvent] = useState(props.event);
     
    const form = useForm<TicketFormPayload>({
        initialValues: {
            tickets: undefined,
            promo_code: props.promoCodeValid ? props.promoCode || null : null, 
        }, 
    });

    //todo - replace with hook
    const ticketMutation = useMutation(
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
   
    const promoCodeEventRefetchMutation = useMutation({
        mutationFn: async (promoCode: string | null) => {
            if (promoCode)  {
                const validPromocode = await promoCodeClientPublic.validateCode(
                    eventId,
                    promoCode
                );

                if (!validPromocode.valid) {
                    showError(t`That promo code is invalid`);
                    return;
                }
            } 

            // fetch fresh event even if the promo code is removed.
            const eventWithPromoCodeApplied = await eventsClientPublic.findByID(
                eventId,
                promoCode
            );

            setEvent(eventWithPromoCodeApplied.data);

            if (promoCode) {
                form.setFieldValue("promo_code", promoCode);
            } else {
                // if it's removed
                form.setFieldValue("promo_code", null);
                setShowPromoCodeInput(false)
                removeQueryStringFromUrl('promo_code');
            }

        },
    });

    const tickets = event.tickets || [];
    const ticketAreAvailable = tickets && tickets.length > 0;

    const selectedTicketQuantitySum = useMemo(() => {
            let total = 0;
            form.values.tickets?.forEach(({quantities}) => {
                quantities?.forEach(({quantity}) => {
                    total += Number(quantity);
                });
            });

            return total;
        }, [form.values]);

    useEffect(()=>{
        if (form.values.promo_code) {
            const promo_code = form.values.promo_code
            showSuccess(t`Promo ${promo_code} code applied`);
            addQueryStringToUrl('promo_code', promo_code);
        }
    }, [form.values.promo_code])

    useEffect(()=>{
        if (typeof props.promoCodeValid !== 'undefined') {
            if (!props.promoCodeValid) {
                showError(t`That promo code is invalid`);
                removeQueryStringFromUrl('promo_code');
            }
        }
    },[props.promoCodeValid])


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

        form.setFieldValue("tickets", ticketValues)
    }


    useEffect(populateFormValue, [tickets]);


    const handleTicketSelection = (values: TicketFormPayload) => {
        if (values && selectedTicketQuantitySum > 0) {
            ticketMutation.mutate(values);
        } else {
            showInfo(t`Please select at least one ticket`);
        }
    };

    const handleApplyPromoCode = () => {
        const promoCode = promoRef.current?.value;
        if (promoCode && promoCode.length >= 3) {
            promoCodeEventRefetchMutation.mutate(promoCode);
        } else {
            showError(t`Sorry, this promo code is invalid'`);
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
            {!ticketAreAvailable && (
                <div className={classNames(['hi-no-tickets'])}>
                    <p className={classNames(['hi-no-tickets-message'])}>
                        {t`There are no tickets available for this event`}
                    </p>
                </div>
            )}

            {(event && ticketAreAvailable) && (
                <form onSubmit={form.onSubmit(handleTicketSelection)}>
                    <Input type={'hidden'} {...form.getInputProps('promo_code')} />
                    <div className={'hi-ticket-rows'}>
                        {(tickets) && tickets.map((ticket, ticketIndex) => {
                            const quantityRange = range(ticket.min_per_order || 1, ticket.max_per_order || 25)
                                .map((n) => n.toString());
                            quantityRange.unshift("0");


                            return (
                                <div key={ticket.id} className={'hi-ticket-row'}>
                                    <div className={'hi-title-row'}>
                                        <div className={'hi-ticket-title'}>
                                            <h3>{ticket.title}</h3>
                                        </div>
                                        <div className={'hi-ticket-availability'}>
                                            {(!ticket.is_available && ticket.type === 'TIERED') && (
                                                <TicketAvailabilityMessage ticket={ticket} event={event}/>
                                            )}
                                        </div>
                                    </div>
                                    <div className={'hi-price-tiers-rows'}>
                                        <TieredPricing
                                            ticketIndex={ticketIndex}
                                            event={event}
                                            ticket={ticket}
                                            form={form}
                                        />
                                    </div>

                                    {ticket.max_per_order && form.values.tickets && isObjectEmpty(form.errors) && (form.values.tickets[ticketIndex]?.quantities.reduce((acc, {quantity}) => acc + Number(quantity), 0) > ticket.max_per_order) && (
                                        <div className={'hi-ticket-quantity-error'}>
                                            <Trans>The maximum numbers number of tickets for Generals is {ticket.max_per_order}</Trans>
                                        </div>
                                    )}

                                    {form.errors[`tickets.${ticketIndex}`] && (
                                        <div className={'hi-ticket-quantity-error'}>
                                            {form.errors[`tickets.${ticketIndex}`]}
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
                                loading={ticketMutation.isLoading}>
                            {props.continueButtonText || event?.settings?.continue_button_text || t`Continue`}
                        </Button>
                    </div>
                </form>
            )}

            {ticketAreAvailable && (
                <div className={'hi-promo-code-row'}>
                    {(!showPromoCodeInput && !form.values.promo_code) && (
                        <Anchor className={'hi-have-a-promo-code-link'}
                                onClick={() => setShowPromoCodeInput(true)}>
                            {t`Have a promo code?`}
                        </Anchor>
                    )}
                    {form.values.promo_code && (
                        <div className={'hi-promo-code-applied'}>
                            <span><b>{form.values.promo_code}</b> {t`applied`}</span>
                            <ActionIcon 
                                className={'hi-promo-code-applied-remove-icon-button'} 
                                variant="outline" 
                                aria-label={t`remove`}
                                onClick={()=>{
                                    promoCodeEventRefetchMutation.mutate(null)                          
                                }}
                            >
                                <IconX />
                            </ActionIcon>
                        </div>
                    )}
                    {(showPromoCodeInput && !form.values.promo_code) && (
                        <Group className={'hi-promo-code-input-wrapper'} wrap={'nowrap'} gap={'20px'}>
                            {/* eslint-disable-next-line @typescript-eslint/ban-ts-comment */}
                            {/*@ts-ignore*/}
                            <TextInput autoFocus classNames={{input: 'hi-promo-code-input'}} onKeyDown={(event)=>{
                                if (event.key === 'Enter') {
                                    event.preventDefault();
                                    handleApplyPromoCode();
                                }
                            }} mb={0} ref={promoRef}/>
                            <Button disabled={promoCodeEventRefetchMutation.isLoading} className={'hi-apply-promo-code-button'} variant={'outline'}
                                    onClick={handleApplyPromoCode}>
                                {t`Apply Promo Code`}
                            </Button>
                        </Group>
                    )}
                </div>
            )}
            <PoweredByFooter style={{
                'color': props.colors?.primaryText || '#000',
            }}/>
        </div>
    );
}

export default SelectTickets;