import {Select, Switch, Text, Textarea, TextInput} from "@mantine/core";
import {t, Trans} from "@lingui/macro";
import {UseFormReturnType} from "@mantine/form";
import {useParams} from "react-router";
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
import {Ledger, LedgerRow, RowSummary} from "../../common/Ledger";
import {useEffect, useMemo, useState} from "react";
import {IconCalendarTime, IconDeviceTablet, IconEye, IconNotes, IconPrinter} from "@tabler/icons-react";
import {formatDateWithLocale} from "../../../utilites/dates.ts";
import {formatLocalDateTime} from "../ProductForm/ledgerSummaries.ts";
import {useEventFeature} from "../../../hooks/useEventFeature.ts";
import {DateTimeField} from "../../common/DateTimeField";
import classes from "./CheckInListForm.module.scss";

type CheckInListLedgerRow = 'check-in-window' | 'staff-note' | 'staff-visibility' | 'kiosk' | 'badges';

const FIELD_TO_ROW: Record<string, CheckInListLedgerRow> = {
    activates_at: 'check-in-window',
    expires_at: 'check-in-window',
    description: 'staff-note',
};

interface CheckInListFormProps {
    form: UseFormReturnType<CheckInListRequest>;
    productCategories: ProductCategory[];
    eventType?: EventType;
    occurrences?: EventOccurrence[];
    timezone?: string;
    isNewForOccurrence?: boolean;
    hideIntro?: boolean;
}

const onOffSummary = (enabled: boolean): RowSummary => ({text: enabled ? t`On` : t`Off`, emphasized: enabled});

const checkInWindowSummary = (values: CheckInListRequest): RowSummary => {
    const opens = values.activates_at ? formatLocalDateTime(values.activates_at) : null;
    const closes = values.expires_at ? formatLocalDateTime(values.expires_at) : null;

    if (opens && closes) {
        return {text: `${opens} – ${closes}`, emphasized: true};
    }
    if (opens) {
        return {text: t`From ${opens}`, emphasized: true};
    }
    if (closes) {
        return {text: t`Until ${closes}`, emphasized: true};
    }
    return {text: t`Always open`, emphasized: false};
};

const staffNoteSummary = (values: CheckInListRequest): RowSummary => {
    const note = (values.description ?? '').trim();
    return note ? {text: note, emphasized: false} : {text: t`None yet`, emphasized: false};
};

