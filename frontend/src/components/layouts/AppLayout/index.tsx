import React, {useEffect, useState} from "react";
import {Outlet} from "react-router";
import classes from './AppLayout.module.scss';
import {Topbar} from "./Topbar";
import {Sidebar} from "./Sidebar";
import {BreadcrumbItem, NavItem} from "./types.ts";
import {IconLayoutSidebar} from "@tabler/icons-react";
import {UnstyledButton, VisuallyHidden} from "@mantine/core";
import {t} from "@lingui/macro";

interface AppLayoutProps {
    navItems: NavItem[];
    breadcrumbItems: BreadcrumbItem[];
    entityType: 'event' | 'organizer';
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
    const [sidebarOpen, setSidebarOpen] = useState<boolean>(() => {
        if (typeof window === 'undefined') return true; // SSR default
        return window.innerWidth >= 768; // Desktop open, mobile closed
    });
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
        const handleResize = () => {
            if (window.innerWidth >= 768) {
                setSidebarOpen(true);
            } else {
                setSidebarOpen(false);
            }
        };

        window.addEventListener('resize', handleResize);
        return () => window.removeEventListener('resize', handleResize);
    }, []);

    return (
        <div id={`${entityType}-manage-container`}
             className={`${classes.container} ${sidebarOpen ? classes.open : classes.closed}`}>
            <Topbar
                sidebarOpen={sidebarOpen}
                setSidebarOpen={setSidebarOpen}
                topBarShadow={topBarShadow}
                breadcrumbItems={breadcrumbItems}
                topBarContent={topBarContent}
                breadcrumbContentRight={breadcrumbContentRight}
                actionGroupContent={actionGroupContent}
            />

            <div className={classes.main} id={'app-manage-main'}>
                <Outlet/>
            </div>

            <Sidebar
                sidebarOpen={sidebarOpen}
                setSidebarOpen={setSidebarOpen}
                navItems={navItems}
                sidebarFooter={sidebarFooter}
            />

            {sidebarOpen && (
                <div
                    className={`${classes.overlay} ${sidebarOpen ? classes.open : ''}`}
                    onClick={() => setSidebarOpen(false)}
                />
            )}

            {!sidebarOpen && (
                <SidebarToggleButton
                    open={false}
                    onClick={() => setSidebarOpen(true)}
                />
            )}
        </div>
    );
};

export default AppLayout;
