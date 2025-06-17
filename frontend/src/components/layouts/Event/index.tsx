import {ActionIcon, Badge, Breadcrumbs, Burger, Button, UnstyledButton, VisuallyHidden} from '@mantine/core';
import {NavLink, Outlet, useLocation, useParams} from "react-router-dom";
import {
    IconChartPie,
    IconChevronLeft,
    IconChevronRight,
    IconDashboard,
    IconDeviceTabletCode,
    IconDiscount2,
    IconExternalLink,
    IconEye,
    IconEyeOff,
    IconHome,
    IconLayoutSidebar,
    IconPaint,
    IconQrcode,
    IconReceipt,
    IconSend,
    IconSettings,
    IconShare,
    IconStar,
    IconTicket,
    IconUserQuestion,
    IconUsers,
    IconUsersGroup,
    IconWebhook
} from "@tabler/icons-react";
import {useEffect, useState} from "react";
import classes from './Event.module.scss';
import {GlobalMenu} from "../../common/GlobalMenu";
import {t} from "@lingui/macro";
import {useGetEvent} from "../../../queries/useGetEvent.ts";
import Truncate from "../../common/Truncate";
import {useUpdateEventStatus} from "../../../mutations/useUpdateEventStatus.ts";
import {showError, showSuccess} from "../../../utilites/notifications.tsx";
import {confirmationDialog} from "../../../utilites/confirmationDialog.tsx";
import {useGetEventSettings} from "../../../queries/useGetEventSettings.ts";
import {useGetEventStats} from "../../../queries/useGetEventStats.ts";
import {ShareModal} from "../../modals/ShareModal";
import {useDisclosure} from "@mantine/hooks";
import {ShowForDesktop, ShowForMobile} from "../../common/Responsive/ShowHideComponents.tsx";

interface NavItem {
    link?: string;
    label: string;
    icon?: any;
    comingSoon?: boolean;
    isActive?: (isActive: boolean) => boolean;
    badge?: string | undefined | number | null;
    onClick?: () => void;
    showWhen?: () => boolean;
}

interface NavItem {
    link?: string;
    label: string;
    icon?: any;
    comingSoon?: boolean;
    isActive?: (isActive: boolean) => boolean;
}

