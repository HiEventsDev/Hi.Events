import {t} from "@lingui/macro";
import {Alert, Button, Checkbox, NumberInput, SegmentedControl, Stack, Text, TextInput} from "@mantine/core";
import {useForm} from "@mantine/form";
import {useParams} from "react-router";
import {IconClock, IconInfoCircle, IconRuler, IconTag, IconUsers} from "@tabler/icons-react";
import {Modal} from "../../../../common/Modal";
import {InputGroup} from "../../../../common/InputGroup";
import {BulkUpdateOccurrencesRequest, EventOccurrence, EventOccurrenceStatus, GenericModalProps} from "../../../../../types.ts";
import {useBulkUpdateOccurrences} from "../../../../../mutations/useBulkUpdateOccurrences.ts";
import {showSuccess, showError} from "../../../../../utilites/notifications.tsx";
import {useFormErrorResponseHandler} from "../../../../../hooks/useFormErrorResponseHandler.tsx";
import classes from './OccurrenceBulkEditModal.module.scss';

type BulkAction = 'shift_times' | 'change_duration' | 'update_capacity' | 'update_label';

interface OccurrenceBulkEditModalProps extends GenericModalProps {
    occurrences?: EventOccurrence[];
}

export const OccurrenceBulkEditModal = ({onClose, occurrences}: OccurrenceBulkEditModalProps) => {
    const ACTIONS: { value: BulkAction; label: string; icon: typeof IconClock; description: string }[] = [
        {value: 'shift_times', label: t`Shift times`, icon: IconClock, description: t`Move all dates earlier or later`},
        {value: 'change_duration', label: t`Change duration`, icon: IconRuler, description: t`Set how long each date lasts`},
        {value: 'update_capacity', label: t`Update capacity`, icon: IconUsers, description: t`Change the attendee limit`},
        {value: 'update_label', label: t`Update label`, icon: IconTag, description: t`Set or clear the date label`},
    ];
    const {eventId} = useParams();
    const bulkUpdateMutation = useBulkUpdateOccurrences();
    const errorHandler = useFormErrorResponseHandler();

    const form = useForm({
        initialValues: {
            bulk_action: null as BulkAction | null,
            shift_direction: 'later' as 'later' | 'earlier',
            shift_hours: 0,
            shift_minutes: 0,
            duration_hours: 1,
            duration_minutes: 0,
            capacity: undefined as number | undefined,
            clear_capacity: false,
            label: '',
            clear_label: false,
            future_only: true,
            skip_overridden: true,
        },
    });

    const selectedAction = form.values.bulk_action;

    const buildRequest = (): BulkUpdateOccurrencesRequest | null => {
        const base: BulkUpdateOccurrencesRequest = {
            action: 'update',
            future_only: form.values.future_only,
            skip_overridden: form.values.skip_overridden,
        };

        switch (selectedAction) {
            case 'shift_times': {
                const totalMinutes = (form.values.shift_hours * 60) + form.values.shift_minutes;
                if (totalMinutes === 0) {
                    showError(t`Enter a time to shift by.`);
                    return null;
                }
                const shift = form.values.shift_direction === 'earlier' ? -totalMinutes : totalMinutes;
                return {...base, start_time_shift: shift, end_time_shift: shift};
            }
            case 'change_duration': {
                const totalMinutes = (form.values.duration_hours * 60) + form.values.duration_minutes;
                if (totalMinutes === 0) {
                    showError(t`Duration must be at least 1 minute.`);
                    return null;
                }
                return {...base, duration_minutes: totalMinutes};
            }
            case 'update_capacity': {
                if (form.values.clear_capacity) {
                    return {...base, clear_capacity: true};
                }
                if (form.values.capacity === undefined || form.values.capacity === null) {
                    showError(t`Enter a capacity value or choose unlimited.`);
                    return null;
                }
                return {...base, capacity: form.values.capacity};
            }
            case 'update_label': {
                if (form.values.clear_label) {
                    return {...base, clear_label: true};
                }
                if (form.values.label.trim() === '') {
                    showError(t`Enter a label or choose to remove it.`);
                    return null;
                }
                return {...base, label: form.values.label.trim()};
            }
            default:
                return null;
        }
    };

    const handleSubmit = () => {
        const data = buildRequest();
        if (!data) return;

        bulkUpdateMutation.mutate({eventId, data}, {
            onSuccess: (response) => {
                const count = response.updated_count;
                const actionLabels: Record<string, string> = {
                    'shift_times': t`Shifted times for ${count} date(s)`,
                    'change_duration': t`Changed duration for ${count} date(s)`,
                    'update_capacity': t`Updated capacity for ${count} date(s)`,
                    'update_label': t`Updated label for ${count} date(s)`,
                };
                showSuccess(actionLabels[selectedAction!] || t`Updated ${count} date(s)`);
                onClose();
            },
            onError: (error: any) => {
                if (error?.response?.status === 422) {
                    errorHandler(form, error);
                } else {
                    showError(error?.response?.data?.message || t`Bulk update failed.`);
                }
            },
        });
    };

    const affectedCount = occurrences
        ? occurrences.filter(occ => {
            if (occ.status === EventOccurrenceStatus.CANCELLED) return false;
            if (form.values.future_only && occ.is_past) return false;
            if (form.values.skip_overridden && occ.is_overridden) return false;
            return true;
        }).length
        : undefined;

    const scopeParts = [];
    scopeParts.push(form.values.future_only ? t`future` : t`all`);
    if (form.values.skip_overridden) {
        scopeParts.push(t`non-edited`);
    }

    return (
        <Modal opened onClose={onClose} heading={t`Bulk Edit Dates`}>
            {!selectedAction ? (
                <div className={classes.actionPicker}>
                    {ACTIONS.map(({value, label, icon: Icon, description}) => (
                        <button
                            key={value}
                            type="button"
                            className={classes.actionOption}
                            onClick={() => form.setFieldValue('bulk_action', value)}
                        >
                            <div className={classes.actionOptionIcon}>
                                <Icon size={20}/>
                            </div>
                            <div>
                                <div className={classes.actionOptionLabel}>{label}</div>
                                <div className={classes.actionOptionDesc}>{description}</div>
                            </div>
                        </button>
                    ))}
                </div>
            ) : (
                <form onSubmit={(e) => { e.preventDefault(); handleSubmit(); }}>
                    <button
                        type="button"
                        className={classes.backLink}
                        onClick={() => form.setFieldValue('bulk_action', null)}
                    >
                        &larr; {t`Choose a different action`}
                    </button>

                    <Stack gap="xs" mb="sm">
                        <Checkbox
                            label={t`Future dates only`}
                            {...form.getInputProps('future_only', {type: 'checkbox'})}
                        />
                        <Checkbox
                            label={t`Skip manually edited dates`}
                            {...form.getInputProps('skip_overridden', {type: 'checkbox'})}
                        />
                    </Stack>

                    <Alert icon={<IconInfoCircle size={16}/>} color="blue" variant="light" mb="md">
                        {t`Applies to ${scopeParts.join(', ')}, non-cancelled dates.`}
                        {affectedCount !== undefined && (
                            <Text size="sm" fw={600} mt={4}>
                                {t`This will affect ${affectedCount} date(s).`}
                            </Text>
                        )}
                    </Alert>

                    {selectedAction === 'shift_times' && (
                        <>
                            <SegmentedControl
                                fullWidth
                                size="sm"
                                mb="sm"
                                data={[
                                    {value: 'later', label: t`Later`},
                                    {value: 'earlier', label: t`Earlier`},
                                ]}
                                {...form.getInputProps('shift_direction')}
                            />
                            <InputGroup>
                                <NumberInput
                                    label={t`Hours`}
                                    {...form.getInputProps('shift_hours')}
                                    min={0}
                                    max={23}
                                    allowNegative={false}
                                />
                                <NumberInput
                                    label={t`Minutes`}
                                    {...form.getInputProps('shift_minutes')}
                                    min={0}
                                    max={59}
                                    allowNegative={false}
                                />
                            </InputGroup>
                        </>
                    )}

                    {selectedAction === 'change_duration' && (
                        <>
                            <Text size="sm" c="dimmed" mb="sm">
                                {t`Set the end time of each date to be this long after its start time.`}
                            </Text>
                            <InputGroup>
                                <NumberInput
                                    label={t`Hours`}
                                    {...form.getInputProps('duration_hours')}
                                    min={0}
                                    max={23}
                                    allowNegative={false}
                                />
                                <NumberInput
                                    label={t`Minutes`}
                                    {...form.getInputProps('duration_minutes')}
                                    min={0}
                                    max={59}
                                    allowNegative={false}
                                />
                            </InputGroup>
                        </>
                    )}

                    {selectedAction === 'update_capacity' && (
                        <>
                            {!form.values.clear_capacity && (
                                <NumberInput
                                    {...form.getInputProps('capacity')}
                                    label={t`New capacity`}
                                    placeholder={t`Enter capacity`}
                                    min={0}
                                    allowNegative={false}
                                    mb="xs"
                                />
                            )}
                            <Checkbox
                                label={t`Set to unlimited (remove limit)`}
                                {...form.getInputProps('clear_capacity', {type: 'checkbox'})}
                            />
                        </>
                    )}

                    {selectedAction === 'update_label' && (
                        <>
                            {!form.values.clear_label && (
                                <TextInput
                                    {...form.getInputProps('label')}
                                    label={t`New label`}
                                    placeholder={t`e.g. Morning Session`}
                                    leftSection={<IconTag size={14}/>}
                                    mb="xs"
                                />
                            )}
                            <Checkbox
                                label={t`Remove label from all dates`}
                                {...form.getInputProps('clear_label', {type: 'checkbox'})}
                            />
                        </>
                    )}

                    <Button
                        type="submit"
                        fullWidth
                        mt="lg"
                        loading={bulkUpdateMutation.isPending}
                    >
                        {t`Apply Changes`}
                    </Button>
                </form>
            )}
        </Modal>
    );
};
