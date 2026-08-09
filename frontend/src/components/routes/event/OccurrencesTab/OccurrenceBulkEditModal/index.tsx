import {t} from "@lingui/macro";
import {Button, Checkbox, NumberInput, Radio, SegmentedControl, Stack, Text, TextInput} from "@mantine/core";
import {useForm} from "@mantine/form";
import {modals} from "@mantine/modals";
import {useParams} from "react-router";
import {useMemo, useState} from "react";
import {IconClock, IconMapPin, IconRuler, IconTag, IconUsers} from "@tabler/icons-react";
import {Modal} from "../../../../common/Modal";
import {Callout} from "../../../../common/Callout";
import {InputGroup} from "../../../../common/InputGroup";
import {Editor} from "../../../../common/Editor";
import {LocationPicker, LocationPickerValue} from "../../../../common/LocationPicker";
import {BulkUpdateOccurrencesRequest, EventOccurrence, EventOccurrenceStatus, GenericModalProps, LocationType, MessageType} from "../../../../../types.ts";
import {useBulkUpdateOccurrences} from "../../../../../mutations/useBulkUpdateOccurrences.ts";
import {useCreateLocation} from "../../../../../mutations/useCreateLocation.ts";
import {useGetEvent} from "../../../../../queries/useGetEvent.ts";
import {isAddressSet} from "../../../../../utilites/addressUtilities.ts";
import {showSuccess, showError} from "../../../../../utilites/notifications.tsx";
import {useFormErrorResponseHandler} from "../../../../../hooks/useFormErrorResponseHandler.tsx";
import {SendMessageModal} from "../../../../modals/SendMessageModal";
import {buildBulkRescheduleTemplate} from "../rescheduleMessageTemplate";
import classes from './OccurrenceBulkEditModal.module.scss';

type BulkAction = 'shift_times' | 'change_duration' | 'update_capacity' | 'update_label' | 'update_location';
type BulkLocationMode = 'in_person' | 'online' | 'clear';

interface OccurrenceBulkEditModalProps extends GenericModalProps {
    occurrences?: EventOccurrence[];
}

