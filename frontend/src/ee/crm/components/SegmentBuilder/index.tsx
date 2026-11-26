import {useState} from "react";
import {plural, t} from "@lingui/macro";
import {ActionIcon, Button, MultiSelect, NumberInput, SegmentedControl, Select} from "@mantine/core";
import {DateInput} from "@mantine/dates";
import {useDebouncedValue} from "@mantine/hooks";
import {IconPlus, IconX} from "@tabler/icons-react";
import dayjs from "dayjs";
import {useAccess} from "../../../../hooks/useAccess.ts";
import {IdParam} from "../../../../types.ts";
import {getDatePickerFormat} from "../../../../utilites/dates.ts";
import {useTextMessagesVisible} from "../../hooks/useTextMessagesVisible.ts";
import {useGetCrmSegmentCount} from "../../queries/useGetCrmSegmentCount.ts";
import {useGetCrmSegmentOptions} from "../../queries/useGetCrmSegmentOptions.ts";
import {useGetCrmTags} from "../../queries/useGetCrmTags.ts";
import {
    CrmSegmentDefinition,
    CrmSegmentFieldKey,
    CrmSegmentOperator,
    CrmSegmentRule,
} from "../../types.ts";
import {marketingStatusLabel, segmentFieldLabel, segmentOperatorLabel, sourceLabel} from "../../utilites/labels.ts";
import {EntitySearchSelect} from "./EntitySearchSelect.tsx";
import classes from "./SegmentBuilder.module.scss";

type FieldKind = 'number' | 'money' | 'date' | 'entity' | 'option' | 'flag';

const MAX_SEGMENT_RULES = 20;

const FIELD_KINDS: Record<CrmSegmentFieldKey, FieldKind> = {
    events_count: 'number',
    orders_count: 'number',
    tickets_count: 'number',
    attended_count: 'number',
    no_show_count: 'number',
    total_spent: 'money',
    last_order_at: 'date',
    last_attended_at: 'date',
    first_seen_at: 'date',
    ticket_event: 'entity',
    checked_in_event: 'entity',
    ticket_product: 'entity',
    ticket_location: 'entity',
    tag: 'entity',
    email_marketing_status: 'option',
    sms_marketing_status: 'option',
    source: 'option',
    used_promo_code: 'flag',
    waitlist_missed_out: 'flag',
    abandoned_checkout: 'flag',
    has_phone: 'flag',
};

const KIND_OPERATORS: Record<FieldKind, CrmSegmentOperator[]> = {
    number: ['gte', 'lte', 'eq'],
    money: ['gte', 'lte', 'eq'],
    date: ['within_days', 'not_within_days', 'before', 'after', 'is_empty'],
    entity: ['in', 'not_in'],
    option: ['in', 'not_in'],
    flag: ['is_true', 'is_false'],
};

const emptyValue = (kind: FieldKind): CrmSegmentRule['value'] => kind === 'entity' || kind === 'option' ? [] : null;

export const isRuleComplete = (rule: CrmSegmentRule): boolean => {
    const kind = FIELD_KINDS[rule.field];

    if (kind === 'entity' || kind === 'option') {
        return Array.isArray(rule.value) && rule.value.length > 0;
    }
    if (kind === 'flag' || rule.operator === 'is_empty') {
        return true;
    }
    if (kind === 'money') {
        return typeof rule.value === 'number' && !!rule.currency;
    }
    return rule.value !== null && rule.value !== '';
};

export const isDefinitionComplete = (definition: CrmSegmentDefinition): boolean =>
    definition.rules.length > 0 && definition.rules.every(isRuleComplete);

interface SegmentBuilderProps {
    organizerId: IdParam;
    value: CrmSegmentDefinition;
    onChange: (definition: CrmSegmentDefinition) => void;
    errors?: Record<string, string>;
    showCount?: boolean;
    startsChoosingRule?: boolean;
}

