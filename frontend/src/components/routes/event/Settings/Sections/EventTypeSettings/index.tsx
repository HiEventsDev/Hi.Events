import {t} from "@lingui/macro";
import {Button, Text} from "@mantine/core";
import {IconCalendarEvent, IconCalendarRepeat} from "@tabler/icons-react";
import {useParams} from "react-router";
import {SettingsPage, SettingsRow, SettingsSection} from "../../../../../common/SettingsPage";
import {WriteAccess} from "../../../../../common/WriteAccess";
import {useGetEvent} from "../../../../../../queries/useGetEvent.ts";
import {useGetEventCounts} from "../../../../../../queries/useGetEventCounts.ts";
import {useUpdateEventType} from "../../../../../../mutations/useUpdateEventType.ts";
import {confirmationDialog} from "../../../../../../utilites/confirmationDialog.tsx";
import {showError, showSuccess} from "../../../../../../utilites/notifications.tsx";
import {EventType} from "../../../../../../types.ts";

export const EventTypeSettings = () => {
    const {eventId} = useParams();
    const {data: event} = useGetEvent(eventId);
    const {data: counts} = useGetEventCounts(eventId);
    const typeMutation = useUpdateEventType();

    if (!event) {
        return null;
    }

    const isRecurring = event.type === EventType.RECURRING;
    const targetType = isRecurring ? EventType.SINGLE : EventType.RECURRING;
    const hasOrders = (counts?.total_orders ?? 0) > 0;

    const toRecurringWarning = hasOrders
        ? t`This event already has orders, so you won't be able to change it back to a single event.`
        : t`You can only change it back to a single event while it has exactly one date and no orders. Once someone places an order, this can't be undone.`;

    const handleChange = () => {
        confirmationDialog(
            isRecurring
                ? t`Change this event to a single event?`
                : t`Change this event to a recurring event?`,
            () => {
                typeMutation.mutate({eventId: eventId!, type: targetType}, {
                    onSuccess: () => {
                        showSuccess(isRecurring
                            ? t`Event changed to a single event`
                            : t`Event changed to a recurring event`);
                    },
                    onError: (error: any) => {
                        showError(error?.response?.data?.errors?.type?.[0]
                            || error?.response?.data?.message
                            || t`Failed to change the event type`);
                    },
                });
            },
            {
                confirm: isRecurring ? t`Change to single event` : t`Change to recurring event`,
                description: isRecurring
                    ? t`The date's own prices, product visibility, capacity and label will be removed. This can't be undone.`
                    : toRecurringWarning,
            },
        );
    };

    return (
        <SettingsPage
            title={t`Event Type`}
            description={t`Choose whether this event happens once or on several dates`}
        >
            <SettingsSection>
                <SettingsRow
                    label={isRecurring ? t`This is a recurring event.` : t`This is a single event.`}
                    description={(
                        <>
                            {isRecurring
                                ? t`A recurring event can become a single event when it has exactly one active date and no orders. The date's own prices, product visibility, capacity and label are removed; its time and location are kept.`
                                : t`Change to a recurring event to sell tickets for several dates. You'll manage dates from the Occurrence Schedule. Capacity assignments don't apply to recurring events.`}
                            {!isRecurring && <Text inherit mt={6}>{toRecurringWarning}</Text>}
                        </>
                    )}
                >
                    <WriteAccess>
                        <Button
                            variant="outline"
                            data-testid="event-type-change-button"
                            leftSection={isRecurring ? <IconCalendarEvent size={16}/> : <IconCalendarRepeat size={16}/>}
                            loading={typeMutation.isPending}
                            onClick={handleChange}
                        >
                            {isRecurring ? t`Change to single event` : t`Change to recurring event`}
                        </Button>
                    </WriteAccess>
                </SettingsRow>
            </SettingsSection>
        </SettingsPage>
    );
};
