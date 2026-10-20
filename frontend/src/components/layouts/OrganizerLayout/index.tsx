import {
    IconArrowLeft,
    IconArrowsHorizontal,
    IconBrandStripe,
    IconCalendar,
    IconCalendarPlus,
    IconChartPie,
    IconChevronRight,
    IconDashboard,
    IconDeviceTabletCode,
    IconExternalLink,
    IconEye,
    IconEyeOff,
    IconArmchair,
    IconMapPin,
    IconMicrophone2,
    IconPaint,
    IconSettings,
    IconShare,
    IconUsersGroup,
    IconWebhook
} from "@tabler/icons-react";
import { useAccess } from "../../../hooks/useAccess.ts";
import { t } from "@lingui/macro";
import { BreadcrumbItem, NavItem } from "../AppLayout/types.ts";
import AppLayout from "../AppLayout";
import { NavLink, useLocation, useParams } from "react-router";
import { Badge, Button, Modal, Stack, Text } from "@mantine/core";
import { useGetOrganizer } from "../../../queries/useGetOrganizer.ts";
import { useGetOrganizerSettings } from "../../../queries/useGetOrganizerSettings.ts";
import { OrganizerDatesProvider } from "../../common/OrganizerDatesProvider";
import { useGeoStatus } from "../../../queries/useGeoStatus.ts";
import { useState } from "react";
import { CreateEventModal } from "../../modals/CreateEventModal";
import { TopBarButton } from "../../common/TopBarButton";
import classes from "./OrganizerLayout.module.scss";
import { CalloutConfig, SidebarCalloutQueue } from "../../common/SidebarCallout/SidebarCalloutQueue";
import { InviteUserModal } from "../../modals/InviteUserModal";
import { useDisclosure, useMediaQuery } from "@mantine/hooks";
import { SwitchOrganizerModal } from "../../modals/SwitchOrganizerModal";
import { CreateOrganizerModal } from "../../modals/CreateOrganizerModal";
import { useGetOrganizers } from "../../../queries/useGetOrganizers.ts";
import { useGetAccount } from "../../../queries/useGetAccount.ts";
import { useLicensedFeature } from "../../../ee/licensing/hooks/useLicensedFeature.ts";
import { FeatureFlag } from "../../../constants/featureFlags.ts";
import { ShareModal } from "../../modals/ShareModal";
import { organizerHomepageUrl } from "../../../utilites/urlHelper";
import { useUpdateOrganizerStatus } from "../../../mutations/useUpdateOrganizerStatus.ts";
import { confirmationDialog } from "../../../utilites/confirmationDialog.tsx";
import { showError, showSuccess } from "../../../utilites/notifications.tsx";
import { useResendEmailConfirmation } from "../../../mutations/useResendEmailConfirmation.ts";
import { useGetMe } from "../../../queries/useGetMe.ts";
import { GenericErrorPage } from "../../common/GenericErrorPage";

