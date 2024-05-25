import {Currency, TicketPriceDisplay} from "../../../../../common/Currency";
import {Event, Ticket, TicketType} from "../../../../../../types.ts";
import {Group, TextInput} from "@mantine/core";
import {NumberSelector} from "../../../../../common/NumberSelector";
import {UseFormReturnType} from "@mantine/form";
import {t} from "@lingui/macro";
import {TicketPriceAvailability} from "../../../../../common/TicketPriceAvailability";

interface TieredPricingProps {
    event: Event;
    ticket: Ticket;
    form: UseFormReturnType<any>;
    ticketIndex: number;
}

export const TieredPricing = ({ticket, event, form, ticketIndex}: TieredPricingProps) => {
    return (
        <>
            {ticket?.prices?.map((price, index) => {
                return (
                    <div key={index} className={'hi-price-tier-row'}>
                        <Group justify={'space-between'}>
                            <div className={'hi-price-tier'}>
                                <div className={'hi-price-tier-label'}>{price.label}</div>
                                <div className={'hi-price-tier-price'}>
                                    {ticket.type === 'DONATION' && (
                                        <div
                                            className={'hi-donation-input-wrapper'}>
                                            <TextInput
                                                {...form.getInputProps(`tickets.${ticketIndex}.quantities.${index}.price`)}
                                                type={'number'}
                                                min={ticket.price}
                                                step={0.01}
                                                placeholder={'0.00'}
                                                label={t`Amount`}
                                                required={true}
                                                w={150}
                                                mb={0}
                                                classNames={{
                                                    input: 'hi-donation-input',
                                                }}
                                            />
                                        </div>
                                    )}
                                    {ticket.type !== 'DONATION' && (
                                        <TicketPriceDisplay
                                            price={price}
                                            ticket={ticket}
                                            currency={event?.currency}
                                            className={'hi-price-tier-price-amount'}
                                            freeLabel={t`Free`}
                                            taxAndServiceFeeDisplayType={event?.settings?.price_display_mode}
                                        />
                                    )}
                                </div>
                            </div>
                            <div className={'hi-ticket-quantity-selector'}>
                                {(ticket.is_available && price.is_available) && (
                                    <>
                                        <NumberSelector
                                            className={'hi-ticket-quantity-selector'}
                                            min={(ticket.type !== TicketType.Tiered ? ticket.min_per_order : 0) || 0}
                                            max={(ticket.type !== TicketType.Tiered ? ticket.max_per_order : 100) || 100}
                                            fieldName={`tickets.${ticketIndex}.quantities.${index}.quantity`}
                                            formInstance={form}
                                        />
                                        {form.errors[`tickets.${ticketIndex}.quantities.${index}.quantity`] && (
                                            <div className={'hi-ticket-quantity-error'}>
                                                {form.errors[`tickets.${ticketIndex}.quantities.${index}.quantity`]}
                                            </div>
                                        )}
                                    </>
                                )}
                                {(!ticket.is_available || !price.is_available) && (
                                    <TicketPriceAvailability ticket={ticket} price={price} event={event}/>
                                )}
                            </div>
                        </Group>

                        {price.is_discounted && (
                            <div style={{textDecoration: 'line-through', fontSize: '.9em'}}>
                                <Currency
                                    price={price.price_before_discount}
                                    currency={event?.currency}
                                    className={'hi-price-tier-price-amount'}
                                />
                            </div>
                        )}
                    </div>
                );
            })}
        </>
    );
}