import {ActionIcon, Tooltip} from '@mantine/core';
import {Event, IdParam, Product} from "../../../types.ts";
import classes from "./EventCard.module.scss";
import {NavLink, useNavigate} from "react-router";
import {
    IconArchive,
    IconCopy,
    IconDotsVertical,
    IconEye,
    IconSettings,
} from "@tabler/icons-react";
import {t} from "@lingui/macro"
import {eventHomepagePath} from "../../../utilites/urlHelper.ts";
import {useDisclosure} from "@mantine/hooks";
import {DuplicateEventModal} from "../../modals/DuplicateEventModal";
import {useState} from "react";
import {ActionMenu, ActionMenuItemsGroup} from '../ActionMenu';
import {confirmationDialog} from "../../../utilites/confirmationDialog.tsx";
import {showError, showSuccess} from "../../../utilites/notifications.tsx";
import {useUpdateEventStatus} from "../../../mutations/useUpdateEventStatus.ts";
import {formatCurrency} from "../../../utilites/currency.ts";
import {formatNumber} from "../../../utilites/helpers.ts";
import {formatDateWithLocale, relativeDate} from "../../../utilites/dates.ts";
import {Card} from "../Card";

const placeholderGradients = [
    'linear-gradient(135deg, var(--mantine-color-violet-5) 0%, var(--mantine-color-indigo-5) 100%)',
    'linear-gradient(135deg, var(--mantine-color-pink-5) 0%, var(--mantine-color-grape-5) 100%)',
    'linear-gradient(135deg, var(--mantine-color-blue-5) 0%, var(--mantine-color-cyan-5) 100%)',
    'linear-gradient(135deg, var(--mantine-color-teal-5) 0%, var(--mantine-color-green-5) 100%)',
    'linear-gradient(135deg, var(--mantine-color-orange-5) 0%, var(--mantine-color-yellow-5) 100%)',
    'linear-gradient(135deg, var(--mantine-color-indigo-5) 0%, var(--mantine-color-blue-5) 100%)',
    'linear-gradient(135deg, var(--mantine-color-grape-5) 0%, var(--mantine-color-violet-5) 100%)',
    'linear-gradient(135deg, var(--mantine-color-cyan-5) 0%, var(--mantine-color-teal-5) 100%)',
];

interface EventCardProps {
    event: Event;
}

