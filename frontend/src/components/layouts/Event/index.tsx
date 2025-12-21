import {
    IconArrowLeft,
    IconChartPie,
    IconChevronRight,
    IconDashboard,
    IconDeviceTabletCode,
    IconDiscount2,
    IconExternalLink,
    IconEye,
    IconEyeOff,
    IconMailCheck,
    IconMailForward,
    IconPaint,
    IconQrcode,
    IconReceipt,
    IconSend,
    IconSettings,
    IconShare,
    IconStar,
    IconTicket,
    IconTrendingUp,
    IconUserQuestion,
    IconUsers,
    IconUsersGroup,
    IconWebhook
} from "@tabler/icons-react";
import {t} from "@lingui/macro";
import {useGetEvent} from "../../../queries/useGetEvent";
import {useGetEventSettings} from "../../../queries/useGetEventSettings";
import {useGetEventStats} from "../../../queries/useGetEventStats";
import Truncate from "../../common/Truncate";
import {BreadcrumbItem, NavItem} from "../AppLayout/types.ts";
import AppLayout from "../AppLayout";
import {NavLink, useLocation, useParams} from "react-router";
import classes from './Event.module.scss';
import {Button} from "@mantine/core";
import {confirmationDialog} from "../../../utilites/confirmationDialog.tsx";
import {useUpdateEventStatus} from "../../../mutations/useUpdateEventStatus.ts";
import {showError, showSuccess} from "../../../utilites/notifications.tsx";
import {ShareModal} from "../../modals/ShareModal";
import {useDisclosure} from "@mantine/hooks";
import {TopBarButton} from "../../common/TopBarButton";
import {useWindowWidth} from "../../../hooks/useWindowWidth.ts";
import {SidebarCallout} from "../../common/SidebarCallout";
import {useGetMe} from "../../../queries/useGetMe.ts";
import {useResendEmailConfirmation} from "../../../mutations/useResendEmailConfirmation.ts";
import {useState} from "react";
import {eventHomepageUrl} from "../../../utilites/urlHelper.ts";

const EventLayout = () => {
    const location = useLocation();
    const {eventId} = useParams();

    const [opened, {open, close}] = useDisclosure(false);

    const statusToggleMutation = useUpdateEventStatus();

    const {data: event, isFetched: isEventFetched} = useGetEvent(eventId);
    const {data: eventSettings, isFetched: isEventSettingsFetched} = useGetEventSettings(eventId);
    const {data: eventStats} = useGetEventStats(eventId);
    const {data: me} = useGetMe();

    const resendEmailConfirmationMutation = useResendEmailConfirmation();
    const [emailConfirmationResent, setEmailConfirmationResent] = useState(false);

    const handleEmailConfirmationResend = () => {
        resendEmailConfirmationMutation.mutate({
            userId: me?.id
        }, {
            onSuccess: () => {
                showSuccess(t`Email confirmation resent successfully`);
                setEmailConfirmationResent(true);
            },
            onError: () => {
                showError(t`Something went wrong. Please try again.`);
            }
        })
    }

    const navItems: NavItem[] = [
        {link: '/manage/organizer/' + event?.organizer?.id, label: t`Organizer Dashboard`, icon: IconArrowLeft},
        {label: t`Overview`},
        {
            link: 'getting-started',
            label: t`Getting Started`,
            icon: IconStar,
            showWhen: () => !eventSettings?.hide_getting_started_page
        },
        {link: 'dashboard', label: t`Dashboard`, icon: IconDashboard},
        {
            link: 'reports',
            label: t`Reports`,
            icon: IconChartPie,
            isActive: (isActive) => isActive || location.pathname.includes('/report/')
        },

        {label: t`Manage`},
        {link: 'settings', label: t`Settings`, icon: IconSettings},
        {link: 'attendees', label: t`Attendees`, icon: IconUsers, badge: eventStats?.total_attendees_registered},
        {link: 'orders', label: t`Orders`, icon: IconReceipt, badge: eventStats?.total_orders},
        {link: 'products', label: t`Tickets & Products`, icon: IconTicket},
        {link: 'questions', label: t`Questions`, icon: IconUserQuestion},
        {link: 'capacity-assignments', label: t`Capacity`, icon: IconUsersGroup},
        {link: 'check-in', label: t`Check-In Lists`, icon: IconQrcode},
        {link: 'messages', label: t`Messages`, icon: IconSend},
        {link: 'promo-codes', label: t`Promo Codes`, icon: IconDiscount2},
        {link: 'affiliates', label: t`Affiliates`, icon: IconTrendingUp},

        {label: t`Tools`},
        {link: 'homepage-designer', label: t`Homepage Designer`, icon: IconPaint},
        {link: 'ticket-designer', label: t`Ticket Design`, icon: IconTicket},
        {link: 'widget', label: t`Widget Embed`, icon: IconDeviceTabletCode},
        {link: 'webhooks', label: t`Webhooks`, icon: IconWebhook},
    ];

    const navItemsWithLoading = !isEventSettingsFetched || !isEventFetched
        ? navItems.map(item => item.link ? {...item, loading: true} : item)
        : navItems;

    const screenWidth = useWindowWidth();
    const breadcrumbItemsWidth = screenWidth > 1100 ? 60 : 23;

    const breadcrumbItems: BreadcrumbItem[] = [
        {
            link: '/manage/events',
            content: t`Home`
        },
        ...(isEventFetched ? [
            {
                link: `/manage/organizer/${event?.organizer?.id}`,
                content: <Truncate length={breadcrumbItemsWidth} text={event?.organizer?.name} showTooltip={false}/>
            },
            {
                link: `/manage/event/${event?.id}`,
                content: <Truncate length={breadcrumbItemsWidth} text={event?.title} showTooltip={false}/>
            }
        ] : [
            {link: '#', content: '...'}
        ])
    ];

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

    return (
        <AppLayout
            navItems={navItemsWithLoading}
            breadcrumbItems={breadcrumbItems}
            entityType="event"
            topBarContent={(
                <div className={classes.statusToggleContainer}>
                    {isEventFetched && (
                        <TopBarButton
                            onClick={handleStatusToggle}
                            size="sm"
                            leftSection={event?.status === 'DRAFT' ? <IconEyeOff size={16}/> : <IconEye size={16}/>}
                            rightSection={<IconChevronRight size={14}/>}
                        >
                            {event?.status === 'DRAFT'
                                ? <span>{t`Draft`} <span
                                    className={classes.statusAction}>{t`- Click to Publish`}</span></span>
                                : <span>{t`Live`} <span
                                    className={classes.statusAction}>{t`- Click to Unpublish`}</span></span>
                            }
                        </TopBarButton>
                    )}
                </div>
            )}
            breadcrumbContentRight={(
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
                                url={eventHomepageUrl(event)}
                                title={event.title}
                                modalTitle={t`Share Event`}
                                opened={opened}
                                onClose={close}
                            />}
                        </>
                    )}
                </div>
            )}
            actionGroupContent={(
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
            )}
            sidebarFooter={
                (me && !me.is_email_verified && !emailConfirmationResent ? (
                    <SidebarCallout
                        icon={<IconMailCheck size={20}/>}
                        heading={t`Verify your email`}
                        description={t`Confirm your email to access all features.`}
                        buttonIcon={<IconMailForward size={16}/>}
                        buttonText={t`Resend email`}
                        onClick={() => handleEmailConfirmationResend()}
                    />
                ) : null)
            }
        />
    );
};

export default EventLayout;