export const SegmentBuilder = ({organizerId, value, onChange, errors = {}, showCount = true, startsChoosingRule = false}: SegmentBuilderProps) => {
    const {can} = useAccess();
    const showTextMessages = useTextMessagesVisible();
    const [isChoosingRule, setIsChoosingRule] = useState(startsChoosingRule && value.rules.length === 0);
    const canViewFinancials = can('VIEW_FINANCIALS');
    const baseOptions = useGetCrmSegmentOptions(organizerId).data;
    const {data: tags} = useGetCrmTags(organizerId);
    const complete = isDefinitionComplete(value);
    const [debouncedDefinition] = useDebouncedValue(value, 400);
    const countQuery = useGetCrmSegmentCount(organizerId, {definition: debouncedDefinition}, showCount && complete && isDefinitionComplete(debouncedDefinition));

    const fieldGroups = [
        {group: t`How often`, fields: ['events_count', 'orders_count', 'tickets_count', 'attended_count', 'no_show_count']},
        {group: t`How recently`, fields: ['last_order_at', 'last_attended_at', 'first_seen_at']},
        {group: t`What they booked`, fields: ['ticket_event', 'checked_in_event', 'ticket_product', 'ticket_location']},
        {group: t`What happened`, fields: ['used_promo_code', 'waitlist_missed_out', 'abandoned_checkout', ...(canViewFinancials ? ['total_spent'] : [])]},
        {group: t`About them`, fields: ['tag', 'email_marketing_status', 'sms_marketing_status', 'has_phone', 'source']},
    ].map(group => ({
        group: group.group,
        items: (group.fields as CrmSegmentFieldKey[])
            .filter(field => field !== 'sms_marketing_status' || showTextMessages || value.rules.some(rule => rule.field === field))
            .map(field => ({value: field, label: segmentFieldLabel(field)})),
    }));

    const entityOptions = (field: CrmSegmentFieldKey) => {
        switch (field) {
            case 'ticket_location':
                return (baseOptions?.locations ?? []).map(location => ({value: String(location.id), label: location.name}));
            case 'tag':
                return (tags ?? []).map(tag => ({value: String(tag.id), label: tag.name}));
            case 'email_marketing_status':
                return (['SUBSCRIBED', 'NOT_SUBSCRIBED', 'UNSUBSCRIBED', 'BOUNCED', 'COMPLAINED'] as const)
                    .map(status => ({value: status, label: marketingStatusLabel(status)}));
            case 'sms_marketing_status':
                return (['SUBSCRIBED', 'NOT_SUBSCRIBED', 'UNSUBSCRIBED'] as const)
                    .map(status => ({value: status, label: marketingStatusLabel(status)}));
            default:
                return (['ORDER', 'ATTENDEE', 'WAITLIST', 'FORM', 'IMPORT', 'MANUAL'] as const)
                    .map(source => ({value: source, label: sourceLabel(source)}));
        }
    };

    const updateRule = (index: number, rule: CrmSegmentRule) => onChange({
        ...value,
        rules: value.rules.map((existing, ruleIndex) => ruleIndex === index ? rule : existing),
    });

    const ruleFor = (field: CrmSegmentFieldKey): CrmSegmentRule => {
        const kind = FIELD_KINDS[field];

        return {
            field,
            operator: KIND_OPERATORS[kind][0],
            value: emptyValue(kind),
            ...(kind === 'money' ? {currency: baseOptions?.currencies[0] ?? 'USD'} : {}),
        };
    };

    const addRule = (field: CrmSegmentFieldKey) => {
        setIsChoosingRule(false);
        onChange({...value, rules: [...value.rules, ruleFor(field)]});
    };

    const changeOperator = (index: number, operator: CrmSegmentOperator) => {
        const rule = value.rules[index];
        const kind = FIELD_KINDS[rule.field];
        const wasDays = rule.operator === 'within_days' || rule.operator === 'not_within_days';
        const isDays = operator === 'within_days' || operator === 'not_within_days';
        const keepValue = kind !== 'date' || (wasDays && isDays)
            || (!wasDays && !isDays && rule.operator !== 'is_empty' && operator !== 'is_empty');

        updateRule(index, {...rule, operator, value: keepValue ? rule.value : emptyValue(kind)});
    };

    const renderValue = (rule: CrmSegmentRule, index: number) => {
        const kind = FIELD_KINDS[rule.field];

        if (kind === 'flag' || rule.operator === 'is_empty') {
            return null;
        }

        if (kind === 'number') {
            return (
                <NumberInput
                    aria-label={t`Value`}
                    value={(rule.value as number | null) ?? ''}
                    onChange={next => updateRule(index, {...rule, value: typeof next === 'number' ? next : null})}
                    min={0}
                    max={1000000}
                    allowDecimal={false}
                    allowNegative={false}
                />
            );
        }

        if (kind === 'money') {
            return (
                <>
                    <NumberInput
                        aria-label={t`Amount`}
                        value={(rule.value as number | null) ?? ''}
                        onChange={next => updateRule(index, {...rule, value: typeof next === 'number' ? next : null})}
                        min={0}
                        decimalScale={2}
                        allowNegative={false}
                    />
                    <Select
                        aria-label={t`Currency`}
                        w={96}
                        data={baseOptions?.currencies ?? (rule.currency ? [rule.currency] : [])}
                        value={rule.currency ?? null}
                        onChange={currency => updateRule(index, {...rule, currency: currency ?? undefined})}
                        allowDeselect={false}
                    />
                </>
            );
        }

        if (kind === 'date') {
            if (rule.operator === 'before' || rule.operator === 'after') {
                return (
                    <DateInput
                        aria-label={t`Date`}
                        value={typeof rule.value === 'string' ? rule.value : null}
                        onChange={next => updateRule(index, {...rule, value: next ? dayjs(next).format('YYYY-MM-DD') : null})}
                        valueFormat={getDatePickerFormat()}
                    />
                );
            }

            return (
                <>
                    <NumberInput
                        aria-label={t`Days`}
                        value={(rule.value as number | null) ?? ''}
                        onChange={next => updateRule(index, {...rule, value: typeof next === 'number' ? next : null})}
                        min={1}
                        max={3650}
                        allowDecimal={false}
                        allowNegative={false}
                    />
                    <span className={classes.suffix}>
                        {rule.operator === 'within_days' ? t`days` : t`days ago`}
                    </span>
                </>
            );
        }

        if (rule.field === 'ticket_event' || rule.field === 'checked_in_event' || rule.field === 'ticket_product') {
            return (
                <EntitySearchSelect
                    organizerId={organizerId}
                    entity={rule.field === 'ticket_product' ? 'product' : 'event'}
                    value={rule.value as number[]}
                    onChange={next => updateRule(index, {...rule, value: next})}
                />
            );
        }

        return (
            <MultiSelect
                aria-label={t`Options`}
                placeholder={(rule.value as unknown[]).length === 0 ? t`Choose…` : undefined}
                data={entityOptions(rule.field)}
                value={(rule.value as (string | number)[]).map(String)}
                onChange={next => updateRule(index, {
                    ...rule,
                    value: kind === 'entity' ? next.map(Number) : next,
                })}
                searchable
                maxValues={50}
                nothingFoundMessage={t`Nothing to choose from yet`}
            />
        );
    };

    return (
        <div className={classes.builder}>
            <div className={classes.match}>
                <span>{t`Include people who match`}</span>
                <SegmentedControl
                    size="xs"
                    value={value.match}
                    onChange={match => onChange({...value, match: match as CrmSegmentDefinition['match']})}
                    data={[{value: 'all', label: t`all rules`}, {value: 'any', label: t`any rule`}]}
                />
            </div>

            <div className={classes.rules}>
                {value.rules.map((rule, index) => {
                    const error = errors[`definition.rules.${index}`];

                    return (
                        <div key={index} className={classes.rule} data-invalid={error ? true : undefined}>
                            <Select
                                className={classes.field}
                                aria-label={t`Rule`}
                                data={fieldGroups}
                                value={rule.field}
                                onChange={field => field && updateRule(index, ruleFor(field as CrmSegmentFieldKey))}
                                allowDeselect={false}
                                searchable
                            />
                            <Select
                                className={classes.operator}
                                aria-label={t`Condition`}
                                data={KIND_OPERATORS[FIELD_KINDS[rule.field]].map(operator => ({
                                    value: operator,
                                    label: segmentOperatorLabel(operator),
                                }))}
                                value={rule.operator}
                                onChange={operator => operator && changeOperator(index, operator as CrmSegmentOperator)}
                                allowDeselect={false}
                            />
                            <div className={classes.value}>{renderValue(rule, index)}</div>
                            <ActionIcon
                                className={classes.remove}
                                variant="subtle"
                                color="gray"
                                aria-label={t`Remove rule`}
                                onClick={() => onChange({...value, rules: value.rules.filter((_, ruleIndex) => ruleIndex !== index)})}
                            >
                                <IconX size={16}/>
                            </ActionIcon>
                            {error && <span className={classes.error}>{error}</span>}
                            {!error && !isRuleComplete(rule) && (
                                <span className={classes.unfinished}>{t`Not applied yet. Finish this rule to use it.`}</span>
                            )}
                        </div>
                    );
                })}
                {isChoosingRule && (
                    <div className={classes.rule} data-choosing>
                        <Select
                            className={classes.field}
                            aria-label={t`Rule`}
                            placeholder={t`Choose what to filter by`}
                            data={fieldGroups}
                            value={null}
                            onChange={field => field && addRule(field as CrmSegmentFieldKey)}
                            searchable
                        />
                        <ActionIcon
                            className={classes.remove}
                            variant="subtle"
                            color="gray"
                            aria-label={t`Remove rule`}
                            onClick={() => setIsChoosingRule(false)}
                        >
                            <IconX size={16}/>
                        </ActionIcon>
                    </div>
                )}
            </div>

            <div className={classes.footer}>
                <Button
                    variant="default"
                    size="xs"
                    leftSection={<IconPlus size={14}/>}
                    onClick={() => setIsChoosingRule(true)}
                    disabled={isChoosingRule || value.rules.length >= MAX_SEGMENT_RULES}
                >
                    {t`Add a rule`}
                </Button>
                {showCount && <span className={classes.count} data-testid="segment-live-count">
                    {value.rules.length === 0 && t`Add a rule to see who matches`}
                    {value.rules.length > 0 && !complete && t`Finish the rules to see who matches`}
                    {complete && countQuery.data && (
                        <>
                            <strong>{plural(countQuery.data.contacts_count, {one: '# person matches', other: '# people match'})}</strong>
                            {' · '}
                            {plural(countQuery.data.mailable_count, {one: '# subscribed', other: '# subscribed'})}
                        </>
                    )}
                    {complete && !countQuery.data && (countQuery.isError ? t`These rules aren't valid yet` : t`Counting…`)}
                </span>}
            </div>
            {errors['definition.rules'] && <span className={classes.error}>{errors['definition.rules']}</span>}
        </div>
    );
};