export function EventCard({event}: EventCardProps) {
    const navigate = useNavigate();
    const [isDuplicateModalOpen, duplicateModal] = useDisclosure(false);
    const [eventId, setEventId] = useState<IdParam>();
    const statusToggleMutation = useUpdateEventStatus();

    const coverImage = event.images?.find(img => img.type === 'EVENT_COVER');
    const gradientIndex = event.id ? Number(event.id) % placeholderGradients.length : 0;
    const placeholderGradient = placeholderGradients[gradientIndex];

    const handleDuplicate = () => {
        setEventId(event.id);
        duplicateModal.open();
    }

    const handleStatusToggle = () => {
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

    const getStatusConfig = () => {
        if (event.status === 'ARCHIVED') {
            return {label: t`Archived`, status: 'archived'};
        }
        if (event.lifecycle_status === 'ENDED') {
            return {label: t`Ended`, status: 'ended'};
        }
        if (event.status === 'DRAFT') {
            return {label: t`Draft`, status: 'draft'};
        }
        if (event.lifecycle_status === 'ONGOING') {
            return {label: t`Live`, status: 'live', pulse: true};
        }
        return {label: t`On Sale`, status: 'onsale'};
    };

    const getLocationText = () => {
        if (event.settings?.is_online_event) return t`Online`;
        const location = event.settings?.location_details;
        if (location?.venue_name) return location.venue_name;
        if (location?.city) return location.city;
        return null;
    };

    const getTicketAvailability = () => {
        const products = event.products;
        if (!products || products.length === 0) return null;

        const ticketProducts = products.filter((p: Product) => p.product_type === 'TICKET');
        if (ticketProducts.length === 0) return null;

        const availableCount = ticketProducts.filter((p: Product) => {
            if (p.status === 'INACTIVE') return false;
            return p.is_available !== false && p.is_sold_out !== true;
        }).length;

        const totalCount = ticketProducts.length;

        if (availableCount === 0) {
            return {text: t`Sold out`, status: 'sold-out'};
        }
        if (availableCount === totalCount) {
            return {
                text: totalCount === 1 ? t`1 ticket type` : t`${totalCount} ticket types`,
                status: 'available'
            };
        }
        return {text: t`${availableCount} of ${totalCount} available`, status: 'partial'};
    };

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
                {
                    label: t`Duplicate event`,
                    icon: <IconCopy size={14}/>,
                    onClick: handleDuplicate,
                },
                {
                    label: event?.status === 'ARCHIVED' ? t`Restore event` : t`Archive event`,
                    icon: <IconArchive size={14}/>,
                    onClick: handleStatusToggle,
                },
            ],
        },
    ];

    const monthShort = formatDateWithLocale(event.start_date, 'monthShort', event.timezone);
    const dayOfMonth = formatDateWithLocale(event.start_date, 'dayOfMonth', event.timezone);
    const shortDateTime = formatDateWithLocale(event.start_date, 'shortDateTime', event.timezone);
    const relativeDateStr = relativeDate(event.start_date);
    const locationText = getLocationText();

    const revenue = event?.statistics?.sales_total_gross || 0;
    const attendees = event?.statistics?.attendees_registered || 0;

    const statusConfig = getStatusConfig();
    const ticketAvailability = getTicketAvailability();

    const isEnded = event.lifecycle_status === 'ENDED';
    const isDraft = event.status === 'DRAFT';

    return (
        <>
            <Card className={`${classes.eventCard} ${isEnded ? classes.isEnded : ''} ${isDraft ? classes.isDraft : ''}`}>
                <NavLink to={`/manage/event/${event.id}/dashboard`} className={classes.cardLink}>
                    <div className={classes.imageContainer}>
                        <div
                            className={`${classes.image} ${!coverImage ? classes.placeholderImage : ''}`}
                            style={coverImage
                                ? {backgroundImage: `url(${coverImage.url})`}
                                : {background: placeholderGradient}
                            }
                        />
                        <div className={`${classes.imageOverlay} ${!coverImage ? classes.placeholderOverlay : ''}`}/>

                        <div className={`${classes.statusBadge} ${classes[`status-${statusConfig.status}`]}`}>
                            {statusConfig.pulse && <span className={classes.pulseDot}/>}
                            {statusConfig.label}
                        </div>

                        <div className={classes.dateBadge}>
                            <span className={classes.dateDay}>{dayOfMonth}</span>
                            <span className={classes.dateMonth}>{monthShort}</span>
                        </div>
                    </div>

                    <div className={classes.content}>
                        <div className={classes.contentMain}>
                            <h3 className={classes.title}>{event.title}</h3>
                            <div className={classes.meta}>
                                <span className={classes.eventDate}>{shortDateTime}</span>
                                <span className={classes.relativeDate}>({relativeDateStr})</span>
                                {locationText && (
                                    <>
                                        <span className={classes.separator}>·</span>
                                        <span className={classes.location}>{locationText}</span>
                                    </>
                                )}
                            </div>
                            <div className={classes.footer}>
                                <NavLink
                                    to={`/manage/organizer/${event?.organizer?.id}`}
                                    className={classes.organizer}
                                    onClick={(e) => e.stopPropagation()}
                                >
                                    {event?.organizer?.name}
                                </NavLink>
                                {ticketAvailability && (
                                    <span className={`${classes.ticketStatus} ${classes[`ticket-${ticketAvailability.status}`]}`}>
                                        {ticketAvailability.text}
                                    </span>
                                )}
                            </div>
                        </div>

                        <div className={classes.statsPanel}>
                            <Tooltip label={t`Attendees registered`} withArrow position="top">
                                <div className={classes.stat}>
                                    <span className={classes.statValue}>{formatNumber(attendees)}</span>
                                    <span className={classes.statLabel}>{t`Attendees`}</span>
                                </div>
                            </Tooltip>
                            <Tooltip label={t`Gross revenue`} withArrow position="top">
                                <div className={classes.stat}>
                                    <span className={classes.statValueRevenue}>
                                        {formatCurrency(revenue, event?.currency)}
                                    </span>
                                    <span className={classes.statLabel}>{t`Revenue`}</span>
                                </div>
                            </Tooltip>
                        </div>

                        <div className={classes.menuButton} onClick={(e) => e.preventDefault()}>
                            <ActionMenu
                                itemsGroups={menuItems}
                                target={
                                    <ActionIcon
                                        className={classes.actionButton}
                                        size="md"
                                        variant="subtle"
                                    >
                                        <IconDotsVertical size={18}/>
                                    </ActionIcon>
                                }
                            />
                        </div>
                    </div>
                </NavLink>
            </Card>
            {isDuplicateModalOpen && <DuplicateEventModal eventId={eventId} onClose={duplicateModal.close}/>}
        </>
    );
}
