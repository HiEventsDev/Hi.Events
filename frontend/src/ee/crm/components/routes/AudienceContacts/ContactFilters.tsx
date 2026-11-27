import {t} from "@lingui/macro";
import {Button} from "@mantine/core";
import {IconDeviceFloppy, IconX} from "@tabler/icons-react";
import {IdParam} from "../../../../../types.ts";
import {CrmSegmentDefinition} from "../../../types.ts";
import {isRuleComplete, SegmentBuilder} from "../../SegmentBuilder";
import classes from "./Contacts.module.scss";

export const rejectedRuleErrors = (error: unknown, draft: CrmSegmentDefinition): Record<string, string> => {
    const errors = (error as { response?: { data?: { errors?: Record<string, string | string[]> } } })?.response?.data?.errors ?? {};
    const draftIndexes = draft.rules.flatMap((rule, index) => isRuleComplete(rule) ? [index] : []);

    return Object.fromEntries(Object.entries(errors).map(([key, messages]) => {
        const appliedIndex = /^definition\.rules\.(\d+)$/.exec(key)?.[1];

        return [
            appliedIndex === undefined ? key : `definition.rules.${draftIndexes[Number(appliedIndex)]}`,
            [messages].flat()[0] ?? '',
        ];
    }));
};

interface ContactFiltersProps {
    organizerId: IdParam;
    value: CrmSegmentDefinition;
    errors?: Record<string, string>;
    canSave: boolean;
    onChange: (definition: CrmSegmentDefinition) => void;
    onSave: () => void;
    onClear: () => void;
    onClose: () => void;
}

export const ContactFilters = ({organizerId, value, errors, canSave, onChange, onSave, onClear, onClose}: ContactFiltersProps) => (
    <section className={classes.filterPanel} aria-label={t`Filter contacts`}>
        <SegmentBuilder organizerId={organizerId} value={value} onChange={onChange} errors={errors} showCount={false} startsChoosingRule/>
        <div className={classes.filterActions}>
            {value.rules.length > 0 ? (
                <Button variant="subtle" color="gray" size="xs" leftSection={<IconX size={14}/>} onClick={onClear}>
                    {t`Clear filter`}
                </Button>
            ) : (
                <Button variant="subtle" color="gray" size="xs" onClick={onClose}>{t`Close`}</Button>
            )}
            {canSave && (
                <Button variant="default" size="xs" leftSection={<IconDeviceFloppy size={14}/>} onClick={onSave}>
                    {t`Save as segment`}
                </Button>
            )}
        </div>
    </section>
);
