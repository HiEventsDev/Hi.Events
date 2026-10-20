import React, {useEffect, useState} from "react";
import {Outlet} from "react-router";
import classes from './AppLayout.module.scss';
import {Topbar} from "./Topbar";
import {Sidebar} from "./Sidebar";
import {BreadcrumbItem, NavItem} from "./types.ts";
import {IconLayoutSidebar} from "@tabler/icons-react";
import {UnstyledButton, VisuallyHidden} from "@mantine/core";
import {t} from "@lingui/macro";
import ImpersonationBanner from "../../common/ImpersonationBanner";
import PendingDeletionBanner from "../../common/PendingDeletionBanner";
import AnnouncementDisplay from "../../common/AnnouncementDisplay";

interface AppLayoutProps {
    navItems: NavItem[];
    breadcrumbItems: BreadcrumbItem[];
    entityType: 'event' | 'organizer' | 'settings';
    topBarContent?: React.ReactNode;
    breadcrumbContentRight?: React.ReactNode;
    actionGroupContent?: React.ReactNode;
    sidebarFooter?: React.ReactNode;
}

interface SidebarToggleButtonProps {
    open: boolean;
    onClick: () => void;
}

const SidebarToggleButton: React.FC<SidebarToggleButtonProps> = ({open, onClick}) => {
    const Icon = IconLayoutSidebar;
    const label = t`Open sidebar`;

    return (
        <UnstyledButton
            className={open ? classes.sidebarOpen : classes.sidebarClose}
            onClick={onClick}
        >
            <Icon size={16}/>
            <VisuallyHidden>{label}</VisuallyHidden>
        </UnstyledButton>
    );
};

const AppLayout: React.FC<AppLayoutProps> = ({
                                                 navItems,
                                                 breadcrumbItems,
                                                 entityType,
                                                 topBarContent = null,
                                                 breadcrumbContentRight = null,
                                                 actionGroupContent = null,
                                                 sidebarFooter = null,
                                             }) => {
    const [sidebarOpen, setSidebarOpen] = useState<boolean | null>(null);
    const [topBarShadow, setTopBarShadow] = useState<boolean>(false);

    useEffect(() => {
        const mainElement = document.getElementById('app-manage-main');
        if (mainElement) {
            const handleScroll = () => {
                setTopBarShadow(mainElement.scrollTop > 10);
            };
            mainElement.addEventListener('scroll', handleScroll);
            return () => mainElement.removeEventListener('scroll', handleScroll);
        }
    }, []);

    useEffect(() => {
        const handleResize = () => setSidebarOpen(null);

        window.addEventListener('resize', handleResize);
        return () => window.removeEventListener('resize', handleResize);
    }, []);

    const sidebarStateClass = sidebarOpen === null ? '' : (sidebarOpen ? classes.open : classes.closed);

    const focusMainContent = (event: React.MouseEvent<HTMLAnchorElement>) => {
        event.preventDefault();
        document.getElementById('app-manage-main')?.focus();
    };

    return (
        <>
            <a href="#app-manage-main" className={classes.skipLink} onClick={focusMainContent}>
                {t`Skip to main content`}
            </a>
            <ImpersonationBanner />
            <PendingDeletionBanner />
            <AnnouncementDisplay />
            <div id={`${entityType}-manage-container`}
                 className={`${classes.container} ${sidebarStateClass}`}>
                <Topbar
                    sidebarOpen={sidebarOpen === true}
                    setSidebarOpen={setSidebarOpen}
                    topBarShadow={topBarShadow}
                    breadcrumbItems={breadcrumbItems}
                    topBarContent={topBarContent}
                    breadcrumbContentRight={breadcrumbContentRight}
                    actionGroupContent={actionGroupContent}
                />

            <Sidebar
                sidebarOpen={sidebarOpen}
                setSidebarOpen={setSidebarOpen}
                navItems={navItems}
                sidebarFooter={sidebarFooter}
            />

            <main className={classes.main} id={'app-manage-main'} tabIndex={-1}>
                <Outlet/>
            </main>

            {sidebarOpen && (
                <div
                    className={`${classes.overlay} ${sidebarOpen ? classes.open : ''}`}
                    onClick={() => setSidebarOpen(false)}
                />
            )}

            {sidebarOpen === false && (
                <SidebarToggleButton
                    open={false}
                    onClick={() => setSidebarOpen(true)}
                />
            )}
            </div>
        </>
    );
};

export default AppLayout;
