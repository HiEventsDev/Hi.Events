import {ActionIcon, Button, Group, Text,} from '@mantine/core';
import {Event, IdParam} from "../../../types.ts";
import classes from "./EventCard.module.scss";
import {Card} from "../Card";
import {NavLink, useNavigate} from "react-router-dom";
import {
    IconCalendarEvent,
    IconCopy,
    IconDotsVertical,
    IconEye,
    IconMap,
    IconQrcode,
    IconSettings,
    IconUser,
} from "@tabler/icons-react";
import {relativeDate} from "../../../utilites/dates.ts";
import {t} from "@lingui/macro"
import {eventHomepagePath} from "../../../utilites/urlHelper.ts";
import {EventStatusBadge} from "../EventStatusBadge";
import {useDisclosure} from "@mantine/hooks";
import {DuplicateEventModal} from "../../modals/DuplicateEventModal";
import {useState} from "react";
import {ActionMenu, MenuItem} from '../ActionMenu/index.tsx';
import {confirmationDialog} from "../../../utilites/confirmationDialog.tsx";
import {showError, showSuccess} from "../../../utilites/notifications.tsx";
import {useUpdateEventStatus} from "../../../mutations/useUpdateEventStatus.ts";

interface EventCardProps {
    event: Event;
}

export function EventCard({event}: EventCardProps) {
    const navigate = useNavigate();
    const [isDuplicateModalOpen, duplicateModal] = useDisclosure(false);
    const [eventId, setEventId] = useState<IdParam>();
    const statusToggleMutation = useUpdateEventStatus();

    const handleDuplicate = (event: Event) => {
        setEventId(() => event.id);
        duplicateModal.open();
    }

    const handleStatusToggle = (event: Event) => () => {
        const message = event?.status !== 'ARCHIVED'
            ? t`Are you sure you want to archive this event?`
            : t`Are you sure you want to restore this event? It will be restored as a draft event.`;

        confirmationDialog(message, () => {
            statusToggleMutation.mutate({
                eventId: event.id,
                status: event?.status === 'ARCHIVED' ? 'DRAFT' : 'ARCHIVED'
            }, {
                onSuccess: () => {
                    showSuccess(t`Event status updated`);
                },
                onError: (error: any) => {
                    showError(error?.response?.data?.message || t`Event status update failed. Please try again later`);
                }
            });
        })
    }

    return (
        <>
            <Card className={classes.card}>
                <div className={classes.body}>
                    {event && <EventStatusBadge event={event}/>}
                    <Text className={classes.title} mt="xs" mb="md">
                        <NavLink to={`/manage/event/${event.id}`}>
                            {event.title}
                        </NavLink>
                    </Text>
                    <div className={classes.eventInfo}>
                        {event.settings?.location_details?.venue_name && (
                            <Group gap="xs" wrap="nowrap">
                                <IconMap color={'#ccc'}/>
                                <Text size="xs">
                                    {event.settings?.location_details?.venue_name}
                                </Text>
                            </Group>
                        )}
                        {event.settings?.is_online_event && (
                            <Group gap="xs" wrap="nowrap">
                                <IconMap color={'#ccc'}/>
                                <Text size="xs">
                                    {t`Online event`}
                                </Text>
                            </Group>
                        )}
                        <Group gap="xs" wrap="nowrap">
                            <IconCalendarEvent color={'#ccc'}/>
                            <Text size="xs">
                                {relativeDate(event.start_date)}
                            </Text>
                        </Group>
                        <Group gap="xs" wrap="nowrap">
                            <IconUser color={'#ccc'}/>
                            <Text size="xs">
                                <NavLink to={`/manage/organizer/${event?.organizer?.id}`}>
                                    {event?.organizer?.name}
                                </NavLink>
                            </Text>
                        </Group>
                    </div>
                </div>
                <div className={classes.actions}>
                    <ActionMenu
                        itemsGroups={[
                            {
                                label: '',
                                items: [
                                    {
                                        label: t`View event page`,
                                        icon: <IconEye size={14}/>,
                                        onClick: () => window.location.href = eventHomepagePath(event),
                                    },
                                    {
                                        label: t`Manage event`,
                                        icon: <IconSettings size={14}/>,
                                        onClick: () => navigate(`/manage/event/${event.id}`),
                                    },
                                    ((event.lifecycle_status === 'UPCOMING' || event.lifecycle_status === 'ONGOING')
                                        && event.status === 'LIVE') && {
                                        label: t`Check-in`,
                                        icon: <IconQrcode size={14}/>,
                                        onClick: () => navigate(`/manage/event/${event.id}/check-in`),
                                        visible: true,
                                    },
                                    {
                                        label: t`Duplicate event`,
                                        icon: <IconCopy size={14}/>,
                                        onClick: () => handleDuplicate(event),
                                    },
                                    {
                                        label: event?.status === 'ARCHIVED' ? t`Restore event` : t`Archive event`,
                                        icon: <IconCopy size={14}/>,
                                        onClick: handleStatusToggle(event)
                                    },
                                ].filter(Boolean) as MenuItem[],
                            },
                        ]}
                        target={
                            <div>
                                <ActionIcon className={classes.desktopButton} size={"md"} variant={"transparent"}>
                                    <IconDotsVertical/>
                                </ActionIcon>
                                <Button className={classes.mobileButton} variant={"light"}>
                                    {t`Manage`}
                                </Button>
                            </div>
                        }
                    />
                </div>
            </Card>
            {isDuplicateModalOpen && <DuplicateEventModal eventId={eventId} onClose={duplicateModal.close}/>}
        </>
    );
}
