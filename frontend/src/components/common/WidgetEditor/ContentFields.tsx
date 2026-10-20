import {Alert, Button, MultiSelect, NumberInput, SegmentedControl, Select, Switch, Text, TextInput} from "@mantine/core";
import {t} from "@lingui/macro";
import {NavLink} from "react-router";
import {OccurrenceSelect} from "../OccurrenceSelect";
import {IconEyeOff} from "@tabler/icons-react";
import {Event, EventOccurrence} from '../../../types.ts';
import {getEventCategories} from "../../../constants/eventCategories.ts";
import {WidgetEventsLayout, WidgetOpenIn, WidgetSettings, WidgetType} from "../../../utilites/widgetConfig.ts";
import {SectionsPicker} from "./SectionsPicker.tsx";
import {useAccess} from "../../../hooks/useAccess.ts";
import {hiddenSectionsMessage, LineupSectionAvailability} from "./sectionAvailability.ts";
import classes from './WidgetEditor.module.scss';

interface FieldsProps {
    settings: WidgetSettings;
    update: (patch: Partial<WidgetSettings>) => void;
}

export const ButtonFields = ({settings, update}: FieldsProps) => (
    <TextInput
        label={t`Button text`}
        size="sm"
        value={settings.button_text ?? ''}
        placeholder={t`Get tickets`}
        maxLength={100}
        onChange={(event) => update({button_text: event.currentTarget.value})}
    />
);

interface SectionFieldsProps extends FieldsProps {
    availability: LineupSectionAvailability;
    homepageDesignerPath: string;
}

export const SectionFields = ({settings, update, availability, homepageDesignerPath}: SectionFieldsProps) => {
    const {isReadOnly} = useAccess();
    const lineupHidden = availability.lineup === 'hidden';
    const hiddenMessage = hiddenSectionsMessage(lineupHidden, availability.schedule === 'hidden');

    return (
        <>
            <div>
                <Text size="xs" c="dimmed" mb={8}>{t`Turn on extra sections and drag to reorder.`}</Text>
                <SectionsPicker value={settings.sections} availability={availability} onChange={(sections) => update({sections})}/>
                {hiddenMessage && (
                    <div className={classes.sectionNotice}>
                        <IconEyeOff size={16}/>
                        <span>
                            {hiddenMessage}
                            {!isReadOnly && (
                                <>
                                    {' '}<NavLink to={homepageDesignerPath} className={classes.inlineLink}>{t`Open homepage designer`}</NavLink>
                                </>
                            )}
                        </span>
                    </div>
                )}
            </div>
            <Switch
                label={t`Show lineup strip above tickets`}
                checked={!!settings.show_lineup && !lineupHidden}
                disabled={lineupHidden}
                onChange={(event) => update({show_lineup: event.currentTarget.checked})}
                data-testid="widget-show-lineup-switch"
            />
        </>
    );
};

interface OccurrenceFieldProps extends FieldsProps {
    event: Event;
    occurrences: EventOccurrence[];
}

export const OccurrenceField = ({settings, update, event, occurrences}: OccurrenceFieldProps) => (
    <OccurrenceSelect
        label={t`Date`}
        description={t`Pin the widget to one date, or let visitors choose.`}
        occurrences={occurrences}
        timezone={event.timezone}
        value={settings.occurrence_id ? String(settings.occurrence_id) : null}
        onChange={(value) => update({occurrence_id: value ? Number(value) : undefined})}
        placeholder={t`All dates`}
        clearable
    />
);

export const TicketFields = ({settings, update, event}: FieldsProps & { event?: Event }) => {
    const categories = event?.product_categories ?? [];
    const productOptions = categories
        .map(category => ({
            group: category.name,
            items: (category.products ?? [])
                .filter(product => !product.is_addon_only)
                .map(product => ({value: String(product.id), label: product.title})),
        }))
        .filter(group => group.items.length > 0);

    return (
        <>
            <MultiSelect
                label={t`Only these categories`}
                placeholder={settings.categories?.length ? undefined : t`All categories`}
                size="sm"
                data={categories.map(category => ({value: String(category.id), label: category.name}))}
                value={(settings.categories ?? []).map(String)}
                onChange={(values) => update({categories: values.map(Number)})}
                clearable
                searchable
                hidePickedOptions
                data-testid="widget-categories-select"
            />
            <MultiSelect
                label={t`Only these tickets`}
                description={t`Leave both empty to show everything that's on sale.`}
                placeholder={settings.products?.length ? undefined : t`All tickets`}
                size="sm"
                data={productOptions}
                value={(settings.products ?? []).map(String)}
                onChange={(values) => update({products: values.map(Number)})}
                clearable
                searchable
                hidePickedOptions
                data-testid="widget-products-select"
            />
            <Switch
                label={t`Hide category names`}
                checked={!!settings.hide_category_titles}
                onChange={(event) => update({hide_category_titles: event.currentTarget.checked})}
                data-testid="widget-hide-category-titles-switch"
            />
        </>
    );
};

