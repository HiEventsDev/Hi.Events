import {useMemo} from "react";
import {t} from "@lingui/macro";
import {useForm} from "@mantine/form";
import {Alert, Button, MultiSelect, Select, TextInput} from "@mantine/core";
import {TimeInput} from "@mantine/dates";
import {IconAlertTriangle, IconTrash} from "@tabler/icons-react";
import dayjs from "dayjs";
import {Modal, ModalFooter} from "../../../../common/Modal";
import {Editor} from "../../../../common/Editor";
import {UserGeneratedContent} from "../../../../common/UserGeneratedContent";
import {Event} from "../../../../../types.ts";
import {EventLineup, ScheduleItem} from "../../../../../api/lineup.client.ts";
import {useUpsertScheduleItem} from "../../../../../mutations/useUpsertScheduleItem.ts";
import {useDeleteScheduleItem} from "../../../../../mutations/useDeleteScheduleItem.ts";
import {useAccess} from "../../../../../hooks/useAccess.ts";
import {useFormErrorResponseHandler} from "../../../../../hooks/useFormErrorResponseHandler.tsx";
import {showError, showSuccess} from "../../../../../utilites/notifications.tsx";
import {confirmationDialog} from "../../../../../utilites/confirmationDialog.tsx";
import {getLineupTerminology, toEventLocal} from "../../../../../utilites/lineup.ts";
import {DATE_INPUT_FORMAT, LOCAL_DATETIME_FORMAT, TIME_INPUT_FORMAT} from "../../../../../utilites/lineupSchedule.ts";
import {UNASSIGNED_SPACE} from "./scheduleConstants.ts";
import {localScheduleDayKey} from "./scheduleDays.ts";
import classes from "../Lineup.module.scss";
import {DateField} from "../../../../common/DateField";

export interface ScheduleItemDraft {
    start: string;
    end: string;
    spaceId: number | null;
    entryIds: number[];
}

interface ScheduleItemModalProps {
    event: Event;
    lineup: EventLineup;
    item?: ScheduleItem;
    draft?: ScheduleItemDraft;
    occurrenceDays?: ReadonlySet<string>;
    onSaved: (start: string) => void;
    onClose: () => void;
}

interface ScheduleItemFormValues {
    lineup_entry_ids: string[];
    title: string;
    space: string;
    date: string;
    start_time: string;
    end_time: string;
    description: string;
}

