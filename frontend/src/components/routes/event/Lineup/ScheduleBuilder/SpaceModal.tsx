import {t} from "@lingui/macro";
import {useForm} from "@mantine/form";
import {Button, Select, TextInput} from "@mantine/core";
import {IconTrash} from "@tabler/icons-react";
import {Modal, ModalFooter} from "../../../../common/Modal";
import {Event} from "../../../../../types.ts";
import {EventLineup, EventSpace} from "../../../../../api/lineup.client.ts";
import {useUpsertEventSpace} from "../../../../../mutations/useUpsertEventSpace.ts";
import {useDeleteEventSpace} from "../../../../../mutations/useDeleteEventSpace.ts";
import {useFormErrorResponseHandler} from "../../../../../hooks/useFormErrorResponseHandler.tsx";
import {showError, showSuccess} from "../../../../../utilites/notifications.tsx";
import {confirmationDialog} from "../../../../../utilites/confirmationDialog.tsx";
import classes from "../Lineup.module.scss";

interface SpaceModalProps {
    event: Event;
    lineup: EventLineup;
    space?: EventSpace;
    onClose: () => void;
}

export const SpaceModal = ({event, lineup, space, onClose}: SpaceModalProps) => {
    const upsertMutation = useUpsertEventSpace();
    const deleteMutation = useDeleteEventSpace();
    const handleFormError = useFormErrorResponseHandler();
    const venues = lineup.venues ?? [];

    const form = useForm({
        initialValues: {
            name: space?.name ?? '',
            event_location_id: space?.event_location_id ? String(space.event_location_id) : null as string | null,
        },
        validate: {
            name: value => value.trim() ? null : t`Name is required`,
        },
    });

    const handleSubmit = (values: typeof form.values) => upsertMutation.mutate({
        eventId: event.id!,
        spaceId: space?.id,
        space: {
            name: values.name.trim(),
            event_location_id: values.event_location_id ? Number(values.event_location_id) : null,
        },
    }, {
        onSuccess: () => {
            showSuccess(space ? t`Saved` : t`Stage added`);
            onClose();
        },
        onError: error => handleFormError(form, error),
    });

    const spaceName = space?.name ?? '';

    const remove = () => space && confirmationDialog(
        t`Delete ${spaceName}? Time slots on it stay on the schedule without a stage.`,
        () => deleteMutation.mutate({eventId: event.id!, spaceId: space.id}, {
            onSuccess: () => {
                showSuccess(t`Stage deleted`);
                onClose();
            },
            onError: () => showError(t`Could not delete ${spaceName}`),
        }),
        {confirm: t`Delete`},
    );

    return (
        <Modal opened onClose={onClose} heading={space ? t`Edit stage or room` : t`Add a stage or room`} size={520}>
            <form onSubmit={form.onSubmit(handleSubmit)} className={classes.fieldStack}>
                <TextInput
                    label={t`Name`}
                    placeholder={t`e.g. Main Stage, Room 2B, The Tent`}
                    required
                    maxLength={100}
                    data-autofocus
                    {...form.getInputProps('name')}
                />
                {venues.length > 0 && (
                    <Select
                        label={t`Venue`}
                        description={t`For events across more than one venue`}
                        placeholder={t`Same as the event`}
                        clearable
                        data={venues.map(venue => ({
                            value: String(venue.id),
                            label: venue.location.name || venue.location.structured_address?.venue_name || t`Unnamed venue`,
                        }))}
                        {...form.getInputProps('event_location_id')}
                    />
                )}
                <ModalFooter
                    start={space && (
                        <Button variant="subtle" color="red" leftSection={<IconTrash size={16}/>} onClick={remove}>{t`Delete`}</Button>
                    )}
                >
                    <Button variant="default" onClick={onClose}>{t`Cancel`}</Button>
                    <Button type="submit" loading={upsertMutation.isPending} data-testid="lineup-space-save-button">
                        {space ? t`Save` : t`Add`}
                    </Button>
                </ModalFooter>
            </form>
        </Modal>
    );
};
