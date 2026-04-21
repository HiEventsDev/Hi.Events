import {Collapse, Select, Switch, Textarea, TextInput} from "@mantine/core";
import {t, Trans} from "@lingui/macro";
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
import {Callout} from "../../common/Callout";
import {useEffect, useMemo, useState} from "react";
import {
    IconChevronRight,
    IconClipboardText,
    IconEye,
    IconMessageCircleQuestion,
    IconReceipt2,
} from "@tabler/icons-react";
import {formatDateWithLocale} from "../../../utilites/dates.ts";
import classes from "./CheckInListForm.module.scss";

interface CheckInListFormProps {
    form: UseFormReturnType<CheckInListRequest>;
    productCategories: ProductCategory[];
    eventType?: EventType;
    occurrences?: EventOccurrence[];
    timezone?: string;
    isNewForOccurrence?: boolean;
    hideIntro?: boolean;
}

const hasAdvancedValuesSet = (form: UseFormReturnType<CheckInListRequest>): boolean => {
    return !!(
        form.values.description
        || form.values.activates_at
        || form.values.expires_at
        || form.values.public_show_attendee_notes === false
        || form.values.public_show_question_answers === false
        || form.values.public_show_order_details === false
    );
};

export const CheckInListForm = ({
                                    form,
                                    productCategories,
                                    eventType,
                                    occurrences,
                                    timezone,
                                    isNewForOccurrence,
                                    hideIntro,
                                }: CheckInListFormProps) => {
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

    // Open advanced panel automatically if editing a list that already uses any of those options.
    const [showAdvanced, setShowAdvanced] = useState(() => hasAdvancedValuesSet(form));

    useEffect(() => {
        if (tickets.length === 1 && (!form.values.product_ids || form.values.product_ids.length === 0)) {
            form.setFieldValue('product_ids', [String(tickets[0].id)]);
        }
    }, [tickets]);

    const introTitle = isNewForOccurrence
        ? t`Control who gets in for this date`
        : t`Control who gets in, and when`;

    return (
        <>
            {!hideIntro && (
                <Callout variant="info" title={introTitle}>
                    <Trans>
                        Split check-in across days, areas, or ticket types. Share the link with staff — no account needed on their end.
                    </Trans>
                </Callout>
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

            <button
                type="button"
                className={classes.advancedToggle}
                onClick={() => setShowAdvanced(v => !v)}
                aria-expanded={showAdvanced}
            >
                <IconChevronRight
                    size={14}
                    className={`${classes.chevron} ${showAdvanced ? classes.chevronOpen : ""}`}
                />
                {showAdvanced ? t`Hide advanced options` : t`Show advanced options`}
            </button>

            <Collapse in={showAdvanced}>
                <Textarea
                    {...form.getInputProps('description')}
                    label={t`Description for check-in staff`}
                    placeholder={t`Add a description for this check-in list`}
                    description={t`Shown to staff the first time they open the check-in page.`}
                    minRows={3}
                    autosize
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

                <div className={classes.visibilitySection}>
                    <div className={classes.visibilityHeader}>
                        <div className={classes.visibilityIcon}>
                            <IconEye size={16}/>
                        </div>
                        <div>
                            <div className={classes.visibilityTitle}>
                                {t`What unauthenticated staff can see`}
                            </div>
                            <div className={classes.visibilityHint}>
                                <Trans>
                                    Applies to anyone opening the shared check-in link without being signed in. Logged-in team members always see everything.
                                </Trans>
                            </div>
                        </div>
                    </div>

                    <div className={classes.visibilityRows}>
                        <label className={classes.visibilityRow}>
                            <div className={classes.visibilityRowIcon}>
                                <IconClipboardText size={16}/>
                            </div>
                            <div className={classes.visibilityRowMain}>
                                <div className={classes.visibilityRowLabel}>{t`Attendee notes`}</div>
                                <div className={classes.visibilityRowDesc}>
                                    {t`Internal notes on the attendee's ticket`}
                                </div>
                            </div>
                            <Switch
                                size="md"
                                {...form.getInputProps('public_show_attendee_notes', {type: 'checkbox'})}
                                aria-label={t`Show attendee notes to non-logged-in staff`}
                            />
                        </label>

                        <label className={classes.visibilityRow}>
                            <div className={classes.visibilityRowIcon}>
                                <IconMessageCircleQuestion size={16}/>
                            </div>
                            <div className={classes.visibilityRowMain}>
                                <div className={classes.visibilityRowLabel}>{t`Question answers`}</div>
                                <div className={classes.visibilityRowDesc}>
                                    {t`Answers provided at checkout (e.g. meal choice)`}
                                </div>
                            </div>
                            <Switch
                                size="md"
                                {...form.getInputProps('public_show_question_answers', {type: 'checkbox'})}
                                aria-label={t`Show question answers to non-logged-in staff`}
                            />
                        </label>

                        <label className={classes.visibilityRow}>
                            <div className={classes.visibilityRowIcon}>
                                <IconReceipt2 size={16}/>
                            </div>
                            <div className={classes.visibilityRowMain}>
                                <div className={classes.visibilityRowLabel}>{t`Order details`}</div>
                                <div className={classes.visibilityRowDesc}>
                                    {t`Order number, purchase date, purchaser email`}
                                </div>
                            </div>
                            <Switch
                                size="md"
                                {...form.getInputProps('public_show_order_details', {type: 'checkbox'})}
                                aria-label={t`Show order details to non-logged-in staff`}
                            />
                        </label>
                    </div>
                </div>
            </Collapse>
        </>
    );
}