export const ScheduleItemModal = ({event, lineup, item, draft, occurrenceDays, onSaved, onClose}: ScheduleItemModalProps) => {
    const {isReadOnly} = useAccess();
    const upsertMutation = useUpsertScheduleItem();
    const deleteMutation = useDeleteScheduleItem();
    const handleFormError = useFormErrorResponseHandler();
    const terminology = getLineupTerminology(event.category);

    const start = item ? toEventLocal(item.starts_at, event.timezone) : dayjs(draft!.start.replace(' ', 'T'));
    const end = item ? toEventLocal(item.ends_at, event.timezone) : dayjs(draft!.end.replace(' ', 'T'));
    const spaceId = item ? item.event_space_id : draft?.spaceId;

    const form = useForm<ScheduleItemFormValues>({
        initialValues: {
            lineup_entry_ids: (item?.lineup_entry_ids ?? draft?.entryIds ?? []).map(String),
            title: item?.title ?? '',
            space: spaceId ? String(spaceId) : UNASSIGNED_SPACE,
            date: start.format(DATE_INPUT_FORMAT),
            start_time: start.format(TIME_INPUT_FORMAT),
            end_time: end.format(TIME_INPUT_FORMAT),
            description: item?.description ?? '',
        },
        validate: {
            date: value => value ? null : t`Pick a date`,
            start_time: value => value ? null : t`Pick a start time`,
            end_time: (value, values) => {
                if (!value) {
                    return t`Pick an end time`;
                }
                return value === values.start_time ? t`The slot needs some length` : null;
            },
            title: (value, values) => value.trim() || values.lineup_entry_ids.length
                ? null
                : t`Add a title or pick someone from the lineup`,
        },
    });

    const endsNextDay = !!form.values.start_time && !!form.values.end_time && form.values.end_time < form.values.start_time;
    const offDate = !!occurrenceDays && !!form.values.date && !!form.values.start_time
        && !occurrenceDays.has(localScheduleDayKey(`${form.values.date} ${form.values.start_time}`));

    const entryOptions = useMemo(() => lineup.entries.map(entry => ({
        value: String(entry.id),
        label: entry.profile.name,
    })), [lineup.entries]);

    const titlePlaceholder = form.values.lineup_entry_ids
        .map(id => lineup.entries.find(entry => String(entry.id) === id)?.profile.name)
        .filter(Boolean)
        .join(', ');

    const handleSubmit = (values: ScheduleItemFormValues) => {
        const startsAt = dayjs(`${values.date}T${values.start_time}`);
        let endsAt = dayjs(`${values.date}T${values.end_time}`);
        if (!endsAt.isAfter(startsAt)) {
            endsAt = endsAt.add(1, 'day');
        }

        upsertMutation.mutate({
            eventId: event.id!,
            itemId: item?.id,
            item: {
                title: values.title.trim() || null,
                description: values.description || null,
                starts_at: startsAt.format(LOCAL_DATETIME_FORMAT),
                ends_at: endsAt.format(LOCAL_DATETIME_FORMAT),
                event_space_id: values.space === UNASSIGNED_SPACE ? null : Number(values.space),
                lineup_entry_ids: values.lineup_entry_ids.map(Number),
            },
        }, {
            onSuccess: () => {
                showSuccess(item ? t`Time slot updated` : t`Time slot added`);
                onSaved(startsAt.format(LOCAL_DATETIME_FORMAT));
                onClose();
            },
            onError: error => handleFormError(form, error),
        });
    };

    const remove = () => item && confirmationDialog(
        t`Remove this time slot from the schedule?`,
        () => deleteMutation.mutate({eventId: event.id!, itemId: item.id}, {
            onSuccess: () => {
                showSuccess(t`Time slot removed`);
                onClose();
            },
            onError: () => showError(t`Could not remove the time slot`),
        }),
        {confirm: t`Remove`},
    );

    return (
        <Modal opened onClose={onClose} heading={isReadOnly ? t`Time slot` : item ? t`Edit time slot` : t`New time slot`} size="lg">
            <form onSubmit={form.onSubmit(handleSubmit)} className={classes.fieldStack}>
                <MultiSelect
                    label={terminology.lineup}
                    placeholder={t`Who's on?`}
                    data={entryOptions}
                    searchable
                    disabled={isReadOnly}
                    data-testid="lineup-slot-people"
                    {...form.getInputProps('lineup_entry_ids')}
                />
                <TextInput
                    label={t`Title`}
                    description={t`Optional when someone from the lineup is picked`}
                    placeholder={titlePlaceholder || t`e.g. Opening remarks, Lunch, Doors open`}
                    maxLength={255}
                    disabled={isReadOnly}
                    {...form.getInputProps('title')}
                />
                <Select
                    label={t`Stage or room`}
                    allowDeselect={false}
                    disabled={isReadOnly}
                    data={[
                        ...lineup.spaces.map(space => ({value: String(space.id), label: space.name})),
                        {value: UNASSIGNED_SPACE, label: t`No stage`},
                    ]}
                    {...form.getInputProps('space')}
                />
                <div className={classes.timeRow}>
                    <DateField
                        label={t`Date`}
                        disabled={isReadOnly}
                        {...form.getInputProps('date')}
                    />
                    <TimeInput
                        label={t`Starts`}
                        disabled={isReadOnly}
                        {...form.getInputProps('start_time')}
                    />
                    <TimeInput
                        label={t`Ends`}
                        disabled={isReadOnly}
                        description={endsNextDay ? t`Ends the next day` : undefined}
                        inputWrapperOrder={['label', 'input', 'description', 'error']}
                        {...form.getInputProps('end_time')}
                    />
                </div>
                {offDate && (
                    <Alert variant="light" color="orange" icon={<IconAlertTriangle size={16}/>}>
                        {t`Your event has no date on this day, so this slot won't show on your event page.`}
                    </Alert>
                )}
                {isReadOnly
                    ? item?.description && (
                        <div>
                            <div className={classes.fieldLabel}>{t`Description`}</div>
                            <UserGeneratedContent className={classes.readOnlyContent} html={item.description}/>
                        </div>
                    )
                    : (
                        <Editor
                            label={t`Description`}
                            editorType="simple"
                            value={form.values.description}
                            onChange={value => form.setFieldValue('description', value)}
                            maxLength={20000}
                        />
                    )}
                {!isReadOnly && (
                    <ModalFooter
                        start={item && (
                            <Button variant="subtle" color="red" leftSection={<IconTrash size={16}/>} onClick={remove}>
                                {t`Remove`}
                            </Button>
                        )}
                    >
                        <Button variant="default" onClick={onClose}>{t`Cancel`}</Button>
                        <Button type="submit" loading={upsertMutation.isPending} data-testid="lineup-slot-save-button">
                            {item ? t`Save slot` : t`Add slot`}
                        </Button>
                    </ModalFooter>
                )}
            </form>
        </Modal>
    );
};