const EventLayout = () => {
    const location = useLocation();
    const [sidebarOpen, setSidebarOpen] = useState(false);
    const [topBarShadow, setTopBarShadow] = useState(false);
    const {eventId} = useParams();
    const {data: event, isFetched: isEventFetched} = useGetEvent(eventId);
    const {data: eventSettings, isFetched: isEventSettingsFetched} = useGetEventSettings(eventId);
    const statusToggleMutation = useUpdateEventStatus();
    const eventStatsQuery = useGetEventStats(eventId);
    const {data: eventStats} = eventStatsQuery;
    const [opened, {open, close}] = useDisclosure(false);

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
        });
    };

    const data: NavItem[] = [
        {label: t`Overview`},
        {link: 'getting-started', label: t`Getting Started`, icon: IconStar},
        {link: 'dashboard', label: t`Dashboard`, icon: IconDashboard},
        {
            link: 'reports',
            label: t`Reports`,
            icon: IconChartPie,
            isActive: (isActive) => isActive || location.pathname.includes('/report/')
        },

        {label: t`Manage`},
        {link: 'settings', label: t`Settings`, icon: IconSettings},
        {link: 'attendees', label: t`Attendees`, icon: IconUsers, badge: eventStats?.total_products_sold},
        {link: 'orders', label: t`Orders`, icon: IconReceipt, badge: eventStats?.total_orders},
        {link: 'products', label: t`Tickets & Products`, icon: IconTicket},
        {link: 'questions', label: t`Questions`, icon: IconUserQuestion},
        {link: 'capacity-assignments', label: t`Capacity`, icon: IconUsersGroup},
        {link: 'check-in', label: t`Check-In Lists`, icon: IconQrcode},
        {link: 'messages', label: t`Messages`, icon: IconSend},
        {link: 'promo-codes', label: t`Promo Codes`, icon: IconDiscount2},

        {label: t`Tools`},
        {link: 'homepage-designer', label: t`Homepage Designer`, icon: IconPaint},
        {link: 'widget', label: t`Widget Embed`, icon: IconDeviceTabletCode},
        {link: 'webhooks', label: t`Webhooks`, icon: IconWebhook},
    ];

    useEffect(() => {
        const mainElement = document.getElementById('event-manage-main');
        if (mainElement) {
            const handleScroll = () => {
                setTopBarShadow(mainElement.scrollTop > 10);
            };
            mainElement.addEventListener('scroll', handleScroll);
            return () => mainElement.removeEventListener('scroll', handleScroll);
        }
    }, []);

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
                    `${((item.isActive ? item.isActive(isActive) : isActive) && !item.comingSoon)
                        ? classes.linkActive
                        : ""} ${classes.link}`
                }
            >
                <item.icon size={20} className={classes.linkIcon} stroke={1.5}/>
                <span>{item.label}</span>
                {item.badge !== undefined &&
                    <Badge size="xs" radius="xl" className={classes.navBadge}>{item.badge}</Badge>}
                {item.comingSoon &&
                    <Badge ml={'4px'} size={'xs'} className={classes.comingSoonBadge}>{t`Coming Soon`}</Badge>}
            </NavLink>
        );
    });

    return (
        <div id={'event-manage-container'} className={`${classes.container} ${sidebarOpen ? classes.closed : ''}`}>
            <div className={`${classes.topBar} ${topBarShadow ? classes.withShadow : ''}`}>
                <div className={classes.topBarMain}>
                    <div className={classes.burger}>
                        <Burger color={'#fff'} opened={sidebarOpen} onClick={() => setSidebarOpen(!sidebarOpen)}
                                size={'sm'}/>
                    </div>
                    <div className={classes.logo}>
                        <NavLink to={'/manage/events'}>
                            <img style={{maxWidth: '160px', margin: "10px auto"}}
                                 src={'/logo-wide-white-text.svg'} alt={''}/>
                        </NavLink>
                    </div>

                    {/* Status toggle - now on the left side */}
                    <div className={classes.statusToggleContainer}>
                        {isEventFetched && (
                            <Button
                                onClick={handleStatusToggle}
                                size="sm"
                                className={`${classes.statusToggleButton} ${event?.status === 'DRAFT' ? classes.draftButton : classes.liveButton}`}
                                leftSection={event?.status === 'DRAFT' ? <IconEyeOff size={16}/> : <IconEye size={16}/>}
                                rightSection={<IconChevronRight size={14}/>}
                            >
                                {event?.status === 'DRAFT'
                                    ? <span>{t`Draft`} <span
                                        className={classes.statusAction}>{t`- Click to Publish`}</span></span>
                                    : <span>{t`Live`} <span
                                        className={classes.statusAction}>{t`- Click to Unpublish`}</span></span>
                                }
                            </Button>
                        )}
                    </div>

                    <div className={classes.actionGroup}>
                        <Button
                            component={NavLink}
                            to={`/event/${eventId}/${event?.slug}`}
                            target={'_blank'}
                            variant={'transparent'}
                            leftSection={<IconExternalLink size={17}/>}
                            className={classes.eventPageButton}
                            title={t`Preview Event page`}
                        >
                            <div className={classes.eventPageButtonText}>
                                <span className={classes.desktop}>
                                    {t`Preview Event page`}
                                </span>
                                <span className={classes.mobile}>
                                    {t`Event Page`}
                                </span>
                            </div>

                        </Button>
                        <div className={classes.menu}>
                            <GlobalMenu/>
                        </div>
                    </div>
                </div>
                <div className={classes.breadcrumbsRow}>
                    <div className={classes.breadcrumbs}>
                        <IconHome size={16} style={{marginRight: '8px', opacity: 0.6}}/>
                        <Breadcrumbs separator={<span style={{margin: '0 0px', color: '#aaa'}}>/</span>}>
                            <NavLink to={'/manage/events'}>{t`Events`}</NavLink>

                            {isEventFetched && (
                                <NavLink to={`/manage/organizer/${event?.organizer?.id}`}>
                                    <Truncate length={24} text={event?.organizer?.name} showTooltip={false}/>
                                </NavLink>
                            )}

                            {isEventFetched && (
                                <NavLink to={`/manage/event/${event?.id}`}>
                                    <Truncate length={20} text={event?.title} showTooltip={false}/>
                                </NavLink>
                            )}

                            {!isEventFetched && <span>... </span>}
                        </Breadcrumbs>
                    </div>
                    <div className={classes.shareButton}>
                        {event && (
                            <>
                                <Button
                                    onClick={open}
                                    variant="transparent"
                                    leftSection={<IconShare size={16}/>}
                                >
                                    {t`Share Event`}
                                </Button>

                                {event && <ShareModal
                                    event={event}
                                    opened={opened}
                                    onClose={close}
                                />}
                            </>
                        )}
                    </div>
                </div>
            </div>
            <div className={classes.main} id={'event-manage-main'}>
                <Outlet/>
            </div>
            <div className={classes.sidebar}>
                <div className={classes.logo}>
                    <NavLink to={'/manage/events'}>
                        <img style={{maxWidth: '120px', margin: "20px auto"}}
                             src={'/logo.svg'} alt={''}/>
                    </NavLink>
                </div>
                <div className={classes.nav}>
                    {links}
                </div>
                <UnstyledButton
                    className={classes.sidebarClose}
                    onClick={() => setSidebarOpen(!sidebarOpen)}>
                    <IconChevronLeft size={20}/>
                    <VisuallyHidden>{t`Close sidebar`}</VisuallyHidden>
                </UnstyledButton>
            </div>
            {sidebarOpen && <UnstyledButton
                className={classes.sidebarOpen}
                onClick={() => setSidebarOpen(!sidebarOpen)}>
                <IconLayoutSidebar size={16}/>
                <VisuallyHidden>{t`Open sidebar`}</VisuallyHidden>
            </UnstyledButton>}
        </div>
    )
}

export default EventLayout;