const staffVisibilitySummary = (values: CheckInListRequest): RowSummary => {
    const shown = [
        values.public_show_attendee_notes,
        values.public_show_question_answers,
        values.public_show_order_details,
    ].filter(Boolean).length;

    if (shown === 3) {
        return {text: t`Everything`, emphasized: false};
    }
    if (shown === 0) {
        return {text: t`Names and tickets only`, emphasized: true};
    }
    return {text: t`Some details hidden`, emphasized: true};
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
    const {eventId} = useParams();
    const badgesEnabled = useEventFeature(eventId, 'BADGES');
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

    const [openRows, setOpenRows] = useState<Set<CheckInListLedgerRow>>(new Set());

    const [scopeToAll, setScopeToAll] = useState(
        () => !form.values.product_ids || form.values.product_ids.length === 0,
    );

    useEffect(() => {
        const hasProducts = (form.values.product_ids?.length ?? 0) > 0;
        if (hasProducts && scopeToAll) setScopeToAll(false);
    }, [form.values.product_ids]);

    useEffect(() => {
        const errorRows = Object.keys(form.errors)
            .map((field) => FIELD_TO_ROW[field])
            .filter((row): row is CheckInListLedgerRow => !!row);

        if (errorRows.length > 0) {
            setOpenRows((previous) => new Set([...previous, ...errorRows]));
        }
    }, [form.errors]);

    const toggleRow = (row: CheckInListLedgerRow) => {
        setOpenRows((previous) => {
            const next = new Set(previous);
            if (next.has(row)) {
                next.delete(row);
            } else {
                next.add(row);
            }
            return next;
        });
    };

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

            <Switch
                mt="sm"
                label={t`Apply to all tickets`}
                description={t`Leave on to cover every ticket on the event. Turn off to pick specific tickets.`}
                checked={scopeToAll}
                onChange={(e) => {
                    const checked = e.currentTarget.checked;
                    setScopeToAll(checked);
                    if (checked) {
                        form.setFieldValue('product_ids', []);
                    }
                }}
            />

            {!scopeToAll && (
                <ProductSelector
                    label={t`Which tickets should be associated with this check-in list?`}
                    placeholder={t`Select tickets`}
                    productCategories={productCategories}
                    form={form}
                    productFieldName="product_ids"
                    includedProductTypes={[ProductType.Ticket]}
                />
            )}

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

            <div className={classes.ledger}>
                <Ledger heading={t`Everything else — tap to edit`}>
                    <LedgerRow
                        idPrefix="check-in-list-ledger"
                        id="check-in-window"
                        icon={<IconCalendarTime size={16}/>}
                        label={t`When check-in is open`}
                        summary={checkInWindowSummary(form.values)}
                        opened={openRows.has('check-in-window')}
                        onToggle={toggleRow}
                    >
                        <InputGroup>
                            <DateTimeField
                                {...form.getInputProps('activates_at')}
                                label={t`Activation date`}
                                description={t`When check-in opens`}
                            />
                            <DateTimeField
                                {...form.getInputProps('expires_at')}
                                label={t`Expiration date`}
                                description={t`When check-in closes`}
                                defaultTime="23:59"
                            />
                        </InputGroup>
                    </LedgerRow>

                    <LedgerRow
                        idPrefix="check-in-list-ledger"
                        id="staff-note"
                        icon={<IconNotes size={16}/>}
                        label={t`Note for staff`}
                        summary={staffNoteSummary(form.values)}
                        opened={openRows.has('staff-note')}
                        onToggle={toggleRow}
                    >
                        <Textarea
                            {...form.getInputProps('description')}
                            label={t`Description for check-in staff`}
                            placeholder={t`Add a description for this check-in list`}
                            description={t`Shown to staff the first time they open the check-in page.`}
                            minRows={3}
                            autosize
                        />
                    </LedgerRow>

                    <LedgerRow
                        idPrefix="check-in-list-ledger"
                        id="staff-visibility"
                        icon={<IconEye size={16}/>}
                        label={t`What staff can see`}
                        summary={staffVisibilitySummary(form.values)}
                        opened={openRows.has('staff-visibility')}
                        onToggle={toggleRow}
                    >
                        <Text size="xs" c="dimmed" mb="md">
                            <Trans>
                                Applies to anyone opening the shared check-in link without being signed in. Logged-in team members always see everything.
                            </Trans>
                        </Text>
                        <div className={classes.switchStack}>
                            <Switch
                                {...form.getInputProps('public_show_attendee_notes', {type: 'checkbox'})}
                                label={t`Attendee notes`}
                                description={t`Internal notes on the attendee's ticket`}
                            />
                            <Switch
                                {...form.getInputProps('public_show_question_answers', {type: 'checkbox'})}
                                label={t`Question answers`}
                                description={t`Answers provided at checkout (e.g. meal choice)`}
                            />
                            <Switch
                                {...form.getInputProps('public_show_order_details', {type: 'checkbox'})}
                                label={t`Order details`}
                                description={t`Order number, purchase date, purchaser email`}
                            />
                        </div>
                    </LedgerRow>

                    <LedgerRow
                        idPrefix="check-in-list-ledger"
                        id="kiosk"
                        icon={<IconDeviceTablet size={16}/>}
                        label={t`Self check-in kiosk`}
                        summary={onOffSummary(!!form.values.kiosk_mode)}
                        opened={openRows.has('kiosk')}
                        onToggle={toggleRow}
                    >
                        <Switch
                            {...form.getInputProps('kiosk_mode', {type: 'checkbox'})}
                            label={t`Use this list as a self check-in kiosk`}
                            description={t`A full-screen scanner where attendees check themselves in. Attendee lists and search are hidden.`}
                            data-testid="check-in-list-kiosk-switch"
                        />
                    </LedgerRow>

                    {badgesEnabled && (
                        <LedgerRow
                            idPrefix="check-in-list-ledger"
                            id="badges"
                            icon={<IconPrinter size={16}/>}
                            label={t`Badge printing`}
                            summary={onOffSummary(!!form.values.badge_printing_enabled)}
                            opened={openRows.has('badges')}
                            onToggle={toggleRow}
                        >
                            <Switch
                                {...form.getInputProps('badge_printing_enabled', {type: 'checkbox'})}
                                label={t`Print badges from this list`}
                                description={t`Staff can print or reprint badges from this list, or print them automatically on check-in`}
                                data-testid="check-in-list-badge-printing-switch"
                            />
                        </LedgerRow>
                    )}
                </Ledger>
            </div>
        </>
    );
}
