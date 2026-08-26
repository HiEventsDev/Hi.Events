import {Fragment, ReactNode, useCallback, useEffect, useRef, useState} from "react";
import {t} from "@lingui/macro";
import {ActionIcon, Button, Menu, Tooltip} from "@mantine/core";
import {useIsomorphicEffect} from "@mantine/hooks";
import {IconDotsVertical} from "@tabler/icons-react";
import {ActionMenuItemsGroup} from "../ActionMenu";
import classes from "./EntityActions.module.scss";

export type EntityActionGroup = 'primary' | 'secondary' | 'danger';

export interface EntityAction {
    key: string;
    label: string;
    icon: ReactNode;
    onClick: () => void;
    group: EntityActionGroup;
    color?: string;
    dataTestId?: string;
}

const byGroup = (actions: EntityAction[], group: EntityActionGroup) => actions.filter(action => action.group === group);

export const toActionMenuGroups = (actions: EntityAction[]): ActionMenuItemsGroup[] => {
    const danger = byGroup(actions, 'danger');

    return [
        {
            label: t`Actions`,
            items: [...byGroup(actions, 'primary'), ...byGroup(actions, 'secondary')],
        },
        ...(danger.length > 0 ? [{
            label: t`Danger Zone`,
            items: danger,
            showDividerAbove: true,
        }] : []),
    ];
};

export const EntityActionMenuItems = ({actions}: { actions: EntityAction[] }) => {
    const danger = byGroup(actions, 'danger');

    return (
        <>
            <Menu.Label>{t`Manage`}</Menu.Label>
            {[...byGroup(actions, 'primary'), ...byGroup(actions, 'secondary')].map(action => (
                <Menu.Item
                    key={action.key}
                    leftSection={action.icon}
                    onClick={action.onClick}
                    color={action.color}
                >
                    {action.label}
                </Menu.Item>
            ))}
            {danger.length > 0 && (
                <>
                    <Menu.Divider/>
                    <Menu.Label>{t`Danger zone`}</Menu.Label>
                    {danger.map(action => (
                        <Menu.Item
                            key={action.key}
                            leftSection={action.icon}
                            onClick={action.onClick}
                            color={action.color}
                        >
                            {action.label}
                        </Menu.Item>
                    ))}
                </>
            )}
        </>
    );
};

const MENU_BUTTON_WIDTH = 34;

export const EntityActionBar = ({actions}: { actions: EntityAction[] }) => {
    const primary = byGroup(actions, 'primary');
    const secondary = byGroup(actions, 'secondary');
    const danger = byGroup(actions, 'danger');

    const barRef = useRef<HTMLDivElement>(null);
    const buttonWidths = useRef<Record<string, number>>({});
    const [visibleCount, setVisibleCount] = useState(primary.length);
    const primaryKeys = primary.map(action => action.key).join('|');

    const fitPrimaryActions = useCallback(() => {
        const bar = barRef.current;
        if (!bar) {
            return;
        }

        bar.querySelectorAll<HTMLElement>('[data-action-key]').forEach(element => {
            buttonWidths.current[element.dataset.actionKey as string] = element.offsetWidth;
        });

        if (primary.some(action => buttonWidths.current[action.key] === undefined)) {
            setVisibleCount(primary.length);
            return;
        }

        const gap = parseFloat(getComputedStyle(bar).columnGap) || 0;
        const available = bar.clientWidth;
        const widthOf = (count: number) => primary
            .slice(0, count)
            .reduce((total, action, index) => total + buttonWidths.current[action.key] + (index > 0 ? gap : 0), 0);

        let count = primary.length;
        while (count > 0) {
            const hasMenu = count < primary.length || secondary.length > 0 || danger.length > 0;
            const required = widthOf(count) + (hasMenu ? gap + MENU_BUTTON_WIDTH : 0);
            if (required <= available) {
                break;
            }
            count--;
        }

        setVisibleCount(count);
    }, [primaryKeys, secondary.length, danger.length]);

    useIsomorphicEffect(() => {
        fitPrimaryActions();
    }, [fitPrimaryActions, visibleCount]);

    useEffect(() => {
        const bar = barRef.current;
        if (!bar) {
            return;
        }
        const observer = new ResizeObserver(fitPrimaryActions);
        observer.observe(bar);
        return () => observer.disconnect();
    }, [fitPrimaryActions]);

    const visiblePrimary = primary.slice(0, visibleCount);
    const overflowPrimary = primary.slice(visibleCount);
    const menuSections = [overflowPrimary, secondary, danger].filter(section => section.length > 0);

    return (
        <div className={classes.bar} ref={barRef}>
            {visiblePrimary.map(action => (
                <Button
                    key={action.key}
                    variant="default"
                    size="compact-sm"
                    color={action.color}
                    leftSection={action.icon}
                    onClick={action.onClick}
                    data-testid={action.dataTestId}
                    data-action-key={action.key}
                >
                    {action.label}
                </Button>
            ))}

            {menuSections.length > 0 && (
                <Menu shadow="md" width={210} position="bottom-end">
                    <Menu.Target>
                        <Tooltip label={t`More actions`} withArrow position="bottom">
                            <ActionIcon variant="subtle" color="gray" size="lg" radius="xl">
                                <IconDotsVertical size={16}/>
                            </ActionIcon>
                        </Tooltip>
                    </Menu.Target>
                    <Menu.Dropdown>
                        {menuSections.map((section, sectionIndex) => (
                            <Fragment key={section[0].key}>
                                {sectionIndex > 0 && <Menu.Divider/>}
                                {section.map(action => (
                                    <Menu.Item
                                        key={action.key}
                                        leftSection={action.icon}
                                        onClick={action.onClick}
                                        color={action.color}
                                        data-testid={overflowPrimary.includes(action) ? action.dataTestId : undefined}
                                    >
                                        {action.label}
                                    </Menu.Item>
                                ))}
                            </Fragment>
                        ))}
                    </Menu.Dropdown>
                </Menu>
            )}
        </div>
    );
};
