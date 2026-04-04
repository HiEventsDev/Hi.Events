import {Select, Textarea, TextInput} from "@mantine/core";
import {t} from "@lingui/macro";
import {UseFormReturnType} from "@mantine/form";
import {
    CheckInListRequest,
    EventOccurrence,
    EventType,
    ProductCategory,
    ProductType
} from "../../../types.ts";
import {InputGroup} from "../../common/InputGroup";
import {ProductSelector} from "../../common/ProductSelector";
import {ModalIntro} from "../../common/ModalIntro";
import {IconClipboardList} from "@tabler/icons-react";
import {useEffect, useMemo} from "react";
import {formatDateWithLocale} from "../../../utilites/dates.ts";

interface CheckInListFormProps {
    form: UseFormReturnType<CheckInListRequest>;
    productCategories: ProductCategory[];
    eventType?: EventType;
    occurrences?: EventOccurrence[];
    timezone?: string;
    isNewForOccurrence?: boolean;
    hideIntro?: boolean;
}

export const CheckInListForm = ({form, productCategories, eventType, occurrences, timezone, isNewForOccurrence, hideIntro}: CheckInListFormProps) => {
    const tickets = useMemo(() => {
        return productCategories
            .flatMap(category => category.products || [])
            .filter(product => product.product_type === ProductType.Ticket);
    }, [productCategories]);

    const isRecurring = eventType === EventType.RECURRING;
    const activeOccurrences = useMemo(() => {
        if (!isRecurring || !occurrences || !timezone) return [];
        return occurrences.filter(o => o.status !== 'CANCELLED');
    }, [isRecurring, occurrences, timezone]);

    const occurrenceOptions = useMemo(() => {
        if (!activeOccurrences.length || !timezone) return [];
        return activeOccurrences.map(o => ({
            value: String(o.id),
            label: formatDateWithLocale(o.start_date, 'shortDate', timezone)
                + ' ' + formatDateWithLocale(o.start_date, 'timeOnly', timezone)
                + (o.label ? ` — ${o.label}` : ''),
        }));
    }, [activeOccurrences, timezone]);

    useEffect(() => {
        if (tickets.length === 1 && (!form.values.product_ids || form.values.product_ids.length === 0)) {
            form.setFieldValue('product_ids', [String(tickets[0].id)]);
        }
    }, [tickets]);

    return (
        <>
            {!hideIntro && (
                <ModalIntro
                    icon={<IconClipboardList size={26}/>}
                    title={isNewForOccurrence
                        ? t`Create a check-in list for this date`
                        : t`Create a check-in list`
                    }
                    subtitle={t`Control entry by day, area, or ticket type. Share a secure link with staff — no account needed.`}
                />
            )}

            <TextInput
                {...form.getInputProps('name')}
                required
                label={t`Name`}
                placeholder={t`VIP check-in list`}
            />

            <ProductSelector
                label={t`Which tickets should be associated with this check-in list?`}
                placeholder={t`Select tickets`}
                productCategories={productCategories}
                form={form}
                productFieldName="product_ids"
                includedProductTypes={[ProductType.Ticket]}
            />

            {isRecurring && occurrenceOptions.length > 0 && (
                <Select
                    label={t`Occurrence`}
                    description={t`Leave empty to apply this check-in list to all occurrences`}
                    placeholder={t`All occurrences`}
                    data={occurrenceOptions}
                    value={form.values.event_occurrence_id ? String(form.values.event_occurrence_id) : null}
                    onChange={(val) => form.setFieldValue('event_occurrence_id', val ? Number(val) : null)}
                    clearable
                />
            )}

            <Textarea
                {...form.getInputProps('description')}
                label={t`Description for check-in staff`}
                placeholder={t`Add a description for this check-in list`}
                description={t`Visible to check-in staff only. Helps identify this list during check-in.`}
                minRows={2}
            />

            <InputGroup>
                <TextInput
                    {...form.getInputProps('activates_at')}
                    type="datetime-local"
                    label={t`Activation date`}
                    description={t`When check-in opens`}
                />
                <TextInput
                    {...form.getInputProps('expires_at')}
                    type="datetime-local"
                    label={t`Expiration date`}
                    description={t`When check-in closes`}
                />
            </InputGroup>
        </>
    );
}
