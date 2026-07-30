import {t, Trans} from "@lingui/macro";
import {UseFormReturnType} from "@mantine/form";
import {Event, EventType, Product} from "../../../types.ts";
import {ActionIcon, NumberInput, Switch, TextInput} from "@mantine/core";
import {IconTrash, IconTrashOff} from "@tabler/icons-react";
import {Callout} from "../../common/Callout";
import {NavLink} from "react-router";
import {getCurrencySymbol} from "../../../utilites/currency.ts";
import {Card} from "../../common/Card";
import classes from './ProductForm.module.scss';
import {InputGroup} from "../../common/InputGroup";
import {showError} from "../../../utilites/notifications.tsx";
import classNames from "classnames";

interface ProductPriceTierFormProps {
    form: UseFormReturnType<Product>,
    product?: Product,
    event?: Event,
}

export const hasQuantityValue = (value: unknown): boolean =>
    value !== undefined && value !== null && value !== '';

export const SeriesQuantityWarning = ({eventId}: { eventId?: string | number }) => (
    <Callout variant="warning" style={{marginBottom: 20}}>
        <Trans>
            This limits total sales across every date in your schedule combined — it is not a
            per-date limit. To limit attendance for each date, set a capacity on the <NavLink
            to={`/manage/event/${eventId}/occurrences`}>Occurrence Schedule page</NavLink>.
        </Trans>
    </Callout>
);

export const ProductPriceTierForm = ({form, product, event}: ProductPriceTierFormProps) => {
    const isRecurringTicket = event?.type === EventType.RECURRING && form.values.product_type === 'TICKET';

    return form?.values?.prices?.map((price, index) => {
        const existingPrice = product?.prices?.find((p) => Number(p.id) === Number(price.id));
        const deleteDisabled = form?.values?.prices?.length === 1 || (existingPrice && Number(existingPrice?.quantity_sold) > 0);
        const cannotDeleteTitle = (() => {
            if (existingPrice && Number(existingPrice?.quantity_sold) > 0) {
                return t`You cannot delete this price tier because there are already products sold for this tier. You can hide it instead.`
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
                                 leftSection={event?.currency ? getCurrencySymbol(event.currency) : ''}
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
                    label={isRecurringTicket ? t`Total Quantity Across All Dates` : t`Quantity Available`}
                />
                {!product && isRecurringTicket && hasQuantityValue(price.initial_quantity_available) && (
                    <SeriesQuantityWarning eventId={event?.id}/>
                )}
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
                    mt={10}
                    description={t`Hiding a product will prevent users from seeing it on the event page.`}
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
