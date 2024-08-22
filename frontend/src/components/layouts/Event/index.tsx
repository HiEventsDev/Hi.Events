import {Badge, Breadcrumbs, Burger, Button, UnstyledButton, VisuallyHidden} from '@mantine/core';
import {NavLink, Outlet, useParams} from "react-router-dom";
import {
    IconChevronLeft,
    IconChevronRight,
    IconDashboard,
    IconDeviceTabletCode,
    IconDiscount2,
    IconExternalLink,
    IconPaint,
    IconQrcode,
    IconReceipt,
    IconSend,
    IconSettings,
    IconStar,
    IconTicket,
    IconUserQuestion,
    IconUsers,
    IconUsersGroup
} from "@tabler/icons-react";
import {useState} from "react";
import classes from './Event.module.scss';
import {GlobalMenu} from "../../common/GlobalMenu";
import {t} from "@lingui/macro";
import {useGetEvent} from "../../../queries/useGetEvent.ts";
import Truncate from "../../common/Truncate";
import {useUpdateEventStatus} from "../../../mutations/useUpdateEventStatus.ts";
import {showError, showSuccess} from "../../../utilites/notifications.tsx";
import {Tooltip} from "../../common/Tooltip";
import {confirmationDialog} from "../../../utilites/confirmationDialog.tsx";
import {useGetEventSettings} from "../../../queries/useGetEventSettings.ts";
import {EventStatusBadge} from "../../common/EventStatusBadge";

const EventLayout = () => {
    const data = [
        {link: 'getting-started', label: t`Getting Started`, icon: IconStar},
        {label: t`Manage`},
        {link: 'dashboard', label: t`Dashboard`, icon: IconDashboard},
        {link: 'settings', label: t`Settings`, icon: IconSettings},
        {link: 'tickets', label: t`Tickets`, icon: IconTicket},
        {link: 'attendees', label: t`Attendees`, icon: IconUsers},
        {link: 'orders', label: t`Orders`, icon: IconReceipt},
        {link: 'questions', label: t`Questions`, icon: IconUserQuestion},
        {link: 'promo-codes', label: t`Promo Codes`, icon: IconDiscount2},
        {link: 'messages', label: t`Messages`, icon: IconSend},
        {link: 'capacity-assignments', label: t`Capacity`, icon: IconUsersGroup},
        {link: 'check-in', label: t`Check-In Lists`, icon: IconQrcode},

        {label: t`Tools`},
        {link: 'homepage-designer', label: t`Homepage Designer`, icon: IconPaint},
        {link: 'widget', label: t`Widget Embed`, icon: IconDeviceTabletCode},
    ];
    const [sidebarOpen, setSidebarOpen] = useState(false);
    const {eventId} = useParams();
    const {data: event, isFetched: isEventFetched} = useGetEvent(eventId);
    const {data: eventSettings, isFetched: isEventSettingsFetched} = useGetEventSettings(eventId);
    const statusToggleMutation = useUpdateEventStatus();

    const links = data.map((item) => {
        if (!item.link) {
            return (
                <div className={classes.sectionHeading} key={item.label}>
                    {item.label}
                </div>
            );
        }

        if (!isEventSettingsFetched || !isEventFetched) {
            return <a key={item.label} className={classes.loading}></a>;
        }

        if (item.link === 'getting-started' && eventSettings?.hide_getting_started_page) {
            return null;
        }

        return (
            <NavLink
                to={item.comingSoon ? '#' : item.link}
                key={item.label}
                onClick={() => setSidebarOpen(false)}
                className={({isActive}) =>
                    ((isActive && !item.comingSoon) ? classes.linkActive : "") + " " + classes.link
                }
            >
                <item.icon size={23} className={classes.linkIcon} stroke={1.5}/>
                <span>{item.label}{item.comingSoon && <Badge ml={'4px'} size={'xs'}>{t`Coming Soon`}</Badge>}</span>
            </NavLink>
        );
    });

    const handleStatusToggle = () => {
        const message = event?.status === 'LIVE'
            ? t`Are you sure you want to make this event draft? This will make the event invisible to the public`
            : t`Are you sure you want to make this event public? This will make the event visible to the public`;

        confirmationDialog(message, () => {
            statusToggleMutation.mutate({
                eventId,
                status: event?.status === 'LIVE' ? 'DRAFT' : 'LIVE'
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
        <div id={'event-manage-container'} className={`${classes.container} ${sidebarOpen ? classes.closed : ''}`}>
            <div className={`${classes.topBar}`}>
                <div className={classes.burger}>
                    <Burger color={'#fff'} opened={sidebarOpen} onClick={() => setSidebarOpen(!sidebarOpen)}
                            size={'sm'}/>
                </div>
                <div className={classes.logo}>
                    <NavLink to={'/manage/events'}>
                        <img src={'/logo-text-only-white-text.png'} alt={''}/>
                    </NavLink>
                </div>
                <div className={classes.breadcrumbs}>
                    <Breadcrumbs separator='/'>
                        <NavLink to={'/manage/events'}>{t`Events`}</NavLink>

                        {isEventFetched && (
                            <NavLink to={`/manage/organizer/${event?.organizer?.id}`}>
                                <Truncate length={15} text={event?.organizer?.name} showTooltip={false}/>
                            </NavLink>
                        )}

                        {isEventFetched && (
                            <NavLink to={`/manage/event/${event?.id}`}>
                                <Truncate length={15} text={event?.title} showTooltip={false}/>
                            </NavLink>
                        )}

                        {!isEventFetched && <span>... </span>}
                    </Breadcrumbs>

                    {isEventFetched && (
                        <Tooltip label={event?.status === 'LIVE'
                            ? t`Event is visible to the public`
                            : t`Event is not visible to the public`
                        }>
                            <UnstyledButton onClick={handleStatusToggle}>
                                {event && (
                                    <div style={{marginLeft: '10px'}}>
                                        <EventStatusBadge event={event}/>
                                    </div>
                                )}
                            </UnstyledButton>
                        </Tooltip>
                    )}
                </div>
                <div className={classes.menu}>
                    <Button
                        component={NavLink}
                        to={`/event/${eventId}/${event?.slug}`}
                        target={'_blank'}
                        variant={'transparent'}
                        leftSection={<IconExternalLink size={17}/>}
                        className={classes.eventPageButton}
                    >
                        {t`Event page`}
                    </Button>
                    <GlobalMenu/>
                </div>
            </div>
            <div className={classes.main} id={'event-manage-main'}>
                <Outlet/>
            </div>
            <div className={classes.sidebar}>
                <div className={classes.logo}>
                    <NavLink to={'/manage/events'}>
                        <img style={{maxWidth: '170px', margin: "20px auto"}}
                             src={'/logo.svg'} alt={''}/>
                    </NavLink>
                </div>
                <div className={classes.nav}>
                    {links}
                </div>
                <UnstyledButton
                    className={classes.sidebarClose}
                    onClick={() => setSidebarOpen(!sidebarOpen)}>
                    <IconChevronLeft/>
                    <VisuallyHidden>{t`Close sidebar`}</VisuallyHidden>
                </UnstyledButton>
            </div>
            {sidebarOpen && <UnstyledButton
                className={classes.sidebarOpen}
                onClick={() => setSidebarOpen(!sidebarOpen)}>
                <IconChevronRight/>
                <VisuallyHidden>{t`Open sidebar`}</VisuallyHidden>
            </UnstyledButton>}
        </div>
    )
}

export default EventLayout;
