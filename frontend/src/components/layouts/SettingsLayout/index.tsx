import {useEffect, useState} from "react";
import {Navigate, useLocation} from "react-router";
import {t} from "@lingui/macro";
import {
    IconAdjustmentsHorizontal,
    IconArrowLeft,
    IconBuildingStore,
    IconKey,
    IconReceiptTax,
    IconShieldLock,
    IconUser,
    IconUsers,
} from "@tabler/icons-react";
import AppLayout from "../AppLayout";
import {BreadcrumbItem, NavItem} from "../AppLayout/types.ts";
import {useAccess} from "../../../hooks/useAccess.ts";
import {safeSessionStorageGet, safeSessionStorageSet} from "../../../utilites/helpers.ts";

const SETTINGS_RETURN_PATH_KEY = 'hi-settings-return-path';

const DEFAULT_RETURN_PATH = '/manage/events';

const isSafeReturnPath = (path: unknown): path is string =>
    typeof path === 'string' && path.startsWith('/') && !path.startsWith('//') && !path.startsWith('/settings');

const useReturnPath = () => {
    const location = useLocation();
    const [returnPath, setReturnPath] = useState(DEFAULT_RETURN_PATH);

    useEffect(() => {
        const fromState = (location.state as { returnTo?: unknown } | null)?.returnTo;

        if (isSafeReturnPath(fromState)) {
            safeSessionStorageSet(SETTINGS_RETURN_PATH_KEY, fromState);
            setReturnPath(fromState);
            return;
        }

        const stored = safeSessionStorageGet(SETTINGS_RETURN_PATH_KEY);
        if (isSafeReturnPath(stored)) {
            setReturnPath(stored);
        }
    }, [location.state]);

    return returnPath;
};

const SettingsLayout = () => {
    const location = useLocation();
    const returnPath = useReturnPath();
    const {can, isLoaded, isRestricted} = useAccess();

    const navItems: NavItem[] = [
        {link: returnPath, label: t`Back`, icon: IconArrowLeft, isActive: () => false},
        {label: t`Personal`},
        {link: '/settings/profile', label: t`Profile`, icon: IconUser},
        {link: '/settings/security', label: t`Security`, icon: IconShieldLock},
        {label: t`Account`},
        {link: '/settings/account', label: t`General`, icon: IconAdjustmentsHorizontal, showWhen: () => !isRestricted},
        {link: '/settings/organizers', label: t`Organizers`, icon: IconBuildingStore, showWhen: () => !isRestricted},
        {link: '/settings/team', label: t`Team`, icon: IconUsers, showWhen: () => !isRestricted && can('MANAGE_TEAM')},
        {link: '/settings/api-keys', label: t`API keys`, icon: IconKey},
        {link: '/settings/taxes-and-fees', label: t`Taxes and fees`, icon: IconReceiptTax, showWhen: () => !isRestricted},
    ];

    const currentPage = navItems.find(item => item.link?.startsWith('/settings/') && location.pathname.startsWith(item.link));

    const breadcrumbItems: BreadcrumbItem[] = [
        {link: '/settings', content: t`Settings`},
        ...(currentPage ? [{link: currentPage.link, content: currentPage.label}] : []),
        ...(location.pathname.startsWith('/settings/api-keys/reference') ? [{link: '/settings/api-keys/reference', content: t`API reference`}] : []),
    ];

    if (isLoaded && currentPage?.showWhen && !currentPage.showWhen()) {
        return <Navigate to="/settings/profile" replace/>;
    }

    return (
        <AppLayout
            navItems={navItems}
            breadcrumbItems={breadcrumbItems}
            entityType="settings"
        />
    );
};

export default SettingsLayout;
