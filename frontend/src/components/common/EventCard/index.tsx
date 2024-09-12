import {ActionIcon, Button,} from '@mantine/core';
import {Event, IdParam} from "../../../types.ts";
import classes from "./EventCard.module.scss";
import {Card} from "../Card";
import {NavLink, useNavigate} from "react-router-dom";
import {
    IconArchive,
    IconCash,
    IconCopy,
    IconDotsVertical,
    IconEye,
    IconMap,
    IconQrcode,
    IconSettings,
    IconUsers,
    IconWorld,
} from "@tabler/icons-react";
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
import {formatCurrency} from "../../../utilites/currency.ts";
import {formatNumber} from "../../../utilites/helpers.ts";
import {formatDate} from "../../../utilites/dates.ts";

const NUMBER_OF_THUMBNAILS = 10;

interface EventCardProps {
    event: Event;
}

export function EventCard({event}: EventCardProps) {
    const navigate = useNavigate();
    const [isDuplicateModalOpen, duplicateModal] = useDisclosure(false);
    const [eventId, setEventId] = useState<IdParam>();
    const statusToggleMutation = useUpdateEventStatus();

    const eventThumbnailPath = ((eventId: number) => {
        const result = (eventId % NUMBER_OF_THUMBNAILS);
        const imageNumber = result === 0 ? NUMBER_OF_THUMBNAILS : Math.abs(result);

        return '/images/event-thumbnails/event-thumb-%d.jpg'.replace('%d', String(imageNumber));
    });

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
                <div className={classes.imageAndDate}
                     style={{backgroundImage: `url(${eventThumbnailPath(event.id as number)})`}}>
                    <div className={classes.date}>
                        <div className={classes.day}>
                            {formatDate(event.start_date, 'D', event.timezone)}
                        </div>
                        <div className={classes.month}>
                            {formatDate(event.start_date, 'MMM', event.timezone)}
                        </div>
                        <div className={classes.time}>
                            {formatDate(event.start_date, 'HH:mm', event.timezone)}
                        </div>
                    </div>
                </div>
                <div className={classes.body}>
                    {event && <EventStatusBadge event={event}/>}
                    <div className={classes.title}>
                        <NavLink to={`/manage/event/${event.id}`}>
                            {event.title}
                        </NavLink>
                    </div>
                    <div className={classes.organizer}>
                        <NavLink to={`/manage/organizer/${event?.organizer?.id}`}>
                            {event?.organizer?.name}
                        </NavLink>
                    </div>
                    <div className={classes.eventInfo}>
                        {event.settings?.location_details?.venue_name && (
                            <div className={classes.infoItem}>
                                <IconMap size={16} color={'#ccc'}/>
                                <span>
                                    {event.settings?.location_details?.venue_name}
                                </span>
                            </div>
                        )}
                        {event.settings?.is_online_event && (
                            <div className={classes.infoItem}>
                                <IconWorld size={16} color={'#ccc'}/>
                                <span>
                            {t`Online event`}
                            </span>
                            </div>
                        )}

                        <div className={classes.infoItem}>
                            <IconUsers size={16} color={'#ccc'}/>
                            <span>
                            {formatNumber(event?.statistics?.tickets_sold || 0)} {t`tickets sold`}
                            </span>
                        </div>

                        <div className={classes.infoItem}>
                            <IconCash size={16} color={'#ccc'}/>
                            <span>
                            {formatCurrency(event?.statistics?.sales_total_gross || 0, event?.currency)} {t`gross sales`}
                            </span>
                        </div>
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
                                        icon: <IconArchive size={14}/>,
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
                                <Button fullWidth className={classes.mobileButton} variant={"light"}>
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
