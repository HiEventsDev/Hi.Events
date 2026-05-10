import {t} from "@lingui/macro";
import {Alert, Button, Checkbox, NumberInput, SegmentedControl, Stack, Text, TextInput} from "@mantine/core";
import {useForm} from "@mantine/form";
import {modals} from "@mantine/modals";
import {useParams} from "react-router";
import {useMemo, useState} from "react";
import {IconClock, IconInfoCircle, IconRuler, IconTag, IconUsers} from "@tabler/icons-react";
import {Modal} from "../../../../common/Modal";
import {InputGroup} from "../../../../common/InputGroup";
import {BulkUpdateOccurrencesRequest, EventOccurrence, EventOccurrenceStatus, GenericModalProps, MessageType} from "../../../../../types.ts";
import {useBulkUpdateOccurrences} from "../../../../../mutations/useBulkUpdateOccurrences.ts";
import {useGetEvent} from "../../../../../queries/useGetEvent.ts";
import {showSuccess, showError} from "../../../../../utilites/notifications.tsx";
import {useFormErrorResponseHandler} from "../../../../../hooks/useFormErrorResponseHandler.tsx";
import {SendMessageModal} from "../../../../modals/SendMessageModal";
import {buildBulkRescheduleTemplate} from "../rescheduleMessageTemplate";
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
    const {data: event} = useGetEvent(eventId);

    const [pendingNotification, setPendingNotification] = useState<{
        occurrenceIds: number[];
        actionDescription: string;
    } | null>(null);

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
            // 'loaded'   → only the dates currently visible on this page/calendar window
            // 'matching' → every date in the event matching the filter toggles below
            // The list view paginates at 50/page and the calendar view loads only its
            // visible window, so without this distinction "Bulk Edit" silently affected
            // far fewer dates than the modal copy implied.
            scope: 'loaded' as 'loaded' | 'matching',
        },
    });

    const selectedAction = form.values.bulk_action;
    const isAllMatching = form.values.scope === 'matching';

    const affectedOccurrences = (occurrences ?? []).filter(occ => {
        if (occ.status === EventOccurrenceStatus.CANCELLED) return false;
        if (form.values.future_only && occ.is_past) return false;
        if (form.values.skip_overridden && occ.is_overridden) return false;
        return true;
    });
    const affectedOccurrenceIds = affectedOccurrences
        .map(occ => Number(occ.id))
        .filter((id): id is number => Number.isFinite(id));
    const loadedAffectedCount = occurrences ? affectedOccurrences.length : undefined;
    const loadedAffectedAttendees = affectedOccurrences
        .reduce((sum, occ) => sum + (occ.statistics?.attendees_registered ?? 0), 0);

    const buildRequest = (): BulkUpdateOccurrencesRequest | null => {
        if (!isAllMatching && affectedOccurrenceIds.length === 0) {
            showError(t`No dates match the current filters.`);
            return null;
        }

        // 'matching' scope expands the update to every occurrence in the
        // event that satisfies future_only / skip_overridden — the server
        // resolves the set, so we omit occurrence_ids and flip apply_to_all.
        // 'loaded' scope keeps the historical safe behaviour: only the dates
        // currently visible on the page/calendar window are updated.
        const base: BulkUpdateOccurrencesRequest = isAllMatching
            ? {
                action: 'update',
                future_only: form.values.future_only,
                skip_overridden: form.values.skip_overridden,
                apply_to_all: true,
            }
            : {
                action: 'update',
                future_only: form.values.future_only,
                skip_overridden: form.values.skip_overridden,
                occurrence_ids: affectedOccurrenceIds,
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

    const submit = (data: BulkUpdateOccurrencesRequest, notifyAfterSave: boolean) => {
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

                // Chain the notification step using server-returned ids so an
                // 'all matching' update can still target the exact attendees
                // the backend touched (the local list only contains the
                // current page / calendar window).
                const updatedIds = response.updated_ids ?? [];
                if (notifyAfterSave && updatedIds.length > 0 && selectedAction) {
                    setPendingNotification({
                        occurrenceIds: updatedIds,
                        actionDescription: describeAction(selectedAction),
                    });
                    return;
                }
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

    const handleSubmit = () => {
        const data = buildRequest();
        if (!data) return;

        // Shift-times and change-duration move start/end timestamps; attendees
        // aren't auto-notified, so we make the organizer acknowledge the impact.
        // For 'all matching' scope we can't know the attendee total upfront
        // (the count would only cover the loaded page), so we always show the
        // confirm and let the organizer decide whether to chain the message.
        const changesDateOrTime = selectedAction === 'shift_times' || selectedAction === 'change_duration';
        const shouldConfirm = changesDateOrTime && (isAllMatching || loadedAffectedAttendees > 0);
        if (shouldConfirm) {
            const notifyRef = {current: true};
            modals.openConfirmModal({
                title: t`You're changing session times`,
                children: (
                    <>
                        <Text size="sm" mb="md">
                            {isAllMatching
                                ? t`This applies to every matching date in the event, including dates not currently visible. Attendees registered on any of those dates will be reachable via the message composer once the update finishes.`
                                : (loadedAffectedAttendees === 1
                                    ? t`1 attendee is registered across the affected sessions.`
                                    : t`${loadedAffectedAttendees} attendees are registered across the affected sessions.`)}
                        </Text>
                        <Checkbox
                            defaultChecked={true}
                            label={t`Let them know about the changes`}
                            description={t`We'll open a message composer with a pre-filled template after saving. You review and send it — nothing is sent automatically.`}
                            onChange={(e) => { notifyRef.current = e.currentTarget.checked; }}
                        />
                    </>
                ),
                labels: {confirm: t`Save`, cancel: t`Cancel`},
                onConfirm: () => submit(data, notifyRef.current),
            });
            return;
        }

        submit(data, false);
    };

    const notificationTemplate = useMemo(() => {
        if (!pendingNotification || !event) return null;
        return buildBulkRescheduleTemplate(
            event,
            pendingNotification.occurrenceIds.length,
            pendingNotification.actionDescription,
        );
    }, [pendingNotification, event]);

    const scopeParts = [];
    scopeParts.push(form.values.future_only ? t`future` : t`all`);
    if (form.values.skip_overridden) {
        scopeParts.push(t`non-edited`);
    }

    const describeAction = (action: BulkAction): string => {
        switch (action) {
            case 'shift_times': return t`a shift in start/end times`;
            case 'change_duration': return t`a change in duration`;
            case 'update_capacity': return t`capacity updates`;
            case 'update_label': return t`label updates`;
        }
    };

    return (
        <>
        <Modal opened={!pendingNotification} onClose={onClose} heading={t`Bulk Edit Dates`}>
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

                    <Text size="sm" fw={600} mb={4}>{t`Apply to`}</Text>
                    <SegmentedControl
                        fullWidth
                        size="sm"
                        mb="sm"
                        data={[
                            {value: 'loaded', label: t`Loaded dates`},
                            {value: 'matching', label: t`All matching dates`},
                        ]}
                        {...form.getInputProps('scope')}
                    />

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
                        {isAllMatching
                            ? t`Applies to every ${scopeParts.join(', ')}, non-cancelled date in this event — including dates not currently loaded.`
                            : t`Applies to ${scopeParts.join(', ')}, non-cancelled dates currently loaded on this page.`}
                        {!isAllMatching && loadedAffectedCount !== undefined && (
                            <Text size="sm" fw={600} mt={4}>
                                {t`This will affect ${loadedAffectedCount} date(s).`}
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
        {pendingNotification && notificationTemplate && (
            <SendMessageModal
                onClose={() => {
                    setPendingNotification(null);
                    onClose();
                }}
                messageType={MessageType.AllAttendees}
                eventOccurrenceIds={pendingNotification.occurrenceIds}
                initialSubject={notificationTemplate.subject}
                initialMessage={notificationTemplate.message}
            />
        )}
        </>
    );
};
