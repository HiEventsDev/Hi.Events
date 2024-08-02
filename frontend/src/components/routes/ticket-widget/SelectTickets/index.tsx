import {t, Trans} from "@lingui/macro";
import {ActionIcon, Anchor, Button, Group, Input, Modal, Spoiler, TextInput} from "@mantine/core";
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
import {range, useInputState, useResizeObserver} from "@mantine/hooks";
import React, {useEffect, useMemo, useRef, useState} from "react";
import {showError, showInfo, showSuccess} from "../../../../utilites/notifications.tsx";
import {addQueryStringToUrl, isObjectEmpty, removeQueryStringFromUrl} from "../../../../utilites/helpers.ts";
import {TieredPricing} from "./Prices/Tiered";
import classNames from 'classnames';
import '../../../../styles/widget/default.scss';
import {TicketAvailabilityMessage} from "../../../common/TicketPriceAvailability";
import {PoweredByFooter} from "../../../common/PoweredByFooter";
import {Event} from "../../../../types.ts";
import {eventsClientPublic} from "../../../../api/event.client.ts";
import {promoCodeClientPublic} from "../../../../api/promo-code.client.ts";
import {IconX} from "@tabler/icons-react"
import {getSessionIdentifier} from "../../../../utilites/sessionIdentifier.ts";

const sendHeightToIframeWidgets = () => {
    const height = document.documentElement.scrollHeight;
    const urlParams = new URLSearchParams(window.location.search);
    const iframeId = urlParams.get('iframeId');

    if (!iframeId) {
        return;
    }

    window.parent.postMessage({
        type: 'resize',
        height: height,
        iframeId: iframeId
    }, '*');
};

interface SelectTicketsProps {
    event: Event;
    promoCodeValid?: boolean;
    promoCode?: string;
    backgroundType?: 'COLOR' | 'MIRROR_COVER_IMAGE';
    colors?: {
        primary?: string;
        primaryText?: string;
        secondary?: string;
        secondaryText?: string;
        background?: string;
        bodyBackground?: string;
    },
    padding?: string;
    continueButtonText?: string;
    widgetMode?: 'preview' | 'normal' | 'embedded';
}

