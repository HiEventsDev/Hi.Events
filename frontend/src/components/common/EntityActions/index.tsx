import {ReactNode} from "react";
import {t} from "@lingui/macro";
import {ActionIcon, Button, Menu, Tooltip} from "@mantine/core";
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

export const EntityActionBar = ({actions}: { actions: EntityAction[] }) => {
    const primary = byGroup(actions, 'primary');
    const secondary = byGroup(actions, 'secondary');
    const danger = byGroup(actions, 'danger');

    return (
        <div className={classes.bar}>
            {primary.map(action => (
                <Button
                    key={action.key}
                    variant="default"
                    size="compact-sm"
                    color={action.color}
                    leftSection={action.icon}
                    onClick={action.onClick}
                    data-testid={action.dataTestId}
                >
                    {action.label}
                </Button>
            ))}

            {(secondary.length > 0 || danger.length > 0) && (
                <Menu shadow="md" width={210} position="bottom-end">
                    <Menu.Target>
                        <Tooltip label={t`More actions`} withArrow position="bottom">
                            <ActionIcon variant="subtle" color="gray" size="lg" radius="xl">
                                <IconDotsVertical size={16}/>
                            </ActionIcon>
                        </Tooltip>
                    </Menu.Target>
                    <Menu.Dropdown>
                        {secondary.map(action => (
                            <Menu.Item key={action.key} leftSection={action.icon} onClick={action.onClick}>
                                {action.label}
                            </Menu.Item>
                        ))}
                        {secondary.length > 0 && danger.length > 0 && <Menu.Divider/>}
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
                    </Menu.Dropdown>
                </Menu>
            )}
        </div>
    );
};
