import React, {useEffect, useState} from "react";
import {Badge, UnstyledButton, VisuallyHidden} from '@mantine/core';
import {IconChevronDown, IconChevronLeft} from "@tabler/icons-react";
import {t} from "@lingui/macro";
import classes from './Sidebar.module.scss';
import {NavItem} from "../types";
import {matchPath, NavLink, useLocation, useResolvedPath} from "react-router";
import classNames from "classnames";
import {useMediaQuery} from "@mantine/hooks";
import {getConfig} from "../../../../utilites/config.ts";

interface SidebarProps {
    sidebarOpen: boolean | null;
    setSidebarOpen: (open: boolean) => void;
    navItems: NavItem[];
    sidebarFooter?: React.ReactNode;
}

export const Sidebar: React.FC<SidebarProps> = ({
                                                    sidebarOpen,
                                                    setSidebarOpen,
                                                    navItems,
                                                    sidebarFooter,
                                                }) => {
    const isMobile = useMediaQuery('(max-width: 767px)');

    const isHeading = (item: NavItem) => !item.link && item.link !== "" && item.onClick === undefined;

    const visibleItems = () => {
        const shown = navItems.filter(item => !item.showWhen || item.showWhen());

        return shown.filter((item, index) => {
            if (!isHeading(item)) {
                return true;
            }
            const next = shown[index + 1];
            return !!next && !isHeading(next);
        });
    };

    const closeOnMobile = () => {
        if (isMobile) {
            setSidebarOpen(false);
        }
    };

    const renderLink = (item: NavItem, isChild = false) => {
        if (item.loading) {
            return <a key={`loading-${item.link ?? item.label}`} className={classNames(classes.loading, classes.link)}>&nbsp;</a>;
        }

        return (
            <NavLink
                to={item.comingSoon ? '#' : (item.link ?? '#')}
                key={item.link ?? item.label}
                end={isChild}
                data-testid={item.testId}
                onClick={() => {
                    closeOnMobile();
                    if (item.onClick) item.onClick();
                }}
                className={({isActive}) => classNames(
                    isChild ? classes.childLink : classes.link,
                    {[classes.linkActive]: (item.isActive ? item.isActive(isActive) : isActive) && !item.comingSoon},
                )}
            >
                {item.icon && <item.icon size={20} className={classes.linkIcon} stroke={1.5}/>}
                <span>{item.label}</span>
                {item.badge !== undefined && (
                    <Badge
                        size="xs"
                        radius="xl"
                        className={item.badgeColor ? classes.navBadgeAlert : classes.navBadge}
                    >
                        {item.badge}
                    </Badge>
                )}
                {item.comingSoon &&
                    <Badge ml={'4px'} size={'xs'} className={classes.comingSoonBadge}>{t`Coming Soon`}</Badge>}
            </NavLink>
        );
    };

    const renderLinks = () => {
        return visibleItems().map((item) => {
            if (isHeading(item)) {
                return (
                    <div className={classes.sectionHeading} key={`heading-${item.label}`}>
                        {item.label}
                    </div>
                );
            }

            if (item.children && !item.loading) {
                return (
                    <NavGroup
                        key={item.link ?? item.label}
                        item={item}
                        renderLink={renderLink}
                    />
                );
            }

            return renderLink(item);
        });
    };

    return (
        <div
            id="app-manage-sidebar"
            className={classNames(classes.sidebar, {[classes.open]: sidebarOpen === true, [classes.closed]: sidebarOpen === false})}
        >
            <div className={classes.logo}>
                <NavLink to={`/manage/events`}>
                    <img
                        src={getConfig("VITE_APP_LOGO_LIGHT", "/logos/hi-events-text-dark.svg")}
                        alt={t`${getConfig("VITE_APP_NAME", "Hi.Events")} logo`}
                    />
                </NavLink>
            </div>
            <div className={classes.scrollArea}>
                <nav className={classes.nav} aria-label={t`Main navigation`}>
                    {renderLinks()}
                </nav>
                {sidebarFooter && (
                    <div className={classes.sidebarFooter}>
                        {sidebarFooter}
                    </div>
                )}
            </div>
            {sidebarOpen !== false && (
                <UnstyledButton
                    className={classes.sidebarClose}
                    onClick={() => setSidebarOpen(false)}>
                    <IconChevronLeft size={20}/>
                    <VisuallyHidden>{t`Close sidebar`}</VisuallyHidden>
                </UnstyledButton>
            )}
        </div>
    );
};

const NavGroup = ({item, renderLink}: {
    item: NavItem,
    renderLink: (item: NavItem, isChild?: boolean) => React.ReactNode,
}) => {
    const location = useLocation();
    const parentPath = useResolvedPath(item.link ?? '');
    const children = (item.children ?? []).filter(child => !child.showWhen || child.showWhen());
    const basePath = parentPath.pathname.slice(0, parentPath.pathname.length - (item.link ?? '').length);
    const isWithin = [item, ...children].some(link => link.link !== undefined
        && !!matchPath({path: link === item ? parentPath.pathname : basePath + link.link, end: false}, location.pathname));
    const [toggled, setToggled] = useState<boolean | null>(null);
    const isOpen = toggled ?? isWithin;

    useEffect(() => setToggled(null), [location.pathname]);
    const label = item.label;
    const groupId = `nav-group-${(item.link ?? item.label).replace(/[^a-z0-9]+/gi, '-')}`;

    return (
        <div className={classes.group} data-open={isOpen || undefined}>
            <div className={classes.groupRow}>
                {renderLink(item)}
                {children.length > 0 && (
                    <UnstyledButton
                        className={classes.groupToggle}
                        onClick={() => setToggled(!isOpen)}
                        aria-expanded={isOpen}
                        aria-controls={groupId}
                        aria-label={isOpen ? t`Collapse ${label}` : t`Expand ${label}`}
                    >
                        <IconChevronDown size={14} stroke={2}/>
                    </UnstyledButton>
                )}
            </div>
            {isOpen && children.length > 0 && (
                <div className={classes.children} id={groupId}>
                    {children.map(child => renderLink(child, true))}
                </div>
            )}
        </div>
    );
};