const SelectTickets = (props: SelectTicketsProps) => {
    const {eventId} = useParams();
    const queryClient = useQueryClient();
    const navigate = useNavigate();

    const promoRef = useRef<HTMLInputElement>(null);
    const [showPromoCodeInput, setShowPromoCodeInput] = useInputState<boolean>(false);
    const [event, setEvent] = useState(props.event);
    const [orderInProcessOverlayVisible, setOrderInProcessOverlayVisible] = useState(false);
    const [resizeRef, resizeObserverRect] = useResizeObserver();

    useEffect(() => sendHeightToIframeWidgets(), [resizeObserverRect.height]);

    const form = useForm<TicketFormPayload>({
        initialValues: {
            tickets: undefined,
            promo_code: props.promoCodeValid ? props.promoCode || null : null,
            session_identifier: undefined,
        },
    });

    //todo - replace with hook
    const ticketMutation = useMutation(
        (orderData: TicketFormPayload) => orderClientPublic.create(Number(eventId), orderData),
        {
            onSuccess: (data) => queryClient.invalidateQueries()
                .then(() => {
                    const url = '/checkout/' + eventId + '/' + data.data.short_id + '/details';
                    if (props.widgetMode === 'embedded') {
                        window.open(url, '_blank');
                        setOrderInProcessOverlayVisible(true);
                        return;
                    }

                    return navigate(url);
                }),
            onError: (error: any) => {
                if (error?.response?.data?.errors) {
                    form.setErrors(error.response.data.errors);
                }

                notifications.show({
                    message: error.response.data.errors?.tickets[0] || t`Unable to create ticket. Please check the your details`,
                    color: 'red',
                });
            },
        }
    );

    const promoCodeEventRefetchMutation = useMutation({
        mutationFn: async (promoCode: string | null) => {
            if (promoCode) {
                const validPromoCode = await promoCodeClientPublic.validateCode(
                    eventId,
                    promoCode
                );

                if (!validPromoCode.valid) {
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
                form.setFieldValue("promo_code", null);
                setShowPromoCodeInput(false)
                removeQueryStringFromUrl('promo_code');
            }
        },
    });

    const tickets = event?.tickets || [];
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

    useEffect(() => {
        if (form.values.promo_code) {
            const promo_code = form.values.promo_code
            showSuccess(t`Promo ${promo_code} code applied`);
            addQueryStringToUrl('promo_code', promo_code);
        }
    }, [form.values.promo_code])

    useEffect(() => {
        if (typeof props.promoCodeValid !== 'undefined') {
            if (!props.promoCodeValid) {
                showError(t`That promo code is invalid`);
                removeQueryStringFromUrl('promo_code');
            }
        }
    }, [props.promoCodeValid])


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

            // this is hacky way to add empty quantity for a sold out ticket.
            // this is needed to avoid validation error when the ticket is sold out.
            // @todo - refactor this code so returning here doesn't break the checkout process.
            if (quantitiesValues.length === 0) {
                quantitiesValues.push({
                    quantity: 0,
                    price_id: 0,
                    price: 0,
                })
            }

            ticketValues.push({
                ticket_id: Number(ticket.id),
                quantities: quantitiesValues,
            })
        });

        form.setFieldValue("tickets", ticketValues)
    }

    useEffect(populateFormValue, [tickets]);

    const handleTicketSelection = (values: Omit<TicketFormPayload, "session_identifier">) => {
        if (values && selectedTicketQuantitySum > 0) {
            ticketMutation.mutate({
                ...values,
                session_identifier: getSessionIdentifier()
            });
        } else {
            showInfo(t`Please select at least one ticket`);
        }
    };

    const handleApplyPromoCode = () => {
        const promoCode = promoRef.current?.value;
        if (promoCode && promoCode.length >= 3) {
            promoCodeEventRefetchMutation.mutate(promoCode);
        } else {
            showError(t`Sorry, this promo code is not recognized`);
        }
    }

    const isButtonDisabled = ticketMutation.isLoading
        || !ticketAreAvailable
        || selectedTicketQuantitySum === 0
        || props.widgetMode === 'preview'
        || tickets?.every(ticket => ticket.is_sold_out);

    return (
        <div className={'hi-ticket-widget-container'}
             ref={resizeRef}
             style={{
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

            {orderInProcessOverlayVisible && (
                <Modal withCloseButton={false} opened={true} onClose={() => setOrderInProcessOverlayVisible(false)}>
                    <div style={{textAlign: 'center', padding: '20px'}}>
                        <img style={{width: '110px'}} src={'/stopwatch-ticket-icon.svg'} alt={''}/>
                        <div>
                            <h4 style={{margin: '0'}}>
                                {t`Please continue in the new tab`}
                            </h4>
                            <Trans>
                                If a new tab did not open, please {' '}
                                <a href={'/checkout/' + eventId + '/' + ticketMutation.data?.data.short_id + '/details'}
                                   target={'_blank'} rel={'noopener noreferrer'}>
                                    <b>{t`click here`}</b>.
                                </a>
                            </Trans>
                            <Button
                                style={{marginTop: '20px'}}
                                onClick={() => setOrderInProcessOverlayVisible(false)}
                                variant={'transparent'}
                                size={'xs'}
                            >
                                {t`Dismiss`}
                            </Button>
                        </div>
                    </div>
                </Modal>
            )}

            {(event && ticketAreAvailable) && (
                <form target={'__blank'} onSubmit={form.onSubmit(handleTicketSelection as any)}>
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
                                            {(ticket.is_available && !!ticket.quantity_available) && (
                                                <>
                                                    <Trans>{ticket?.quantity_available} available</Trans>
                                                </>
                                            )}

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
                                            <Trans>The maximum numbers number of tickets for Generals
                                                is {ticket.max_per_order}</Trans>
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
                        <Button disabled={isButtonDisabled} fullWidth className={'hi-continue-button'}
                                type={"submit"}
                                loading={ticketMutation.isLoading}>
                            {props.continueButtonText || event?.settings?.continue_button_text || t`Continue`}
                        </Button>
                    </div>
                </form>
            )}
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
                            variant="transparent"
                            aria-label={t`remove`}
                            title={t`Remove`}
                            onClick={() => {
                                promoCodeEventRefetchMutation.mutate(null)
                            }}
                        >
                            <IconX stroke={1.5} size={20}/>
                        </ActionIcon>
                    </div>
                )}
            </div>
            {(showPromoCodeInput && !form.values.promo_code) && (
                <Group className={'hi-promo-code-input-wrapper'} wrap={'nowrap'} gap={'20px'}>
                    {/* eslint-disable-next-line @typescript-eslint/ban-ts-comment */}
                    {/*@ts-ignore*/}
                    <TextInput autoFocus classNames={{input: 'hi-promo-code-input'}} onKeyDown={(event) => {
                        if (event.key === 'Enter') {
                            event.preventDefault();
                            handleApplyPromoCode();
                        }
                    }} mb={0} ref={promoRef}/>
                    <Button disabled={promoCodeEventRefetchMutation.isLoading}
                            className={'hi-apply-promo-code-button'} variant={'outline'}
                            onClick={handleApplyPromoCode}>
                        {t`Apply Promo Code`}
                    </Button>
                </Group>
            )}
            {
                /**
                 * (c) Hi.Events Ltd 2024
                 *
                 * PLEASE NOTE:
                 *
                 * Hi.Events is licensed under the GNU Affero General Public License (AGPL) version 3.
                 *
                 * You can find the full license text at: https://github.com/HiEventsDev/hi.events/blob/main/LICENSE
                 *
                 * In accordance with Section 7(b) of the AGPL, we ask that you retain the "Powered by Hi.Events" notice.
                 *
                 * If you wish to remove this notice, a commercial license is available at: https://hi.events/licensing
                 */
            }
            <PoweredByFooter style={{
                'color': props.colors?.primaryText || '#000',
            }}/>
        </div>
    );
}

export default SelectTickets;
