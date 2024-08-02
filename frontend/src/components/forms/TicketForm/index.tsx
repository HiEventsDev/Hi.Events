import {t, Trans} from "@lingui/macro";
import {UseFormReturnType} from "@mantine/form";
import {TaxAndFee, TaxAndFeeCalculationType, TaxAndFeeType, Ticket, TicketType} from "../../../types.ts";
import {
    ActionIcon,
    Alert,
    Anchor,
    Button,
    Collapse,
    ComboboxItem,
    Group,
    MultiSelect,
    NumberInput,
    Switch,
    TextInput
} from "@mantine/core";
import {
    IconCash,
    IconCoinOff,
    IconCoins,
    IconCurrencyDollar,
    IconHeartDollar,
    IconInfoCircle,
    IconTrash,
    IconTrashOff,
} from "@tabler/icons-react";
import {useDisclosure} from "@mantine/hooks";
import {NavLink, useParams} from "react-router-dom";
import {useEffect} from "react";
import {CustomSelect, ItemProps} from "../../common/CustomSelect";
import {formatCurrency, getCurrencySymbol} from "../../../utilites/currency.ts";
import {useGetEvent} from "../../../queries/useGetEvent.ts";
import {useGetTaxesAndFees} from "../../../queries/useGetTaxesAndFees.ts";
import {Card} from "../../common/Card";
import classes from './TicketForm.module.scss';
import {Fieldset} from "../../common/Fieldset";
import {Editor} from "../../common/Editor";
import {InputGroup} from "../../common/InputGroup";
import {showError} from "../../../utilites/notifications.tsx";
import classNames from "classnames";
import {InputLabelWithHelp} from "../../common/InputLabelWithHelp";

interface TicketFormProps {
    form: UseFormReturnType<Ticket>,
    ticket?: Ticket,
}

const TicketPriceTierForm = ({form, ticket}: TicketFormProps) => {
    return form?.values?.prices?.map((price, index) => {
        const existingPrice = ticket?.prices?.find((p) => Number(p.id) === Number(price.id));
        const deleteDisabled = form?.values?.prices?.length === 1 || (existingPrice && Number(existingPrice?.quantity_sold) > 0);
        const cannotDeleteTitle = (() => {
            if (existingPrice && Number(existingPrice?.quantity_sold) > 0) {
                return t`You cannot delete this price tier because there are already tickets sold for this tier. You can hide it instead.`
            }
            if (form?.values?.prices?.length === 1) {
                return t`You must have at least one price tier`
            }
            return '';
        })();

        return (
            <Card key={`price-${index}`} className={classes.priceTierCard}>
                <h3>{price.label || <Trans>Tier {index + 1}</Trans>}</h3>
                <InputGroup>
                    <NumberInput decimalScale={2}
                                 min={0}
                                 fixedDecimalScale
                                 leftSection={<IconCurrencyDollar/>}
                                 {...form.getInputProps(`prices.${index}.price`)}
                                 label={t`Price`}
                                 placeholder="19.99"/>
                    <TextInput
                        {...form.getInputProps(`prices.${index}.label`)}
                        label={t`Label`}
                        placeholder={t`Early bird`}
                        required
                    />
                </InputGroup>
                <NumberInput
                    placeholder={t`Unlimited`}
                    {...form.getInputProps(`prices.${index}.initial_quantity_available`)}
                    label={t`Quantity Available`}
                />
                <InputGroup>
                    <TextInput
                        type={'datetime-local'}
                        {...form.getInputProps(`prices.${index}.sale_start_date`)}
                        label={t`Sale Start Date`}
                    />
                    <TextInput
                        type={'datetime-local'}
                        {...form.getInputProps(`prices.${index}.sale_end_date`)}
                        label={t`Sale End Date`}
                    />
                </InputGroup>

                <Switch
                    description={t`Hiding a ticket will prevent users from seeing it on the event page.`}
                    {...form.getInputProps(`prices.${index}.is_hidden`, {type: 'checkbox'})}
                    label={t`Hide this tier from users`}
                />

                <ActionIcon
                    variant={'light'}
                    className={classNames([classes.removeTier, deleteDisabled && classes.disabled])}
                    title={cannotDeleteTitle}
                    onClick={() => {
                        if (deleteDisabled) {
                            showError(cannotDeleteTitle);
                            return;
                        }
                        form.removeListItem('prices', index)
                    }}
                >
                    {!deleteDisabled && <IconTrash size="1rem"/>}
                    {deleteDisabled && <IconTrashOff size="1rem"/>}
                </ActionIcon>
            </Card>
        );
    })
}