export const OccurrenceBulkEditModal = ({onClose, occurrences}: OccurrenceBulkEditModalProps) => {
    const ACTIONS: { value: BulkAction; label: string; icon: typeof IconClock; description: string; testId: string }[] = [
        {value: 'shift_times', label: t`Shift times`, icon: IconClock, description: t`Move all dates earlier or later`, testId: 'occurrence-bulk-action-shift-times'},
        {value: 'change_duration', label: t`Change duration`, icon: IconRuler, description: t`Set how long each date lasts`, testId: 'occurrence-bulk-action-change-duration'},
        {value: 'update_capacity', label: t`Update capacity`, icon: IconUsers, description: t`Change the attendee limit`, testId: 'occurrence-bulk-action-update-capacity'},
        {value: 'update_label', label: t`Update label`, icon: IconTag, description: t`Set or clear the date label`, testId: 'occurrence-bulk-action-update-label'},
        {value: 'update_location', label: t`Update location`, icon: IconMapPin, description: t`Set, change, or remove the date's location or online details`, testId: 'occurrence-bulk-action-update-location'},
    ];
    const {eventId} = useParams();
    const bulkUpdateMutation = useBulkUpdateOccurrences();
    const createLocationMutation = useCreateLocation();
    const errorHandler = useFormErrorResponseHandler();
    const {data: event} = useGetEvent(eventId);
    const organizerId = event?.organizer_id;

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
            location_mode: 'in_person' as BulkLocationMode,
            location: null as LocationPickerValue | null,
            online_event_connection_details: '',
            future_only: true,
            skip_overridden: true,
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

    const buildRequest = async (): Promise<BulkUpdateOccurrencesRequest | null> => {
        if (!isAllMatching && affectedOccurrenceIds.length === 0) {
            showError(t`No dates match the current filters.`);
            return null;
        }

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
            case 'update_location': {
                if (form.values.location_mode === 'online') {
                    const details = form.values.online_event_connection_details.trim();
                    if (details === '') {
                        showError(t`Add connection details for the online event.`);
                        return null;
                    }
                    return {
                        ...base,
                        event_location: {
                            type: LocationType.Online,
                            online_event_connection_details: details,
                        },
                    };
                }
                if (form.values.location_mode === 'clear') {
                    return {...base, clear_event_location: true};
                }
                const location = form.values.location;
                if (!location || (location.kind === 'new' && !isAddressSet(location.address))) {
                    showError(t`Enter a venue name or address for in-person events`);
                    return null;
                }
                if (location.kind === 'saved') {
                    return {
                        ...base,
                        event_location: {
                            type: LocationType.InPerson,
                            location_id: Number(location.location.id),
                        },
                    };
                }
                if (!organizerId) {
                    showError(t`No organizer context available.`);
                    return null;
                }
                const created = await createLocationMutation.mutateAsync({
                    organizerId,
                    payload: {
                        name: location.address.venue_name || null,
                        structured_address: location.address,
                        latitude: location.latitude,
                        longitude: location.longitude,
                        provider: location.provider,
                        provider_place_id: location.providerPlaceId,
                    },
                });
                return {
                    ...base,
                    event_location: {
                        type: LocationType.InPerson,
                        location_id: Number(created.data.id),
                    },
                };
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
                    'update_location': t`Updated location for ${count} date(s)`,
                };
                showSuccess(actionLabels[selectedAction!] || t`Updated ${count} date(s)`);

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
                    errorHandler(form, error, error?.response?.data?.message);
                } else {
                    showError(error?.response?.data?.message || t`Bulk update failed.`);
                }
            },
        });
    };

    const handleSubmit = async () => {
        let data: BulkUpdateOccurrencesRequest | null;
        try {
            data = await buildRequest();
        } catch (error: any) {
            showError(error?.response?.data?.message || t`Could not prepare the bulk update.`);
            return;
        }
        if (!data) return;

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
            case 'update_location': return t`location updates`;
        }
    };

    return (
        <>
        <Modal opened={!pendingNotification} onClose={onClose} heading={t`Bulk Edit Dates`}>
            {!selectedAction ? (
                <div className={classes.actionPicker}>
                    {ACTIONS.map(({value, label, icon: Icon, description, testId}) => (
                        <button
                            key={value}
                            type="button"
                            className={classes.actionOption}
                            data-testid={testId}
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

                    <Callout variant="info">
                        {isAllMatching
                            ? t`Applies to every ${scopeParts.join(', ')}, non-cancelled date in this event — including dates not currently loaded.`
                            : t`Applies to ${scopeParts.join(', ')}, non-cancelled dates currently loaded on this page.`}
                        {!isAllMatching && loadedAffectedCount !== undefined && (
                            <Text size="sm" fw={600} mt={4}>
                                {t`This will affect ${loadedAffectedCount} date(s).`}
                            </Text>
                        )}
                    </Callout>

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
                                    max={168}
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

                    {selectedAction === 'update_location' && (
                        <Stack gap="xs">
                            <Radio.Group
                                label={t`Mode`}
                                value={form.values.location_mode}
                                onChange={(value) => form.setFieldValue('location_mode', value as BulkLocationMode)}
                            >
                                <Stack gap={6} mt={6}>
                                    <Radio value="in_person" label={t`In person — set a venue`}/>
                                    <Radio value="online" label={t`Online — provide connection details`}/>
                                    <Radio value="clear" label={t`Clear location — fall back to the event default`}/>
                                </Stack>
                            </Radio.Group>

                            {form.values.location_mode === 'in_person' && organizerId && (
                                <LocationPicker
                                    organizerId={organizerId}
                                    value={form.values.location}
                                    onChange={(value) => form.setFieldValue('location', value)}
                                />
                            )}

                            {form.values.location_mode === 'online' && (
                                <Editor
                                    value={form.values.online_event_connection_details}
                                    label={t`Connection Details`}
                                    description={t`These details will replace any existing location on the affected dates and show on attendee tickets.`}
                                    onChange={(value) => form.setFieldValue('online_event_connection_details', value)}
                                />
                            )}

                            {form.values.location_mode === 'clear' && (
                                <Callout variant="tip">
                                    {t`Clearing removes any per-date override. Affected dates will fall back to the event's default location.`}
                                </Callout>
                            )}
                        </Stack>
                    )}

                    <Button
                        type="submit"
                        fullWidth
                        mt="lg"
                        data-testid="occurrence-bulk-edit-submit-button"
                        loading={bulkUpdateMutation.isPending || createLocationMutation.isPending}
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