const OrganizerLayout = () => {
    const { organizerId } = useParams();
    const location = useLocation();
    const { data: organizer } = useGetOrganizer(organizerId);
    const { data: organizerSettings } = useGetOrganizerSettings(organizerId);
    useGeoStatus();
    const [showCreateEventModal, setShowCreateEventModal] = useState(false);
    const [showCreateOrganizerModal, setShowCreateOrganizerModal] = useState(false);
    const [createModalOpen, { open: openCreateModal, close: closeCreateModal }] = useDisclosure(false);
    const [switchOrganizerModalOpen, { open: openSwitchModal, close: closeSwitchModal }] = useDisclosure(false);
    const [shareModalOpen, { open: openShareModal, close: closeShareModal }] = useDisclosure(false);
    const [emailVerificationModalOpen, {
        open: openEmailVerificationModal,
        close: closeEmailVerificationModal
    }] = useDisclosure(false);
    const { data: organizerResposne } = useGetOrganizers();
    const { canManageOrganizer, can, isReadOnly, isLoaded: isAccessLoaded } = useAccess();
    const organizers = organizerResposne?.data?.filter(org => canManageOrganizer(org.id));
    const { data: account } = useGetAccount();
    const seating = useLicensedFeature(FeatureFlag.SEATING);
    const resendEmailConfirmationMutation = useResendEmailConfirmation();
    const [emailConfirmationResent, setEmailConfirmationResent] = useState(false);
    const { data: me } = useGetMe();
    const isUserEmailVerfied = me?.is_email_verified;
    const isMobile = useMediaQuery('(max-width: 768px)');

    const statusToggleMutation = useUpdateOrganizerStatus();

    const isStripeConnected = !!organizer?.stripe_connect_setup_complete;
    const showPayoutsSection = !!account?.is_saas_mode_enabled;

    const navItems: NavItem[] = [
        {
            label: t`Switch Organizer`,
            icon: IconArrowsHorizontal,
            onClick: openSwitchModal,
            isActive: () => false,
            showWhen: () => organizers && organizers.length > 1,
        },
        ...(showPayoutsSection && !isStripeConnected && can('MANAGE_PAYMENTS') ? [
            { label: t`Get Paid` },
            {
                link: 'settings#payouts',
                label: t`Set up payouts`,
                icon: IconBrandStripe,
                isActive: () => false,
            },
        ] as NavItem[] : []),
        { label: 'Overview' },
        {
            link: 'dashboard',
            label: t`Organizer Dashboard`,
            icon: IconDashboard,
            isActive: (isActive) => isActive || /\/manage\/organizer(\/[^/]+)?\/?$/.test(location.pathname),
        },
        {
            link: 'reports',
            label: t`Reports`,
            icon: IconChartPie,
            isActive: (isActive) => isActive || location.pathname.includes('/report/')
        },

        { label: t`Manage` },
        { link: 'events', label: t`Events`, icon: IconCalendar },
        { link: 'settings', label: t`Settings`, icon: IconSettings, showWhen: () => !isReadOnly },

        { label: t`Tools` },
        { link: 'organizer-homepage-designer', label: t`Homepage Designer`, icon: IconPaint, showWhen: () => !isReadOnly },
        { link: 'widget', label: t`Widget Embed`, icon: IconDeviceTabletCode },

        { label: t`Library` },
        { link: 'locations', label: t`Locations`, icon: IconMapPin },
        { link: 'people', label: t`People`, icon: IconMicrophone2 },
        { link: 'seat-maps', label: t`Seat Maps`, icon: IconArmchair, showWhen: () => !isReadOnly && seating.isVisible },

        { label: t`Integrations` },
        { link: 'webhooks', label: t`Webhooks`, icon: IconWebhook, showWhen: () => can('VIEW_FINANCIALS') },

        { label: t`Team` },
        { link: 'team', label: t`Team Access`, icon: IconUsersGroup, showWhen: () => can('MANAGE_TEAM') },
    ];

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

    const navItemsWithLoading: NavItem[] = navItems.map(item => {
        if (!organizer) {
            return {
                ...item,
                loading: true
            };
        }
        return item;
    });

    const handleStatusToggle = () => {
        if (!isUserEmailVerfied) {
            openEmailVerificationModal();
            return;
        }

        const message = organizer?.status === 'LIVE'
            ? t`Are you sure you want to make this organizer draft? This will make the organizer page invisible to the public`
            : t`Are you sure you want to make this organizer public? This will make the organizer page visible to the public`;

        confirmationDialog(message, () => {
            statusToggleMutation.mutate({
                organizerId,
                status: organizer?.status === 'LIVE' ? 'DRAFT' : 'LIVE'
            }, {
                onSuccess: () => {
                    showSuccess(t`Organizer status updated`);
                },
                onError: (error: any) => {
                    showError(error?.response?.data?.message || t`Organizer status update failed. Please try again later`);
                }
            });
        });
    };

    const breadcrumbItems: BreadcrumbItem[] = [
        {
            link: `/manage/organizer/${organizerId}`,
            content: organizer?.name,
        },
        ...(isReadOnly ? [] : [{
            content: (
                <span
                    className={classes.createEventBreadcrumb}
                    onClick={() => setShowCreateEventModal(true)}
                >
                    <IconCalendarPlus size={16} /> {t`Create Event`}
                </span>
            ),
        }]),
    ];

    const callouts: CalloutConfig[] = !can('MANAGE_TEAM') ? [] : [
        {
            icon: <IconUsersGroup size={20} />,
            heading: t`Invite Your Team`,
            description: t`Collaborate with your team to create amazing events together.`,
            buttonIcon: <IconUsersGroup size={16} />,
            buttonText: t`Invite Team Members`,
            onClick: () => {
                openCreateModal();
            },
            storageKey: `organizer-${organizerId}-team-callout-dismissed`
        },
    ];

    if (isAccessLoaded && !canManageOrganizer(organizerId)) {
        return (
            <GenericErrorPage
                title={t`You don't have access to this organizer`}
                description={t`Ask an account admin to give you access, or open one of your own events.`}
                buttonText={t`Go to your events`}
                buttonUrl="/manage/events"
                buttonIcon={<IconArrowLeft size={18}/>}
            />
        );
    }

    return (
        <OrganizerDatesProvider firstDayOfWeek={organizerSettings?.first_day_of_week}>
            <AppLayout
                navItems={navItemsWithLoading}
                breadcrumbItems={breadcrumbItems}
                entityType="organizer"
                topBarContent={(
                    <div className={classes.statusToggleContainer}>
                        {isReadOnly && <Badge variant="white" color="dark" data-testid="view-only-badge">{t`View only`}</Badge>}
                        {organizer && !isReadOnly && (
                            <TopBarButton
                                onClick={handleStatusToggle}
                                size="sm"
                                leftSection={organizer?.status === 'DRAFT' ? <IconEyeOff size={16} /> :
                                    <IconEye size={16} />}
                                rightSection={<IconChevronRight size={14} />}
                            >
                                {organizer?.status === 'DRAFT'
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
                    <>
                        {organizer && !isMobile && (
                            <>
                                <Button
                                    onClick={openShareModal}
                                    variant="transparent"
                                    leftSection={<IconShare size={16} />}
                                >
                                    <span className={classes.shareButtonTextDesktop}>
                                        {t`Share Organizer Page`}
                                    </span>
                                    <span className={classes.shareButtonTextMobile}>
                                        {t`Share`}
                                    </span>
                                </Button>
                            </>
                        )}
                    </>
                )}
                actionGroupContent={(
                    <Button
                        component={NavLink}
                        to={`/events/${organizerId}/${organizer?.slug || ''}`}
                        target={'_blank'}
                        variant={'transparent'}
                        className={classes.viewHomepageButton}
                        leftSection={<IconExternalLink size={17} />}
                        title={t`View Organizer Homepage`}
                    >
                        <span className={classes.viewHomepageButtonTextDesktop}>
                            {t`View Organizer Homepage`}
                        </span>
                    </Button>
                )}
                sidebarFooter={<SidebarCalloutQueue callouts={callouts} />}
            />

            {createModalOpen && <InviteUserModal onClose={closeCreateModal} />}
            {switchOrganizerModalOpen &&
                <SwitchOrganizerModal
                    opened={switchOrganizerModalOpen}
                    onClose={closeSwitchModal}
                    onCreateOrganizer={() => setShowCreateOrganizerModal(true)}
                />}
            {showCreateOrganizerModal && (
                <CreateOrganizerModal onClose={() => setShowCreateOrganizerModal(false)} />
            )}
            {organizer && shareModalOpen && (
                <ShareModal
                    url={organizerHomepageUrl(organizer)}
                    title={organizer.name}
                    modalTitle={t`Share Organizer Page`}
                    opened={shareModalOpen}
                    onClose={closeShareModal}
                />
            )}
            {showCreateEventModal && (
                <CreateEventModal
                    onClose={() => setShowCreateEventModal(false)}
                    organizerId={organizerId}
                />
            )}

            <Modal
                opened={emailVerificationModalOpen}
                onClose={closeEmailVerificationModal}
                title={t`Email Verification Required`}
                size="md"
                centered
            >
                <Stack gap="md">
                    <Text size="sm" c="dimmed">
                        {t`You must verify your email address before you can update the organizer status.`}
                    </Text>

                    {!emailConfirmationResent ? (
                        <Button
                            variant="light"
                            onClick={() => {
                                handleEmailConfirmationResend();
                            }}
                            loading={resendEmailConfirmationMutation.isPending}
                        >
                            {t`Resend Confirmation Email`}
                        </Button>
                    ) : (
                        <Text size="sm">
                            {t`Confirmation email sent! Please check your inbox.`}
                        </Text>
                    )}
                </Stack>
            </Modal>
        </OrganizerDatesProvider>
    );
};

export default OrganizerLayout;
