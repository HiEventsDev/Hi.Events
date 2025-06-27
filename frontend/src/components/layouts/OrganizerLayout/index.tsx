import {
    IconArrowsHorizontal,
    IconBrandStripe,
    IconCalendar,
    IconCalendarPlus,
    IconChevronRight,
    IconCreditCard,
    IconDashboard,
    IconExternalLink,
    IconEye,
    IconEyeOff,
    IconPaint,
    IconSettings,
    IconShare,
    IconUsersGroup
} from "@tabler/icons-react";
import {t} from "@lingui/macro";
import {BreadcrumbItem, NavItem} from "../AppLayout/types.ts";
import AppLayout from "../AppLayout";
import {NavLink, useParams} from "react-router";
import {Button, Modal, Text, Stack} from "@mantine/core";
import {useGetOrganizer} from "../../../queries/useGetOrganizer.ts";
import {useState} from "react";
import {CreateEventModal} from "../../modals/CreateEventModal";
import {TopBarButton} from "../../common/TopBarButton";
import classes from "./OrganizerLayout.module.scss";
import {CalloutConfig, SidebarCalloutQueue} from "../../common/SidebarCallout/SidebarCalloutQueue";
import {InviteUserModal} from "../../modals/InviteUserModal";
import {useDisclosure, useMediaQuery} from "@mantine/hooks";
import {SwitchOrganizerModal} from "../../modals/SwitchOrganizerModal";
import {useGetOrganizers} from "../../../queries/useGetOrganizers.ts";
import {useGetAccount} from "../../../queries/useGetAccount.ts";
import {StripeConnectButton} from "../../common/StripeConnectButton";
import {ShareModal} from "../../modals/ShareModal";
import {organizerHomepageUrl} from "../../../utilites/urlHelper";
import {useUpdateOrganizerStatus} from "../../../mutations/useUpdateOrganizerStatus.ts";
import {confirmationDialog} from "../../../utilites/confirmationDialog.tsx";
import {showError, showSuccess} from "../../../utilites/notifications.tsx";
import {useResendEmailConfirmation} from "../../../mutations/useResendEmailConfirmation.ts";
import {useGetMe} from "../../../queries/useGetMe.ts";

const OrganizerLayout = () => {
    const {organizerId} = useParams();
    const {data: organizer} = useGetOrganizer(organizerId);
    const [showCreateEventModal, setShowCreateEventModal] = useState(false);
    const [createModalOpen, {open: openCreateModal, close: closeCreateModal}] = useDisclosure(false);
    const [switchOrganizerModalOpen, {open: openSwitchModal, close: closeSwitchModal}] = useDisclosure(false);
    const [shareModalOpen, {open: openShareModal, close: closeShareModal}] = useDisclosure(false);
    const [emailVerificationModalOpen, {open: openEmailVerificationModal, close: closeEmailVerificationModal}] = useDisclosure(false);
    const {data: organizerResposne} = useGetOrganizers();
    const organizers = organizerResposne?.data;
    const {data: account} = useGetAccount();
    const resendEmailConfirmationMutation = useResendEmailConfirmation();
    const [emailConfirmationResent, setEmailConfirmationResent] = useState(false);
    const {data: me} = useGetMe();
    const isUserEmailVerfied = me?.is_email_verified;
    const isMobile = useMediaQuery('(max-width: 768px)');

    const statusToggleMutation = useUpdateOrganizerStatus();

    const navItems: NavItem[] = [
        {
            label: t`Switch Organizer`,
            icon: IconArrowsHorizontal,
            onClick: openSwitchModal,
            isActive: () => false,
            showWhen: () => organizers && organizers.length > 1,
        },
        {label: 'Overview'},
        {link: 'dashboard', label: t`Organizer Dashboard`, icon: IconDashboard},

        {label: t`Manage`},
        {link: 'events', label: t`Events`, icon: IconCalendar},
        {link: 'settings', label: t`Settings`, icon: IconSettings},

        {label: t`Tools`},
        {link: 'organizer-homepage-designer', label: t`Homepage Designer`, icon: IconPaint},
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
        // Check if user email is verified
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
            link: '/manage/events',
            content: t`Events`
        },
        {
            link: `/manage/organizer/${organizerId}`,
            content: organizer?.name,
        },
        {
            content: (
                <span 
                    className={classes.createEventBreadcrumb}
                    onClick={() => setShowCreateEventModal(true)}
                >
                    <IconCalendarPlus size={16} /> {t`Create Event`}
                </span>
            ),
        }
    ];

    const callouts: CalloutConfig[] = [
        {
            icon: <IconUsersGroup size={20}/>,
            heading: t`Invite Your Team`,
            description: t`Collaborate with your team to create amazing events together.`,
            buttonIcon: <IconUsersGroup size={16}/>,
            buttonText: t`Invite Team Members`,
            onClick: () => {
                openCreateModal();
            },
            storageKey: `organizer-${organizerId}-team-callout-dismissed`
        },
    ];

    if (account && !account?.stripe_connect_setup_complete) {
        callouts.unshift({
            icon: <IconBrandStripe size={20}/>,
            heading: t`Connect Stripe`,
            description: t`Connect your Stripe account to accept payments for tickets and products.`,
            storageKey: `stripe-callout-dismissed`,
            customButton:
                <StripeConnectButton
                    fullWidth
                    variant="white"
                    buttonIcon={<IconCreditCard size={16}/>}
                    buttonText={t`Connect Stripe`}
                    className={classes.calloutButton}
                />
        });
    }

    return (
        <>
            <AppLayout
                navItems={navItemsWithLoading}
                breadcrumbItems={breadcrumbItems}
                entityType="organizer"
                topBarContent={(
                    <div className={classes.statusToggleContainer}>
                        {organizer && (
                            <TopBarButton
                                onClick={handleStatusToggle}
                                size="sm"
                                leftSection={organizer?.status === 'DRAFT' ? <IconEyeOff size={16}/> : <IconEye size={16}/>}
                                rightSection={<IconChevronRight size={14}/>}
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
                                    leftSection={<IconShare size={16}/>}
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
                        leftSection={<IconExternalLink size={17}/>}
                        title={t`View Organizer Homepage`}
                    >
                        <span className={classes.viewHomepageButtonTextDesktop}>
                            {t`View Organizer Homepage`}
                        </span>
                    </Button>
                )}
                sidebarFooter={<SidebarCalloutQueue callouts={callouts}/>}
            />

            {createModalOpen && <InviteUserModal onClose={closeCreateModal}/>}
            {switchOrganizerModalOpen &&
                <SwitchOrganizerModal opened={switchOrganizerModalOpen} onClose={closeSwitchModal}/>}
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
        </>
    );
};

export default OrganizerLayout;
