import {IconUsers, IconBuildingBank, IconLayoutDashboard, IconCalendar, IconReceipt, IconSettings} from "@tabler/icons-react";
import {t} from "@lingui/macro";
import {NavItem, BreadcrumbItem} from "../AppLayout/types";
import AppLayout from "../AppLayout";
import {useIsCurrentUserSuperAdmin} from "../../../hooks/useIsCurrentUserAdmin.ts";
import {useNavigate} from "react-router";

const AdminLayout = () => {
    const isSuperAdmin = useIsCurrentUserSuperAdmin();
    const navigate = useNavigate();
    const navItems: NavItem[] = [
        {label: t`Admin`},
        {link: '', label: t`Dashboard`, icon: IconLayoutDashboard},
        {link: 'accounts', label: t`Accounts`, icon: IconBuildingBank},
        {link: 'users', label: t`Users`, icon: IconUsers},
        {link: 'events', label: t`Events`, icon: IconCalendar},
        {link: 'orders', label: t`Orders`, icon: IconReceipt},
        {link: 'configurations', label: t`Configurations`, icon: IconSettings},
    ];

    const breadcrumbItems: BreadcrumbItem[] = [
        {
            link: '/admin',
            content: t`Admin Dashboard`
        }
    ];

    if (!isSuperAdmin) {
        navigate('/');
        return ;
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
