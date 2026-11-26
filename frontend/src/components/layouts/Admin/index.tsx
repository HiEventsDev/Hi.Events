import {IconUsers, IconBuildingBank, IconLayoutDashboard, IconCalendar, IconReceipt, IconSettings, IconChartBar, IconAlertTriangle, IconFlag, IconMail, IconMailHeart, IconSpeakerphone, IconTrash, IconToggleRight, IconLicense, IconMailCheck, IconMailForward} from "@tabler/icons-react";
import {t} from "@lingui/macro";
import {NavItem, BreadcrumbItem} from "../AppLayout/types";
import AppLayout from "../AppLayout";
import {useIsCurrentUserSuperAdmin} from "../../../hooks/useIsCurrentUserSuperAdmin.ts";
import {useGetAdminFeatureFlags} from "../../../queries/useGetAdminFeatureFlags.ts";
import {useGetAdminLifecycleEmails} from "../../../queries/useGetAdminLifecycleEmails.ts";
import {useNavigate} from "react-router";
import {useEffect} from "react";
import {useGetMe} from "../../../queries/useGetMe.ts";
import {FeatureFlag} from "../../../constants/featureFlags.ts";
import {useLicensedFeature} from "../../../ee/licensing/hooks/useLicensedFeature.ts";
import {useGetAccount} from "../../../queries/useGetAccount.ts";

const AdminLayout = () => {
    const isSuperAdmin = useIsCurrentUserSuperAdmin();
    const {data: featureFlags} = useGetAdminFeatureFlags(isSuperAdmin);
    const {isSuccess: lifecycleEmailsAvailable} = useGetAdminLifecycleEmails(isSuperAdmin);
    const {isFetched: isMeFetched} = useGetMe();
    const navigate = useNavigate();
    const audience = useLicensedFeature(FeatureFlag.CRM);
    const {data: account} = useGetAccount();
    const navItems: NavItem[] = [
        {label: t`Admin`},
        {link: '', label: t`Dashboard`, icon: IconLayoutDashboard},
        {link: 'accounts', label: t`Accounts`, icon: IconBuildingBank},
        {link: 'users', label: t`Users`, icon: IconUsers},
        {link: 'events', label: t`Events`, icon: IconCalendar},
        {link: 'orders', label: t`Orders`, icon: IconReceipt},
        {link: 'messages', label: t`Messages`, icon: IconMail},
        {link: 'audience-campaigns', label: t`Audience Campaigns`, icon: IconMailHeart, showWhen: () => !!account?.is_saas_mode_enabled && (!audience.isUnlicensed || audience.isLocked)},
        {link: 'spam-events', label: t`Flagged Events`, icon: IconFlag},
        {link: 'announcements', label: t`Announcements`, icon: IconSpeakerphone},
        {link: 'lifecycle-emails', label: t`Lifecycle Emails`, icon: IconMailForward, showWhen: () => lifecycleEmailsAvailable},
        {link: 'attribution', label: t`UTM Analytics`, icon: IconChartBar},
        {link: 'deletion-requests', label: t`Deletion Requests`, icon: IconTrash},
        {link: 'email-delivery', label: t`Email Delivery`, icon: IconMailCheck},
        {link: 'failed-jobs', label: t`Failed Jobs`, icon: IconAlertTriangle},
        {link: 'configurations', label: t`Configurations`, icon: IconSettings},
        {link: 'feature-flags', label: t`Feature Flags`, icon: IconToggleRight, showWhen: () => !!featureFlags?.data?.length},
        {link: 'licence', label: t`Licence`, icon: IconLicense},
    ];

    const breadcrumbItems: BreadcrumbItem[] = [
        {
            link: '/admin',
            content: t`Admin Dashboard`
        }
    ];

    useEffect(() => {
        if (isMeFetched && !isSuperAdmin) {
            navigate('/');
        }
    }, [isMeFetched, isSuperAdmin, navigate]);

    if (!isSuperAdmin) {
        return null;
    }

    return (
        <AppLayout
            navItems={navItems}
            breadcrumbItems={breadcrumbItems}
            entityType="organizer"
        />
    );
};

export default AdminLayout;
