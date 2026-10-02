import {Select, Switch, Textarea, TextInput} from "@mantine/core";
import {t, Trans} from "@lingui/macro";
import {UseFormReturnType} from "@mantine/form";
import {
    BoxOfficeRequest,
    CheckInList,
    EventOccurrence,
    EventType,
    ProductCategory,
    ProductType,
} from "../../../../../types.ts";
import {InputGroup} from "../../../../../components/common/InputGroup";
import {ProductSelector} from "../../../../../components/common/ProductSelector";
import {Callout} from "../../../../../components/common/Callout";
import {AdvancedOptions} from "../../../../../components/common/AdvancedOptions";
import {useEffect, useMemo, useState} from "react";
import {formatDateWithLocale} from "../../../../../utilites/dates.ts";

interface BoxOfficeFormProps {
    form: UseFormReturnType<BoxOfficeRequest>;
    productCategories: ProductCategory[];
    checkInLists: CheckInList[];
    eventType?: EventType;
    occurrences?: EventOccurrence[];
    timezone?: string;
    hideIntro?: boolean;
}

const hasAdvancedValuesSet = (form: UseFormReturnType<BoxOfficeRequest>): boolean => {
    return !!(form.values.description || form.values.activates_at || form.values.expires_at);
};

export const BoxOfficeForm = ({
                                  form,
                                  productCategories,
                                  checkInLists,
                                  eventType,
                                  occurrences,
                                  timezone,
                                  hideIntro,
                              }: BoxOfficeFormProps) => {
    const isRecurring = eventType === EventType.RECURRING;

    const occurrenceOptions = useMemo(() => {
        if (!isRecurring || !occurrences || !timezone) return [];
        return occurrences
            .filter(o => o.status !== 'CANCELLED')
            .map(o => ({
                value: String(o.id),
                label: formatDateWithLocale(o.start_date, 'shortDate', timezone)
                    + ' ' + formatDateWithLocale(o.start_date, 'timeOnly', timezone)
                    + (o.label ? ` — ${o.label}` : ''),
            }));
    }, [isRecurring, occurrences, timezone]);

    const checkInListOptions = useMemo(() => {
        const selectedOccurrenceId = form.values.event_occurrence_id ?? null;
        return checkInLists
            .filter(list => !list.event_occurrence_id || list.event_occurrence_id === selectedOccurrenceId)
            .map(list => ({
                value: String(list.id),
                label: list.is_system_default ? t`${list.name} (default)` : list.name,
            }));
    }, [checkInLists, form.values.event_occurrence_id]);

    const [showAdvanced, setShowAdvanced] = useState(() => hasAdvancedValuesSet(form));

    const [sellAll, setSellAll] = useState(
        () => !form.values.product_ids || form.values.product_ids.length === 0,
    );

    useEffect(() => {
        const hasProducts = (form.values.product_ids?.length ?? 0) > 0;
        if (hasProducts && sellAll) setSellAll(false);
    }, [form.values.product_ids]);

    return (
        <>
            {!hideIntro && (
                <Callout variant="info" title={t`Sell at the door`}>
                    <Trans>
                        Split door sales by entrance, date, or ticket type. You'll get a link and a PIN to share with staff — no account needed on their end.
                    </Trans>
                </Callout>
            )}

            <TextInput
                {...form.getInputProps('name')}
                required
                label={t`Name`}
                placeholder={t`Main entrance`}
            />

            <Switch
                mt="sm"
                label={t`Sell all products`}
                description={t`Leave on to sell every ticket and product on the event. Turn off to pick specific ones.`}
                checked={sellAll}
                onChange={(e) => {
                    const checked = e.currentTarget.checked;
                    setSellAll(checked);
                    if (checked) {
                        form.setFieldValue('product_ids', []);
                    }
                }}
            />

            {!sellAll && (
                <ProductSelector
                    label={t`Which products can be sold at this box office?`}
                    placeholder={t`Select products`}
                    productCategories={productCategories}
                    form={form}
                    productFieldName="product_ids"
                    includedProductTypes={[ProductType.Ticket, ProductType.General]}
                />
            )}

            {isRecurring && occurrenceOptions.length > 0 && (
                <Select
                    label={t`Occurrence`}
                    description={t`Leave empty to let staff pick the date when they start selling`}
                    placeholder={t`Any occurrence`}
                    data={occurrenceOptions}
                    value={form.values.event_occurrence_id ? String(form.values.event_occurrence_id) : null}
                    onChange={(val) => form.setFieldValue('event_occurrence_id', val ? Number(val) : null)}
                    clearable
                />
            )}

            <Select
                mt="sm"
                label={t`Check-in list`}
                description={t`Used for the Scan tab and for checking attendees in after a sale`}
                placeholder={t`Default check-in list`}
                data={checkInListOptions}
                value={form.values.check_in_list_id ? String(form.values.check_in_list_id) : null}
                onChange={(val) => form.setFieldValue('check_in_list_id', val ? Number(val) : null)}
                clearable
            />

            <Switch
                mt="md"
                label={t`Allow discounts`}
                description={t`Staff can apply a manual discount to a sale`}
                {...form.getInputProps('allow_discounts', {type: 'checkbox'})}
            />

            <Switch
                mt="sm"
                label={t`Allow price override`}
                description={t`Staff can change the price of an item during a sale`}
                {...form.getInputProps('allow_price_override', {type: 'checkbox'})}
            />

            <Switch
                mt="sm"
                label={t`Collect checkout questions`}
                description={t`Ask order and attendee questions at the door. Every ticket gets its own step, which can greatly slow down sales. Leave off for busy doors.`}
                {...form.getInputProps('collect_order_questions', {type: 'checkbox'})}
            />

            <AdvancedOptions opened={showAdvanced} onToggle={() => setShowAdvanced(v => !v)}>
                <Textarea
                    {...form.getInputProps('description')}
                    label={t`Description for staff`}
                    placeholder={t`Add a description for this box office`}
                    description={t`Shown to staff when they open the box office.`}
                    minRows={3}
                    autosize
                />

                <InputGroup>
                    <TextInput
                        {...form.getInputProps('activates_at')}
                        type="datetime-local"
                        label={t`Activation date`}
                        description={t`When sales open`}
                    />
                    <TextInput
                        {...form.getInputProps('expires_at')}
                        type="datetime-local"
                        label={t`Expiration date`}
                        description={t`When sales close`}
                    />
                </InputGroup>
            </AdvancedOptions>
        </>
    );
}