export const TicketForm = ({form, ticket}: TicketFormProps) => {
    const ticketOptions: ItemProps[] = [
        {
            icon: <IconCash/>,
            label: t`Paid Ticket`,
            value: 'PAID',
            description: t`Standard ticket with a fixed price`,
        },
        {
            icon: <IconCoinOff/>,
            label: t`Free Ticket`,
            value: 'FREE',
            description: t`Free ticket, no payment information required`,
        },
        {
            icon: <IconHeartDollar/>,
            label: t`Donation / Pay what you'd like ticket`,
            value: 'DONATION',
            description: t`Set a minimum price and let users pay more if they choose`,
        },
        {
            icon: <IconCoins/>,
            label: t`Tiered Ticket`,
            value: 'TIERED',
            description: t`Multiple price options. Perfect for early bird tickets etc.`,
        },
    ];

    const {eventId} = useParams();
    const [opened, {toggle}] = useDisclosure(false);
    const isFreeTicket = form.values.type === 'FREE';
    const isDonationTicket = form.values.type === 'DONATION';
    const {data: event} = useGetEvent(eventId);
    const {data: taxesAndFees} = useGetTaxesAndFees();

    const taxAndFeeOptions = (type: TaxAndFeeType): ComboboxItem[] => {
        return taxesAndFees?.data
            ?.filter((item) => item.type === type)
            .map((item: TaxAndFee) => ({
                label: item.name + ' - ' + (item.calculation_type === TaxAndFeeCalculationType.Percentage
                    ? item.rate + '%'
                    : formatCurrency(Number(item.rate), event?.currency || 'USD')),
                value: String(item.id),
            })) || [];
    }

    useEffect(() => {
        if (form.values.type === TicketType.Free) {
            form.setFieldValue('price', 0.00);
        }
    }, [form, form.values.type]);

    const removeTaxesAndFees = () => {
        form.setFieldValue('tax_and_fee_ids', []);
    };

    return (
        <>
            <div>
                {Number(ticket?.quantity_sold) > 0 && (
                    <Alert icon={<IconInfoCircle/>} mb={20} color={'blue'}>
                        {t`You cannot change the ticket type as there are attendees associated with this ticket.`}
                    </Alert>
                )}
                <CustomSelect
                    disabled={Number(ticket?.quantity_sold) > 0}
                    label={t`Ticket Type`}
                    required
                    form={form}
                    name={'type'}
                    optionList={ticketOptions}
                />
                {form.errors.type && (
                    <Alert title={t`Ticket Type`} mb={20} color={'red'}>
                        {form.errors.type}
                    </Alert>
                )}

                {form.values.type === TicketType.Tiered && (
                    <Alert title={t`What are Tiered Tickets?`} mb={20}>
                        {t`Tiered tickets allow you to offer multiple price options for the same ticket.
                         This is perfect for early bird tickets, or offering different price
                          options for different groups of people.`}
                    </Alert>
                )}

                <TextInput mt={20}
                           {...form.getInputProps('title')}
                           label={t`Name`}
                           placeholder={t`VIP Ticket`}
                           required/>

                <Editor
                    label={t`Description`}
                    value={form.values.description || ''}
                    onChange={(value) => form.setFieldValue('description', value)}
                />

                {form.values.type !== TicketType.Tiered && (
                    <InputGroup>
                        <NumberInput decimalScale={2}
                                     min={0}
                                     fixedDecimalScale
                                     disabled={isFreeTicket}
                                     leftSection={event?.currency ? getCurrencySymbol(event.currency) : ''}
                                     {...form.getInputProps('prices.0.price')}
                                     label={<InputLabelWithHelp
                                         label={isDonationTicket ? t`Minimum Price` : t`Price`}
                                         helpText={(
                                             <Trans>
                                                 <p>
                                                     Please enter the price excluding taxes and fees.
                                                 </p>
                                                 <p>
                                                     Taxes and fees can be added below.
                                                 </p>
                                             </Trans>
                                         )}
                                     />}
                                     placeholder="19.99"/>
                        <NumberInput min={0}
                                     placeholder={t`Unlimited`}
                                     {...form.getInputProps('prices.0.initial_quantity_available')}
                                     label={<InputLabelWithHelp
                                         label={t`Quantity Available`}
                                         helpText={(
                                             <Trans>
                                                 <p>
                                                     The number of tickets available for this ticket
                                                 </p>
                                                 <p>
                                                     This value can be overridden if there are <a target={'__blank'}
                                                                                                  href={'capacity-assignments'}>Capacity
                                                     Limits</a> associated with this ticket.
                                                 </p>
                                             </Trans>
                                         )}
                                     />}
                        />
                    </InputGroup>
                )}
            </div>

            {form.values.type === TicketType.Tiered && (
                <Fieldset legend={t`Price tiers`} mt={20} mb={20}>
                    <TicketPriceTierForm ticket={ticket} form={form}/>
                    <Group>
                        <Button
                            size={'xs'}
                            variant={'light'}
                            onClick={() =>
                                form.insertListItem('prices', {
                                    price: 0,
                                    label: undefined,
                                    sale_end_date: undefined,
                                    sale_start_date: undefined
                                })
                            }
                        >
                            {t`Add tier`}
                        </Button>
                    </Group>
                </Fieldset>
            )}

            <Anchor display={'block'} ml={5} mb={20} variant={'subtle'} onClick={toggle}>
                {opened ? t`Hide` : t`Show`} {t`Additional Options`}
            </Anchor>

            <Collapse in={opened}>
                <Fieldset legend={t`Additional Options`} mb={20}>
                    <MultiSelect
                        {...form.getInputProps('tax_and_fee_ids')}
                        label={t`Taxes and Fees`}
                        placeholder={t`Select...`}
                        description={(
                            <span>
                        {t`The taxes and fees to apply to this ticket. You can create new taxes and fees on the`}{'  '}
                                <NavLink
                                    target={'_blank'}
                                    to={'/account/taxes-and-fees'}>{t`Taxes and Fees`}</NavLink> {t`page.`}
                                {' '}
                    </span>
                        )}
                        data={[{
                            group: t`Taxes`,
                            items: taxAndFeeOptions(TaxAndFeeType.Tax),
                        }, {
                            group: t`Fees`,
                            items: taxAndFeeOptions(TaxAndFeeType.Fee),
                        }]}
                    />

                    {(form.values.type === TicketType.Free && !!form.values.tax_and_fee_ids?.length) && (
                        <Alert mb={20}>
                            <p>
                                {t`You have taxes and fees added to a Free Ticket. Would you like to remove or obscure them?`}
                            </p>
                            <Button onClick={removeTaxesAndFees} size={'xs'}>{t`Yes, remove them`}</Button>
                        </Alert>
                    )}

                    <InputGroup>
                        <NumberInput {...form.getInputProps('min_per_order')} label={t`Minimum Per Order`}
                                     placeholder="5"/>
                        <NumberInput {...form.getInputProps('max_per_order')} label={t`Maximum Per Order`}
                                     placeholder="5"/>
                    </InputGroup>

                    <InputGroup>
                        <TextInput type={'datetime-local'} {...form.getInputProps('sale_start_date')}
                                   label={t`Sale Start Date`}/>
                        <TextInput type={'datetime-local'} {...form.getInputProps('sale_end_date')}
                                   label={t`Sale End Date`}/>
                    </InputGroup>
                    <h3>
                        {t`Visibility`}
                    </h3>
                    <Switch mt={15} {...form.getInputProps('hide_before_sale_start_date', {type: 'checkbox'})}
                            label={t`Hide ticket before sale start date`}/>
                    <Switch mt={20} {...form.getInputProps('hide_after_sale_end_date', {type: 'checkbox'})}
                            label={t`Hide ticket after sale end date`}/>
                    <Switch mt={20} {...form.getInputProps('show_quantity_remaining', {type: 'checkbox'})}
                            label={t`Show available ticket quantity`}/>
                    <Switch mt={20} {...form.getInputProps('hide_when_sold_out', {type: 'checkbox'})}
                            label={t`Hide ticket when sold out`}/>
                    <Switch
                        description={<>{t`You can create a promo code which targets this ticket on the`} <NavLink
                            target={'_blank'}
                            to={'../promo-codes'}>{t`Promo Code page`}</NavLink></>}
                        mt={20}
                        {...form.getInputProps('is_hidden_without_promo_code', {type: 'checkbox'})}
                        label={t`Hide ticket unless user has applicable promo code`}
                    />
                    <Switch
                        description={t`This overrides all visibility settings and will hide the ticket from all customers.`}
                        {...form.getInputProps(`is_hidden`, {type: 'checkbox'})}
                        label={t`Hide this ticket from customers`}
                    />
                </Fieldset>
            </Collapse>
        </>
    );
};