export const PromoCodeFields = ({settings, update}: FieldsProps) => (
    <>
        <TextInput
            label={t`Promo code`}
            description={t`Applied automatically when the widget loads.`}
            size="sm"
            value={settings.promo_code ?? ''}
            maxLength={50}
            onChange={(event) => update({promo_code: event.currentTarget.value})}
        />
        <Switch
            label={t`Hide "Have a promo code?"`}
            checked={!!settings.hide_promo_code}
            onChange={(event) => update({hide_promo_code: event.currentTarget.checked})}
            data-testid="widget-hide-promo-code-switch"
        />
    </>
);

export const LayoutPicker = ({settings, update, disabled}: FieldsProps & { disabled: boolean }) => (
    <div className={classes.typePicker}>
        <SegmentedControl
            fullWidth
            size="sm"
            disabled={disabled}
            value={settings.layout ?? 'list'}
            onChange={(value) => update({layout: value as WidgetEventsLayout})}
            aria-label={t`Layout`}
            data={[
                {value: 'list', label: t`List`},
                {value: 'grid', label: t`Grid`},
                {value: 'compact', label: t`Compact`},
            ]}
            data-testid="widget-layout-control"
        />
    </div>
);

export const EventListFields = ({settings, update}: FieldsProps) => (
    <>
        <div className={classes.inputRow}>
            <Select
                label={t`Category`}
                size="sm"
                placeholder={t`All events`}
                clearable
                data={getEventCategories().map(category => ({value: category.id, label: category.name}))}
                value={settings.event_category ?? null}
                onChange={(value) => update({event_category: value ?? undefined})}
            />
            <NumberInput
                label={t`Per page`}
                size="sm"
                min={1}
                max={50}
                allowDecimal={false}
                value={settings.limit ?? 12}
                onChange={(value) => update({limit: Number(value) || undefined})}
            />
        </div>
        <div>
            <Text size="sm" fw={500} mb={6}>{t`When someone picks an event`}</Text>
            <SegmentedControl
                fullWidth
                size="sm"
                value={settings.open_in ?? 'popup'}
                onChange={(value) => update({open_in: value as WidgetOpenIn})}
                data={[
                    {value: 'popup', label: t`Show tickets`},
                    {value: 'page', label: t`Open event page`},
                ]}
            />
        </div>
        <Switch
            label={t`Include a Past events tab`}
            checked={!!settings.show_past}
            onChange={(event) => update({show_past: event.currentTarget.checked})}
        />
    </>
);

interface LineupNoticeProps {
    type: WidgetType;
    availability: LineupSectionAvailability;
    homepageDesignerPath: string;
    lineupPath?: string;
}

export const LineupNotice = ({type, availability, homepageDesignerPath, lineupPath}: LineupNoticeProps) => {
    const {isReadOnly} = useAccess();
    if (type !== 'LINEUP' && type !== 'SCHEDULE') {
        return null;
    }

    if (availability[type === 'LINEUP' ? 'lineup' : 'schedule'] === 'hidden') {
        return (
            <Alert variant="light" color="yellow" className={classes.notice}>
                <Text size="sm">
                    {type === 'LINEUP'
                        ? t`Your lineup is hidden on your event page, so this widget will be empty.`
                        : t`Your schedule is hidden on your event page, so this widget will be empty.`}
                </Text>
                {!isReadOnly && (
                    <Button component={NavLink} to={homepageDesignerPath} variant="subtle" size="compact-sm" mt={4}>
                        {t`Open homepage designer`}
                    </Button>
                )}
            </Alert>
        );
    }

    return lineupPath ? (
        <NavLink to={lineupPath} className={`${classes.inlineLink} ${classes.typeLink}`}>{t`Manage lineup & schedule`}</NavLink>
    ) : null;
};
