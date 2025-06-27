import {ActionIcon, Button,} from '@mantine/core';
import {Event, IdParam} from "../../../types.ts";
import classes from "./EventCard.module.scss";
import {Card} from "../Card";
import {NavLink, useNavigate} from "react-router";
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
import {ActionMenu, ActionMenuItemsGroup, MenuItem} from '../ActionMenu';
import {confirmationDialog} from "../../../utilites/confirmationDialog.tsx";
import {showError, showSuccess} from "../../../utilites/notifications.tsx";
import {useUpdateEventStatus} from "../../../mutations/useUpdateEventStatus.ts";
import {formatCurrency} from "../../../utilites/currency.ts";
import {formatNumber} from "../../../utilites/helpers.ts";
import {formatDate} from "../../../utilites/dates.ts";

const placeholderEmojis = ['🎉', '🎪', '🎸', '🎨', '🌟', '🎭', '🎯', '🎮', '🎲', '🎳'];

interface EventCardProps {
    event: Event;
}

export function EventCard({event}: EventCardProps) {
    const navigate = useNavigate();
    const [isDuplicateModalOpen, duplicateModal] = useDisclosure(false);
    const [eventId, setEventId] = useState<IdParam>();
    const statusToggleMutation = useUpdateEventStatus();

    // Get event cover image if available
    const coverImage = event.images?.find(img => img.type === 'EVENT_COVER');

    // Get emoji based on event ID for consistency
    const emojiIndex = event.id ? Number(event.id) % placeholderEmojis.length : 0;
    const placeholderEmoji = placeholderEmojis[emojiIndex];

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

    const menuItems: ActionMenuItemsGroup[] = [
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
    ];

    return (
        <>
            <Card className={classes.eventCard}>
                <div className={classes.cardHeader}>
                    <div className={classes.imageContainer}
                         style={coverImage ? {backgroundImage: `url(${coverImage.url})`} : {}}>
                        {!coverImage && (
                            <div className={classes.placeholderImage}>
                                <span className={classes.placeholderEmoji}>{placeholderEmoji}</span>
                            </div>
                        )}
                    </div>
                    <div className={classes.mainContent}>
                        <div className={classes.topRow}>
                            <NavLink to={`/manage/event/${event.id}/dashboard`} className={classes.titleLink}>
                                <h3 className={classes.eventTitle}>{event.title}</h3>
                            </NavLink>
                            {event && <EventStatusBadge event={event}/>}
                        </div>

                        <div className={classes.organizerWrapper}>
                            <NavLink to={`/manage/organizer/${event?.organizer?.id}`} className={classes.organizerLink}>
                                {event?.organizer?.name}
                            </NavLink>
                        </div>

                        <div className={classes.dateTime}>
                            <div className={classes.dateBox}>
                                <span
                                    className={classes.month}>{formatDate(event.start_date, 'MMM', event.timezone)}</span>
                                <span className={classes.day}>{formatDate(event.start_date, 'D', event.timezone)}</span>
                            </div>
                            <div className={classes.timeInfo}>
                                <span
                                    className={classes.time}>{formatDate(event.start_date, 'h:mm A', event.timezone)}</span>
                                {event.end_date && (
                                    <span
                                        className={classes.endTime}>- {formatDate(event.end_date, 'h:mm A', event.timezone)}</span>
                                )}
                            </div>
                        </div>
                    </div>
                    <div className={classes.actionContainer}>
                        <ActionMenu
                            itemsGroups={menuItems}
                            target={
                                <ActionIcon className={classes.actionButton} size={"lg"} variant={"subtle"}>
                                    <IconDotsVertical/>
                                </ActionIcon>
                            }
                        />
                    </div>
                </div>

                <div className={classes.cardFooter}>
                    <div className={classes.statsGrid}>
                        {event.settings?.location_details?.venue_name && (
                            <div className={classes.statItem}>
                                <IconMap size={14} className={classes.statIcon}/>
                                <span className={classes.statText}>
                                    {event.settings?.location_details?.venue_name}
                                </span>
                            </div>
                        )}
                        {event.settings?.is_online_event && (
                            <div className={classes.statItem}>
                                <IconWorld size={14} className={classes.statIcon}/>
                                <span className={classes.statText}>{t`Online event`}</span>
                            </div>
                        )}
                        <div className={classes.statItem}>
                            <IconUsers size={14} className={classes.statIcon}/>
                            <span
                                className={classes.statValue}>{formatNumber(event?.statistics?.products_sold || 0)}</span>
                            <span className={classes.statLabel}>{t`sold`}</span>
                        </div>
                        <div className={classes.statItem}>
                            <IconCash size={14} className={classes.statIcon}/>
                            <span
                                className={classes.statValue}>{formatCurrency(event?.statistics?.sales_total_gross || 0, event?.currency)}</span>
                        </div>
                    </div>

                    <div className={classes.mobileActionWrapper}>
                        <ActionMenu
                            itemsGroups={menuItems}
                            target={
                                <Button
                                    variant="light"
                                    className={classes.manageButton}
                                    fullWidth
                                >
                                    {t`Manage Event`}
                                </Button>
                            }
                        />
                    </div>
                </div>
            </Card>
            {isDuplicateModalOpen && <DuplicateEventModal eventId={eventId} onClose={duplicateModal.close}/>}
        </>
    );
}
